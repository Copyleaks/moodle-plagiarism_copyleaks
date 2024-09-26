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
            PLAGIARISM_COPYLEAKS_DETECT_WF_ISSUES_FIELD_NAME,
            PLAGIARISM_COPYLEAKS_SCAN_AI_FIELD_NAME,
            PLAGIARISM_COPYLEAKS_SCAN_PLAGIARISM_FIELD_NAME
        );
        $savedvalues = array(
            $detectiondata->showWritingFeedbackIssues,
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
                $field->value = $savedvalues[$idx++];
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
            PLAGIARISM_COPYLEAKS_DETECT_WF_ISSUES_FIELD_NAME,
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

    /**
     * @param bool $connectionstatus - true - active / false - inactive.
     */
    public static function upsert_config_api_connection_status($connectionstatus) {
        global $DB;
        $field = $DB->get_record(
            'plagiarism_copyleaks_config',
            array(
                'cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID,
                'name' => PLAGIARISM_COPYLEAKS_API_CONNECTION_STATUS_FIELD_NAME
            )
        );
        if (!$field || !isset($field)) {
            $newfield = new stdClass();
            $newfield->cm = PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID;
            $newfield->name = PLAGIARISM_COPYLEAKS_API_CONNECTION_STATUS_FIELD_NAME;
            $newfield->value = $connectionstatus ? 1 : 0;
            if (!$DB->insert_record('plagiarism_copyleaks_config', $newfield)) {
                throw new moodle_exception(get_string('clupdateerror', 'plagiarism_copyleaks'));
            }
        } else {
            $field->value = $connectionstatus ? 1 : 0;
            if (!$DB->update_record('plagiarism_copyleaks_config', $field)) {
                throw new moodle_exception(get_string('clupdateerror', 'plagiarism_copyleaks'));
            }
        }
    }

    /**
     * Get the Api connection status.
     */
    public static function is_copyleaks_api_connected() {
        global $DB;
        $field = $DB->get_record(
            'plagiarism_copyleaks_config',
            array(
                'cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID,
                'name' => PLAGIARISM_COPYLEAKS_API_CONNECTION_STATUS_FIELD_NAME
            )
        );

        if ($field) {
            return $field->value == "1";
        }

        return false;
    }
    
    /** 
     * Check if the course module is duplicated and error message.
     * @param int $cmid
     * @return bool
     */
    public static function get_cm_duplicated_error_message($cmid) {
        $cmduplicate = self::get_cm_duplicate($cmid);
        if ($cmduplicate && $cmduplicate->status == plagiarism_copyleaks_cm_duplication_status::ERROR) {
            return $cmduplicate->errormsg;
        }
        return null;
    }

    /**
     * Check if the course module is duplicated and error.
     * @param int $cmid
     * @return bool
     */
    public static function is_cm_duplicated_error($cmid) {
        $cmduplicate = self::get_cm_duplicate($cmid);
        return $cmduplicate && $cmduplicate->status == plagiarism_copyleaks_cm_duplication_status::ERROR;
    }

    /**
     * Check if the course module is duplicated and queued.
     * @param int $cmid
     * @return bool
     */
    public static function is_cm_duplicated_queued($cmid) {
        $cmduplicate = self::get_cm_duplicate($cmid);
        return $cmduplicate && $cmduplicate->status == plagiarism_copyleaks_cm_duplication_status::QUEUED;
    }

    /**
     * Get the course module id of the duplicated course module.
     * @param int $cmid
     * @return object
     */
    private static function get_cm_duplicate($cmid) {
        global $DB;
        return $DB->get_record('plagiarism_copyleaks_cm_copy', array('new_cm_id' => $cmid));
    }

    /**
     * Get the configuration by name.
     * @param string $name
     * @return object
     */
    public static function is_config_result_view_enable($name, $cmid) {
        global $DB, $USER, $COURSE;

        $context = context_course::instance($COURSE->id);
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $isstudent = user_has_role_assignment($USER->id, $studentrole->id, $context->id);

        if (!$isstudent) {
            return true;
        };

        $defaultconfig = $DB->get_record(
            'plagiarism_copyleaks_config',
            array('name' => $name, 'cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID)
        );

        if ($defaultconfig) {
            return $defaultconfig->value;
        }

        $config = $DB->get_record(
            'plagiarism_copyleaks_config',
            array('name' => $name, 'cm' => $cmid)
        );

        return $config ? $config->value : true;
    }

    /**
     * Save the request to Syncs Copyleaks integration data.
     * @param array $data
     * @param string $plagiarism_copyleaks_key
     */
    public static function queue_copyleaks_integration_data_sync_request($data, $plagiarism_copyleaks_key) {
        global $DB;
        $request = new stdClass();
        $request->created_date = time();
        $request->cmid = 0;
        $request->endpoint = "/api/moodle/plugin/$plagiarism_copyleaks_key/task/upsert-plugin-integraion-data";
        $request->total_retry_attempts = 0;
        $request->data = json_encode($data);
        $request->priority = plagiarism_copyleaks_priority::HIGH;
        $request->status = plagiarism_copyleaks_request_status::FAILED;
        $request->fail_message = "";
        $request->verb = 'POST';
        $request->require_auth = true;
        if (!$DB->insert_record('plagiarism_copyleaks_request', $request)) {
            \plagiarism_copyleaks_logs::add(
                "failed to create new database record queue request for endpoint: $request->endpoint",
                "INSERT_RECORD_FAILED"
            );
        }
    }

}
