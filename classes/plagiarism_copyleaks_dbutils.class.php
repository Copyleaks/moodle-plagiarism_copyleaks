<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * copyleaks_comms.class.php - used for communications between Moodle and Copyleaks
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');


/**
 * Function that can be used in multiple places
 */
class plagiarism_copyleaks_dbutils {

    /**
     * @param string $key
     * @param string $cmid
     * @param string $endpoint
     * @param array $data
     * @param int $priority
     * @param string $error
     * @param bool $require_auth
     * @return void
     */
    public static function queued_failed_request($key, $cmid, $endpoint, $data, $priority, $error, $verb, $requireauth = true) {
        global $DB;
        $request = $DB->get_record(
            'plagiarism_copyleaks_request',
            array(
                'cmid' => $cmid,
                'endpoint' => $endpoint,
            )
        );

        if (!$request) {
            $request = new stdClass();
            $request->created_date = time();
            $request->cmid = $cmid;
            $request->endpoint = $endpoint;
            $request->total_retry_attempts = 0;
            $request->data = json_encode($data);
            $request->priority = $priority;
            $request->status = plagiarism_copyleaks_request_status::FAILED;
            $request->fail_message = $error;
            $request->verb = $verb;
            $request->require_auth = $requireauth;
            if (!$DB->insert_record('plagiarism_copyleaks_request', $request)) {
                \plagiarism_copyleaks_logs::add(
                    "failed to create new database record queue request for cmid: " .
                        $data["courseModuleId"] . ", endpoint: /api/moodle/plugin/$key/upsert-module",
                    "INSERT_RECORD_FAILED"
                );
            }
        }
    }

    /**
     * @param string $cmid check if the cmid is in the requests queue
     * @return bool
     */
    public static function is_course_module_request_queued($cmid) {
        global $DB;
        $record = $DB->get_record('plagiarism_copyleaks_request', ['cmid' => $cmid]);
        return isset($record) && $record;
    }

    /**
     * @param string $version
     */
    public static function update_copyleaks_eula_version($version) {
        global $DB;
        $configeula = $DB->get_record(
            'plagiarism_copyleaks_config',
            array('cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID, 'name' => 'latest_eula_version')
        );
        $configeula->value = $version;
        if (!$DB->update_record('plagiarism_copyleaks_config', $configeula)) {
            \plagiarism_copyleaks_logs::add(
                "Could not update eula version to: $version",
                "UPDATE_RECORD_FAILED"
            );
        }
    }

    /**
     * @param string userid check eula version by user Moodle id
     * @return bool
     */
    public static function is_user_eula_uptodate($userid) {
        global $DB;

        $user = $DB->get_record('plagiarism_copyleaks_users', array('userid' => $userid));
        if (!$user || !isset($user)) {
            return false;
        }

        $version = self::get_copyleaks_eula_version();

        $usereulaarr = $DB->get_records_select(
            'plagiarism_copyleaks_eula',
            '(ci_user_id = ' . $userid . ')',
            null,
            $DB->sql_order_by_text('date'),
            '*',
            0,
            1
        );
        if (empty($usereulaarr)) {
            return false;
        }
        $usereula = null;
        foreach ($usereulaarr as $item) {
            $usereula = $item;
            break;
        }

        return $usereula && $version == $usereula->version;
    }

    /*
    * @param string userid
    */
    public static function upsert_eula_by_user_id($userid) {
        global $DB;
        $user = $DB->get_record('plagiarism_copyleaks_users', array('userid' => $userid));
        $curreulaversion = self::get_copyleaks_eula_version();

        if (!$user) {
            if (!$DB->insert_record('plagiarism_copyleaks_users', array('userid' => $userid))) {
                \plagiarism_copyleaks_logs::add(
                    "failed to insert new database record for : " .
                        "plagiarism_copyleaks_eula, Cannot create new user record for user $userid",
                    "INSERT_RECORD_FAILED"
                );
            }
        }

        $newusereula = array(
            "ci_user_id" => $userid,
            "version" => $curreulaversion,
            "is_synced" => false,
            "date" => date('Y-m-d H:i:s')
        );

        // There is a second run for 'handle_submissions' so it is
        // best to check by the userid and the version before inserting a new one.
        $usereulaversion = $DB->record_exists(
            'plagiarism_copyleaks_eula',
            array("ci_user_id" => $userid, "version" => $curreulaversion)
        );
        if (!$usereulaversion && !$DB->insert_record('plagiarism_copyleaks_eula', $newusereula)) {
            \plagiarism_copyleaks_logs::add(
                "failed to insert new database record for :" .
                    "plagiarism_copyleaks_eula, Cannot create new user record eula for user $userid",
                "INSERT_RECORD_FAILED"
            );
        }
    }

    /**
     * return string
     */
    public static function get_copyleaks_eula_version() {
        global $DB;
        $record = $DB->get_record(
            'plagiarism_copyleaks_config',
            array(
                'cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID,
                'name' => PLAGIARISM_COPYLEAKS_EULA_FIELD_NAME
            )
        );
        if ($record) {
            return $record->value;
        }
        return null;
    }
}
