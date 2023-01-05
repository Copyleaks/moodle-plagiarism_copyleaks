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
     * Return the current lang code to use with Copyleaks
     * @return string Supported Copyleaks lang code
     */
    public function get_lang() {
        $defaultlangcode = 'en';
        try {
            $langcode = str_replace("_utf8", "", current_language());
            $langarray = array(
                'en' => $defaultlangcode,
                'en_us' => $defaultlangcode,
                'fr' => 'fr',
                'fr_ca' => 'fr',
                'es' => 'es',
                'fr' => 'fr',
                'pt' => 'pt',
                'hi' => 'hi',
                'zh' => 'zh',
                'it' => 'it',
                'ja' => 'ja',
                'de' => 'de',
                'tr' => 'tr',
                'ru' => 'ru',
                'ar' => 'ar'
            );
            return (isset($langarray[$langcode])) ? $langarray[$langcode] : $defaultlangcode;
        } catch (Exception $e) {
            return $defaultlangcode;
        }
    }

    /**
     * Get plugin default settings that are saved at Copyleaks API
     */
    public function get_plugin_default_settings() {
        if (isset($this->key) && isset($this->secret)) {
            $result = plagiarism_copyleaks_http_client::execute(
                'GET',
                $this->copyleaks_api_url() . "/api/moodle/plugin/" . $this->key . "/scan-settings",
                true
            );
            return $result;
        }
    }

    /**
     * Save plugin default settings at Copyleaks API
     * @param any $settings Copyleaks settings
     */
    public function save_plugin_default_settings($settings) {
        if (isset($this->key) && isset($this->secret)) {
            plagiarism_copyleaks_http_client::execute(
                'POST',
                $this->copyleaks_api_url() . "/api/moodle/plugin/" . $this->key . "/scan-settings",
                true,
                $settings
            );
        }
    }

    /**
     * get course module copyleaks settings
     * @param string $cmid Course Module ID
     * @return any course module copyleaks settings
     */
    public function get_course_module_settings(string $cmid) {
        if (isset($this->key) && isset($this->secret)) {
            $result = plagiarism_copyleaks_http_client::execute(
                'GET',
                $this->copyleaks_api_url() . "/api/moodle/plugin/" . $this->key . "/course/module/" . $cmid . "/scan-settings",
                true
            );
            return $result;
        }
    }

    /**
     * save course module copyleaks settings
     * @param string $cmid course module id
     * @param string $cmname course module type
     * @param string $name course module name
     * @param any $settings Copyleaks settings
     */
    public function save_course_module_settings(string $cmid, string $cmname, string $name, $settings = null) {
        if (isset($this->key) && isset($this->secret)) {
            $reqdata = (array)[
                'name' => $name,
                'courseModuleName' => $cmname,
                'scanProperties' => $settings,
            ];
            $result = plagiarism_copyleaks_http_client::execute(
                'POST',
                $this->copyleaks_api_url() . "/api/moodle/plugin/" . $this->key . "/course/module/" . $cmid . "/scan-settings",
                true,
                json_encode($reqdata)
            );
            return $result;
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
        string $submissiontype
    ) {
        if (isset($this->key) && isset($this->secret)) {
            $coursemodule = get_coursemodule_from_id('', $cmid);
            if (
                plagiarism_copyleaks_moduleconfig::is_allow_student_results_info() &&
                plagiarism_copyleaks_moduleconfig::did_user_accept_eula($userid)
            ) {
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
                    'courseName' => (get_course($coursemodule->course))->fullname
                ];
            } else {
                $paramsmerge = (array)[
                    'fileName' => $filename,
                    'courseModuleId' => $cmid,
                    'moodleUserId' => $userid,
                    'identifier' => $identifier,
                    'submissionType' => $submissiontype
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

            $result = plagiarism_copyleaks_http_client::execute(
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
    public function get_resubmit_reports_ids($cursor) {
        if (isset($this->key) && isset($this->secret)) {
            $reqbody = (array)[
                'cursor' => $cursor
            ];
            $result = plagiarism_copyleaks_http_client::execute(
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
            plagiarism_copyleaks_http_client::execute(
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
            $result = plagiarism_copyleaks_http_client::execute(
                'POST',
                $this->copyleaks_api_url() . "/api/moodle/" . $this->key .
                    "/report/" . $scanid . "/" . $isinstructor . "/request-access",
                true
            );

            return $result->token;
        }
    }

    /**
     * request access for integration repositories
     * @param string $cmid course module (optional)
     * @return string a JWT to access integration repositories only
     */
    public function request_access_for_repositories(string $cmid = null) {
        if (isset($this->key) && isset($this->secret)) {

            if (isset($cmid)) {
                $url = $this->copyleaks_api_url() . "/api/moodle/" . $this->key . "/repositories/" . $cmid . "/request-access";
            } else {
                $url = $this->copyleaks_api_url() . "/api/moodle/" . $this->key . "/repositories/request-access";
            }

            $result = plagiarism_copyleaks_http_client::execute(
                'POST',
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

            $result = plagiarism_copyleaks_http_client::execute(
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
     * @return bool
     */
    public static function test_copyleaks_connection($context) {
        $cl = new plagiarism_copyleaks_comms();
        return $cl->test_connection($context);
    }

    /**
     * Test if server can communicate with Copyleaks.
     * @param string $context
     * @return bool
     */
    public function test_connection($context) {
        try {
            if (isset($this->key) && isset($this->secret)) {
                plagiarism_copyleaks_http_client::execute(
                    'GET',
                    $this->copyleaks_api_url() . "/api/moodle/plugin/" . $this->key . "/test-connection",
                    true
                );
                return true;
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
}
