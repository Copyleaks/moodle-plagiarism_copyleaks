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
 * Functions that can be used in multiple places
 */
class plagiarism_copyleaks_dbutils {

    /**
     * Save the failed request to the table queue in the data base.
     * @param string $cmid
     * @param string $endpoint
     * @param array $data
     * @param int $priority
     * @param string $error
     * @param bool $require_auth
     */
    public static function queued_failed_request($cmid, $endpoint, $data, $priority, $error, $verb, $requireauth = true) {
        global $DB;
        $records = $DB->get_record_select(
            'plagiarism_copyleaks_request',
            "cmid = ? AND endpoint = ?",
            array($cmid, $endpoint)

        );

        if (!$records) {
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
                        $data["courseModuleId"] . ", endpoint: $endpoint",
                    "INSERT_RECORD_FAILED"
                );
            }
        }
    }

    /**
     * Update current eula version.
     * @param string $version
     */
    public static function update_copyleaks_eula_version($version) {
        global $DB;
        $configeula = $DB->get_record(
            'plagiarism_copyleaks_config',
            array('cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID, 'name' => PLAGIARISM_COPYLEAKS_EULA_FIELD_NAME)
        );

        if ($configeula) {
            $configeula->value = $version;
            if (!$DB->update_record('plagiarism_copyleaks_config', $configeula)) {
                \plagiarism_copyleaks_logs::add(
                    "Could not update eula version to: $version",
                    "UPDATE_RECORD_FAILED"
                );
            }
        } else {
            $configeula = array(
                'cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID,
                'name' => PLAGIARISM_COPYLEAKS_EULA_FIELD_NAME,
                'value' => $version,
                'config_hash' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID . "_" . PLAGIARISM_COPYLEAKS_EULA_FIELD_NAME
            );
            if (!$DB->insert_record('plagiarism_copyleaks_config', $configeula)) {
                throw new moodle_exception(get_string('clinserterror', 'plagiarism_copyleaks'));
            }
        }
    }

    /**
     * Check if the last eula of the user is the same as the last eula version.
     * @param string userid check eula version by user Moodle id.
     * @return bool
     */
    public static function is_user_eula_uptodate($userid) {
        global $DB;

        $user = $DB->get_record('plagiarism_copyleaks_users', array('userid' => $userid));
        if (!$user || !isset($user)) {
            return false;
        }

        $version = self::get_copyleaks_eula_version();
        return self::is_eula_version_update_by_userid($userid, $version);
    }

    /**
     * Update in Copyleaks server that the user accepted the current version.
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
                        "plagiarism_copyleaks_users, Cannot create new user record for user $userid",
                    "INSERT_RECORD_FAILED"
                );
            }
        }

        $newusereula = array(
            "ci_user_id" => $userid,
            "version" => $curreulaversion,
            "is_synced" => false,
            "accepted_at" => time()
        );

        if (
            !self::is_eula_version_update_by_userid($userid, $curreulaversion)
            && !$DB->insert_record('plagiarism_copyleaks_eula', $newusereula)
        ) {
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
        return PLAGIARISM_COPYLEAKS_DEFUALT_EULA_VERSION;
    }

    /**
     * @param string $userid check by user id if updated.
     * @param string $version id the user id up-to-date the version
     * @return object
     */
    private static function is_eula_version_update_by_userid($userid, $version) {
        global $DB;
        $result = $DB->record_exists_select(
            "plagiarism_copyleaks_eula",
            "ci_user_id = ? AND version = ?",
            array($userid, $version)
        );
        return $result;
    }

    /**
     * @param object $detectiondata - detections value flags to detect.
     */
    public static function update_config_scanning_detection($detectiondata) {
        global $DB;
        $scandetections = array(
            PLAGIARISM_COPYLEAKS_DETECT_GRAMMAR_FIELD_NAME,
            PLAGIARISM_COPYLEAKS_SCAN_AI_FIELD_NAME,
            PLAGIARISM_COPYLEAKS_SCAN_PLAGIARISM_FIELD_NAME
        );
        $savedvalues = array(
            $detectiondata->showGrammar,
            $detectiondata->showAI,
            $detectiondata->showPlagiarism
        );
        $idx = 0;
        foreach ($scandetections as $fieldname) {
            $field = $DB->get_record(
                'plagiarism_copyleaks_config',
                array(
                    'cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID,
                    'name' => $fieldname
                )
            );
            if (!$field || !isset($field)) {
                $newfield = new stdClass();
                $newfield->cm = PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID;
                $newfield->name = $fieldname;
                $newfield->value = $savedvalues[$idx++];
                if (!$DB->insert_record('plagiarism_copyleaks_config', $newfield)) {
                    throw new moodle_exception(get_string('clupdateerror', 'plagiarism_copyleaks'));
                }
            } else {
                if (!$DB->update_record('plagiarism_copyleaks_config', $field)) {
                    throw new moodle_exception(get_string('clupdateerror', 'plagiarism_copyleaks'));
                }
            }
        }
    }

    /**
     * Get the scan detection configuration.
     */
    public static function get_config_scanning_detection() {
        global $DB;
        $scandetections = array(
            PLAGIARISM_COPYLEAKS_DETECT_GRAMMAR_FIELD_NAME,
            PLAGIARISM_COPYLEAKS_SCAN_AI_FIELD_NAME,
            PLAGIARISM_COPYLEAKS_SCAN_PLAGIARISM_FIELD_NAME
        );
        $detectiondata = array();
        foreach ($scandetections as $fieldname) {
            $field = $DB->get_record(
                'plagiarism_copyleaks_config',
                array(
                    'cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID,
                    'name' => $fieldname
                )
            );
            $detectiondata[$fieldname] = $field->value == "1";
        }
        return $detectiondata;
    }
}
