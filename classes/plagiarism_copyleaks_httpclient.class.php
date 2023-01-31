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
 * copyleaks_httpclient.class.php - containes a generic method for AJAX calls
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_authexception.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_exception.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_ratelimitexception.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_undermaintenanceexception.class.php');
/**
 * containes a generic method for AJAX calls
 */
class plagiarism_copyleaks_http_client {

    /**
     * Generic execute method for AJAX calls
     * @param string $verb - request verb
     * @param string $url request url
     * @param bool $requireauth is request require authentication
     * @param any $data request data
     * @param bool $isauthretry is authentication retry request
     * @param string $contenttype request content type
     * @return mixed response data (if there is any)
     */
    public static function execute(
        $verb,
        $url,
        $requireauth = false,
        $data = null,
        $isauthretry = false,
        $contenttype = 'application/json'
    ) {
        global $CFG;        

        if (!class_exists('curl')) {

            require_once($CFG->libdir . '/filelib.php');
        }

        $c = new curl(array('proxy' => true));
        $c->setopt(array());
        $c->setopt(
            array(
                'CURLOPT_RETURNTRANSFER' => 1,
                'CURLOPT_TIMEOUT' => 70, // Set to 70 seconds according to the Copyleaks API docs.
                'CURLOPT_HTTPAUTH' => CURLAUTH_BASIC
            )
        );

        $version = 2023013105;
        $headers = (array)[
            'Content-Type' => $contenttype,
            'Plugin-Version' => "$version"
        ];

        if ($requireauth) {
            $cljwttoken = plagiarism_copyleaks_comms::login_to_copyleaks();
            $authorization = "Authorization: Bearer $cljwttoken";
            $pluginversion = "Plugin-Version: $version";
            $headers = array('Content-Type: ' . $contenttype, $authorization, $pluginversion);
        }

        $c->setHeader($headers);

        switch ($verb) {
            case 'GET':
                $result = $c->get($url);
                break;
            case 'POST':
                $result = $c->post($url, $data);
                break;
            default:
                throw new Exception('Unsupported HTTP verb: ' . $verb);
        }

        // Get status code.
        $statuscode = $c->info['http_code'];

        if (self::is_success_status_code($statuscode)) {
            if (isset($result)) {
                $contenttype = $c->info['content_type'];
                if ($contenttype == 'application/json; charset=utf-8') {
                    return json_decode($result);
                } else {
                    return $result;
                }
            } else {
                return;
            }
        } else if (self::is_unauthorized_status_code($statuscode)) {
            if (!$isauthretry) {
                // Try to get the jwt again from copyleaks (retry if unauthorized).
                $cljwttoken = plagiarism_copyleaks_comms::login_to_copyleaks(null, null, null, true);
                if (isset($cljwttoken)) {
                    return self::execute($verb, $url, $requireauth, $data, true);
                }
            }
            throw new plagiarism_copyleaks_auth_exception();
        } else if (self::is_under_maintenance_response($statuscode)) {
            throw new plagiarism_copyleaks_under_maintenance_exception();
        } else if (self::is_rate_limit_response($statuscode)) {
            throw new plagiarism_copyleaks_rate_limit_exception();
        } else {
            throw new plagiarism_copyleaks_exception($result, $statuscode);
        }
    }

    /**
     * check if passed status code is representing success
     * @param int $statuscode
     * @return bool
     */
    private static function is_success_status_code(int $statuscode) {
        return $statuscode >= 200 && $statuscode <= 299;
    }

    /**
     * check if passed status code is representing success
     * @param int $statuscode
     * @return bool
     */
    private static function is_unauthorized_status_code(int $statuscode) {
        return $statuscode === 401;
    }

    /**
     * check if passed status code is representing service unavailable
     * @param int $statuscode
     * @return bool
     */
    private static function is_under_maintenance_response(int $statuscode) {
        return $statuscode === 503;
    }

    /**
     * check if passed status code is representing too many requests
     * @param int $statuscode
     * @return bool
     */
    private static function is_rate_limit_response(int $statuscode) {
        return $statuscode === 429;
    }
}
