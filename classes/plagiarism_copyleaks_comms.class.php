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

require_once($CFG->dirroot . '/plagiarism/copyleaks/lib.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_httpclient.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_pluginconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_dbutils.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/enums/plagiarism_copyleaks_enums.php');
/**
 * Used for communications between Moodle and Copyleaks
 */
class plagiarism_copyleaks_comms {
    /** @var stdClass Copyleaks plugin configurations */
    private $config;

    /** @var string Copyleaks account secret */
    private $secret;

    /** @var string Copyleaks account key  */
    private $key;

    /**
     * class constructor
     */
    public function __construct() {
        $this->config = plagiarism_copyleaks_pluginconfig::admin_config();
        if (isset($this->config->plagiarism_copyleaks_secret) && isset($this->config->plagiarism_copyleaks_key)) {
            $this->secret = $this->config->plagiarism_copyleaks_secret;
            $this->key = $this->config->plagiarism_copyleaks_key;
        }
    }

    /**
     * Submit to Copyleaks for plagiairsm scan
     * @param string $filepath file path
     * @param string $filename file name
     * @param string $cmid course module id
     * @param string $userid user id
     * @param string $identifier content identifier
     * @param string $submissiontype submission type
     * @return mixed
     */
    public function submit_for_plagiarism_scan(
        string $filepath,
        string $filename,
        string $cmid,
        string $userid,
        string $identifier,
        string $submissiontype,
        $externalid = null
    ) {
        if (isset($this->key) && isset($this->secret)) {
            $coursemodule = get_coursemodule_from_id('', $cmid);
            $duedate = plagiarism_copyleaks_utils::get_course_module_duedate($cmid);
            $coursestartdate = plagiarism_copyleaks_utils::get_course_start_date($coursemodule->course);
            if (plagiarism_copyleaks_dbutils::is_user_eula_uptodate($userid)) {
                $student = get_complete_user_data('id', $userid);
                $paramsmerge = (array)[
                    'fileName' => $filename,
                    'courseModuleId' => $cmid,
                    'moodleUserId' => $userid,
                    'identifier' => $identifier,
                    'submissionType' => $submissiontype,
                    'userEmail' => $student->email,
                    'userFullName' => $student->firstname . " " . $student->lastname,
                    'moduleName' => $coursemodule->name,
                    'courseId' => $coursemodule->course,
                    'courseName' => (get_course($coursemodule->course))->fullname,
                    'duedate' => $duedate,
                    'coursestartdate' => $coursestartdate,
                    'oldScanId' => $externalid // In case the insrtuctor pressed "Try again" button.
                ];
            } else {
                $paramsmerge = (array)[
                    'fileName' => $filename,
                    'courseModuleId' => $cmid,
                    'moodleUserId' => $userid,
                    'identifier' => $identifier,
                    'submissionType' => $submissiontype,
                    'courseId' => $coursemodule->course,
                    'courseName' => (get_course($coursemodule->course))->fullname,
                    'duedate' => $duedate,
                    'coursestartdate' => $coursestartdate,
                    'oldScanId' => $externalid
                ];
            }

            $mimetype = mime_content_type($filepath);
            if (class_exists('CURLFile')) {
                $paramsmerge['file'] = new \CURLFile($filepath, $mimetype, $filename);
            } else {
                $paramsmerge['file'] = '@' . $filepath;
            }

            $result = plagiarism_copyleaks_http_client::execute(
                'POST',
                $this->copyleaks_api_url() . "/api/moodle/plugin/" . $this->key . "/task/submit-for-scan",
                true,
                $paramsmerge,
                false,
                "multipart/form-data"
            );
            return $result;
        }
    }

    /**
     * get the Copyleaks API scan instances for submissions
     * @param array $submissionsinstances
     * @return array a list of Copyleaks scan instances for files
     */
    public function get_plagiarism_scans_instances(array $submissionsinstances) {
        if (isset($this->key) && isset($this->secret)) {

            $params = (array)[
                'instances' => $submissionsinstances,
            ];

            $result = plagiarism_copyleaks_http_client::execute_retry(
                'POST',
                $this->copyleaks_api_url() . "/api/moodle/plugin/" . $this->key . "/task/scan-instances",
                true,
                json_encode($params)
            );

            return $result;
        }
    }

    /**
     * Get resubmit reports ids from lms server
     * @param string $cursor Copyleaks db cursor
     * @return object $result an array of resubmitted ids and new ids that rescanned
     */
    public function get_resubmit_reports_ids() {
        if (isset($this->key) && isset($this->secret)) {
            $reqbody = (array)[
                'cursor' => ''
            ];
            $result = plagiarism_copyleaks_http_client::execute_retry(
                'POST',
                $this->copyleaks_api_url() . "/api/moodle/plugin/" . $this->key . "/task/resubmit-scans",
                true,
                json_encode($reqbody)
            );
            return $result;
        }
    }

    /**
     * send request to delete resubmitted id to copyleaks server
     * @param array $ids Copyleaks report scan ids
     */
    public function delete_resubmitted_ids(array $ids) {
        if (isset($this->key) && isset($this->secret)) {
            $reqbody = (array)[
                'ids' => $ids
            ];
            plagiarism_copyleaks_http_client::execute_retry(
                'POST',
                $this->copyleaks_api_url() . "/api/moodle/plugin/" . $this->key . "/task/resubmit-scans/delete",
                true,
                json_encode($reqbody)
            );
        }
    }

    /**
     * request access for copyleaks report
     * @param string $scanid Copyleaks report scan id
     * @param boolean $isinstructor Copyleaks report scan id
     * @return string a JWT to access student report only
     */
    public function request_access_for_report(string $scanid, $isinstructor) {
        if ($isinstructor == 0) {
            $isinstructor = -1;
        }

        if (isset($this->key) && isset($this->secret)) {
            $result = plagiarism_copyleaks_http_client::execute_retry(
                'POST',
                $this->copyleaks_api_url() . "/api/moodle/" . $this->key .
                    "/report/" . $scanid . "/" . $isinstructor . "/request-access",
                true
            );

            return $result->token;
        }
    }

    /**
     * request access for copyleaks report.
     * @param boolean $role Moodle user's role.
     * @param array $breadcrumbs Moodle breadcrumbs.
     * @param array $name of the activity type.
     * @param array $coursemodulename of the activity.
     * @return string $cmid for the settings and access.
     */
    public function request_access_for_settings($role, $breadcrumbs, $name = '', $coursemodulename = '', $cmid = 0) {
        if (isset($this->key) && isset($this->secret)) {
            $reqbody = (array)[
                'breadcrumbs' => $breadcrumbs,
                'name' => $name,
                'courseModuleName' => $coursemodulename,
                'accessRole' => $role
            ];
            $url = $this->copyleaks_api_url() . "/api/moodle/" . $this->key . "/settings/request-access";
            if (isset($cmid)) {
                $url = $url . "/$cmid";
            }
            $result = plagiarism_copyleaks_http_client::execute_retry(
                'POST',
                $url,
                true,
                json_encode($reqbody)
            );

            return $result->token;
        }
    }

    /**
     * request access for copyleaks report.
     * @return string $cmid for the settings and access.
     */
    public function request_access_for_analytics($cmid) {
        if (isset($this->key) && isset($this->secret)) {
            $url = $this->copyleaks_api_url() . "/api/moodle/plugin/" . $this->key . "/analytics/request-access";
            if (isset($cmid)) {
                $url = $url . "/$cmid";
            }
            $result = plagiarism_copyleaks_http_client::execute(
                'GET',
                $url,
                true
            );
            return $result->token;
        }
    }

    /**
     * get copyleaks api url.
     * @return string api url if exists, otherwise return null
     */
    public static function copyleaks_api_url() {
        $apiurl = get_config('plagiarism_copyleaks', 'plagiarism_copyleaks_apiurl');
        if (isset($apiurl) && !empty($apiurl)) {
            return $apiurl;
        }

        return "https://lti.copyleaks.com";
    }

    /**
     * login to copyleaks api and get token
     * this will also get token from db if exisits
     * @param string $apiurl copyleaks communication url (optional)
     * @param string $key Copyleaks API connection key (optional)
     * @param string $secret Copyleaks API connection secret (optional)
     * @param bool $force true to ignore db cached jwt token (optional)
     * @return string jwt access token for copyleaks api
     */
    public static function login_to_copyleaks($apiurl = null, $key = null, $secret = null, $force = false) {
        if (!isset($secret) || !isset($key) || !isset($apiurl)) {
            // If key and secret was not passed, try to read them from admin config.
            $config = plagiarism_copyleaks_pluginconfig::admin_config();
            if (
                isset($config->plagiarism_copyleaks_secret) &&
                isset($config->plagiarism_copyleaks_key) &&
                isset($config->plagiarism_copyleaks_apiurl)
            ) {
                $secret = $config->plagiarism_copyleaks_secret;
                $key = $config->plagiarism_copyleaks_key;
                $apiurl = $config->plagiarism_copyleaks_apiurl;

                if (!isset($secret) || !isset($key) || !isset($apiurl)) {
                    return null;
                }
            }
        }

        if (!$force) {
            // If not force ,try to get them from cache.
            $config = plagiarism_copyleaks_pluginconfig::admin_config();
            if (isset($config->plagiarism_copyleaks_jwttoken)) {
                $result = $config->plagiarism_copyleaks_jwttoken;
            }
        }

        if (!isset($result) || $force) {
            // Login to copyleaks api and get jwt.
            $reqbody = (array)[
                'secret' => $secret
            ];

            $result = plagiarism_copyleaks_http_client::execute_retry(
                'POST',
                $apiurl . "/api/moodle/plugin/" . $key . "/login",
                false,
                $reqbody,
                true
            );

            if ($result) {
                $result = $result->jwt;
                set_config('plagiarism_copyleaks_jwttoken', $result, 'plagiarism_copyleaks');
            }
        }

        return $result;
    }

    /**
     * Test if server can communicate with Copyleaks.
     * @param string $context
     * @param bool $updateconfig
     * @return bool
     */
    public static function test_copyleaks_connection($context, $updateconfig = false) {
        $cl = new plagiarism_copyleaks_comms();
        return $cl->test_connection($context, $updateconfig);
    }

    /**
     * Test if server can communicate with Copyleaks.
     * @param string $context
     * @param bool $updateconfig
     * @return bool
     */
    public function test_connection($context, $updateconfig = false) {
        try {
            if (isset($this->key) && isset($this->secret)) {
                $chekbalance = $updateconfig ? "true" : "false";
                $result = plagiarism_copyleaks_http_client::execute_retry(
                    'GET',
                    $this->copyleaks_api_url() . "/api/moodle/plugin/" .
                        $this->key . "/test-connection?source=" . $context . "&checkBalance=$chekbalance",
                    true
                );

                if ($updateconfig) {
                    plagiarism_copyleaks_dbutils::update_config_scanning_detection($result->detectionsValues);
                }

                if (isset($result) && isset($result->eulaVersion)) {
                    plagiarism_copyleaks_dbutils::update_copyleaks_eula_version($result->eulaVersion);
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } catch (Exception $e) {
            if ($context == 'scheduler_task') {
                $errormsg = get_string('cltaskfailedconnecting', 'plagiarism_copyleaks', $e->getMessage());
                plagiarism_copyleaks_logs::add($errormsg, 'API_ERROR');
            }
            return false;
        }
    }

    /**
     * Update course module temp id at Copyleaks server.
     * @param array $data
     */
    public function upsert_course_module($data) {
        $endpoint = "/api/moodle/plugin/$this->key/upsert-module";
        $verb = 'POST';
        try {
            plagiarism_copyleaks_http_client::execute(
                $verb,
                $this->copyleaks_api_url() . $endpoint,
                true,
                json_encode($data)
            );
        } catch (\Exception $e) {
            plagiarism_copyleaks_dbutils::queued_failed_request(
                $data['courseModuleId'],
                $endpoint,
                $data,
                plagiarism_copyleaks_priority::HIGH,
                $e->getMessage(),
                $verb
            );
        }
    }

    /**
     * Update courses at Copyleaks server.
     * @param array $data
     */
    public function upsert_courses($data) {
        $endpoint = "/api/moodle/plugin/$this->key/task/upsert-courses";
        $verb = 'POST';
        try {
            plagiarism_copyleaks_http_client::execute_retry(
                $verb,
                $this->copyleaks_api_url() . $endpoint,
                true,
                json_encode($data)
            );
        } catch (\Exception $e) {
            $errormsg = get_string('cltaskfailedconnecting', 'plagiarism_copyleaks', $e->getMessage());
            plagiarism_copyleaks_logs::add($errormsg, 'API_ERROR');
        }
    }

    public function save_users_data($data) {
        $endpoint = "/api/moodle/plugin/$this->key/task/upsert-users";
        $verb = 'POST';
        try {
            plagiarism_copyleaks_http_client::execute_retry(
                $verb,
                $this->copyleaks_api_url() . $endpoint,
                true,
                json_encode($data)
            );
        } catch (\Exception $e) {
            $errormsg = get_string('cltaskfailedconnecting', 'plagiarism_copyleaks', $e->getMessage());
            plagiarism_copyleaks_logs::add($errormsg, 'API_ERROR');
        }
    }


    /**
     * Update course module temp id at Copyleaks server.
     * @param array $data
     * @return object all the user ids that was updated succesfully in Copyleaks server
     */
    public function upsert_synced_eula($data) {
        $result = plagiarism_copyleaks_http_client::execute(
            'POST',
            $this->copyleaks_api_url() . "/api/moodle/plugin/$this->key/task/eula-approval-sync",
            true,
            json_encode($data)
        );
        return $result;
    }

    /**
     * Get all originality report ids with the updated scores
     * @param string $cursor Copyleaks db cursor
     * @return array $result an array of ids and its updated scores
     */
    public function get_unsynced_scans_scores() {
        if (isset($this->key) && isset($this->secret)) {
            return plagiarism_copyleaks_http_client::execute_retry(
                'GET',
                $this->copyleaks_api_url() . "/api/moodle/plugin/" . $this->key . "/task/unsynced-scans-scores",
                true,
            );
        }
    }

    /**
     * Send request to delete synced ids to copyleaks server
     * @param array $ids Copyleaks report scan ids
     */
    public function delete_synced_scans_by_ids(array $ids) {
        if (isset($this->key) && isset($this->secret)) {
            $reqbody = (array)[
                'ids' => $ids
            ];
            plagiarism_copyleaks_http_client::execute_retry(
                'POST',
                $this->copyleaks_api_url() . "/api/moodle/plugin/" . $this->key . "/task/synced-scans-scores/delete",
                true,
                json_encode($reqbody)
            );
        }
    }

    /**
     * Get all originality report ids with the updated scores
     * @param string $cursor Copyleaks db cursor
     * @return array $result an array of ids and its updated scores
     */
    public function get_unsynced_configs() {
        if (isset($this->key) && isset($this->secret)) {
            return plagiarism_copyleaks_http_client::execute_retry(
                'GET',
                $this->copyleaks_api_url() . "/api/moodle/plugin/" . $this->key . "/task/unsynced-configs",
                true,
            );
        }
    }

    /**
     * Send request to delete synced ids to copyleaks server
     * @param array $ids Copyleaks report scan ids
     */
    public function delete_synced_config_by_keys(array $ids) {
        if (isset($this->key) && isset($this->secret)) {
            $reqbody = (array)[
                'ids' => $ids
            ];
            plagiarism_copyleaks_http_client::execute_retry(
                'POST',
                $this->copyleaks_api_url() . "/api/moodle/plugin/" . $this->key . "/task/synced-configs/delete",
                true,
                json_encode($reqbody)
            );
        }
    }
}
