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
require_once($CFG->dirroot . '/plagiarism/copyleaks/locallib.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/copyleaks_httpclient.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/copyleaks_pluginconfig.class.php');
/**
 * Used for communications between Moodle and Copyleaks
 */
class copyleaks_comms {
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
        $this->config = copyleaks_pluginconfig::admin_config();
        if (isset($this->config->plagiarism_copyleaks_secret) && isset($this->config->plagiarism_copyleaks_key)) {
            $this->secret = $this->config->plagiarism_copyleaks_secret;
            $this->key = $this->config->plagiarism_copyleaks_key;
        }
    }

    /**
     * Get plugin default settings that are saved at Copyleaks API
     */
    public function get_plugin_default_settings() {
        if (isset($this->key) && isset($this->secret)) {
            $result = copyleaks_http_client::execute(
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
            copyleaks_http_client::execute(
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
            $result = copyleaks_http_client::execute(
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
            $result = copyleaks_http_client::execute(
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

            $paramsmerge = (array)[
                'fileName' => $filename,
                'courseModuleId' => $cmid,
                'moodleUserId' => $userid,
                'identifier' => $identifier,
                'submissionType' => $submissiontype,
            ];

            $mimetype = mime_content_type($filepath);
            if (class_exists('CURLFile')) {
                $paramsmerge['file'] = new \CURLFile($filepath, $mimetype, $filename);
            } else {
                $paramsmerge['file'] = '@' . $filepath;
            }

            $result = copyleaks_http_client::execute(
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

            $result = copyleaks_http_client::execute(
                'POST',
                $this->copyleaks_api_url() . "/api/moodle/plugin/" . $this->key . "/task/scan-instances",
                true,
                json_encode($params)
            );

            return $result;
        }
    }

    /**
     * request access for copyleaks report
     * @param string $scanid Copyleaks report scan id
     * @return string a JWT to access student report only
     */
    public function request_access_for_report(string $scanid) {
        if (isset($this->key) && isset($this->secret)) {
            $result = copyleaks_http_client::execute(
                'POST',
                $this->copyleaks_api_url() . "/api/moodle/" . $this->key . "/report/" . $scanid . "/request-access",
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
            $config = copyleaks_pluginconfig::admin_config();
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
            $config = copyleaks_pluginconfig::admin_config();
            if (isset($config->plagiarism_copyleaks_jwttoken)) {
                $result = $config->plagiarism_copyleaks_jwttoken;
            }
        }

        if (!isset($result) || $force) {
            // Login to copyleaks api and get jwt.
            $reqbody = (array)[
                'secret' => $secret
            ];

            $result = copyleaks_http_client::execute(
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
        $cl = new copyleaks_comms();
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
                copyleaks_http_client::execute(
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
                copyleaks_logs::add(get_string('cltaskfailedconnecting', 'plagiarism_copyleaks'), 'API_ERROR');
            }
            return false;
        }
    }
}
