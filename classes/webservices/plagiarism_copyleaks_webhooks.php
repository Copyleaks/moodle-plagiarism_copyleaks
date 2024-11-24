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
 * Copyleaks webhooks
 * @package   plagiarism_copyleaks
 * @author    Shade Amasha <shadea@copyleaks.com>
 * @copyright 2021 Copyleaks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_submissions.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_webserviceexception.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_dbutils.class.php');

/**
 * plagiarism_copyleaks_webhooks external API class
 */
class plagiarism_copyleaks_webhooks extends external_api {

    // Region Update Report Webhook.
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function update_report_webhook_parameters() {
        return new external_function_parameters(
          [
            'coursemoduleid' => new external_value(PARAM_TEXT, 'Course module ID'),
            'moodleuserid' => new external_value(PARAM_TEXT, 'Moodle user ID'),
            'identifier' => new external_value(PARAM_TEXT, 'Identifier'),
            'scanid' => new external_value(PARAM_TEXT, 'Scan ID'),
            'status' => new external_value(PARAM_INT, 'Report scan status'),
            'plagiarismscore' => new external_value(PARAM_FLOAT, 'Plagiarism score', VALUE_DEFAULT, null),
            'aiscore' => new external_value(PARAM_FLOAT, 'AI score', VALUE_DEFAULT, null),
            'writingfeedbackissues' => new external_value(PARAM_INT, 'Writing feedback issues', VALUE_DEFAULT, null),
            'ischeatingdetected' => new external_value(PARAM_BOOL, 'Is cheating detected', VALUE_DEFAULT, false),
            'errormessage' => new external_value(PARAM_TEXT, 'Error message', VALUE_DEFAULT, null),
          ]
        );
    }

    /**
     * Update report webhook
     * @param  string $coursemoduleid  Course module ID
     * @param  string $moodleuserid    Moodle user ID
     * @param  string $identifier      Identifier
     * @param  string $scanid          Scan ID
     * @param  int    $status          Report scan status
     * @param  float  $plagiarismscore Plagiarism score
     * @param  float  $aiscore         AI score
     * @param  int    $writingfeedbackissues Writing feedback issues
     * @param  bool   $ischeatingdetected    Is cheating detected
     * @param  string $errormessage    Error message
     * @return array  Warnings and success status
     */
    public static function update_report_webhook(
    $coursemoduleid,
    $moodleuserid,
    $identifier,
    $scanid,
    $status,
    $plagiarismscore = null,
    $aiscore = null,
    $writingfeedbackissues = null,
    $ischeatingdetected = false,
    $errormessage = null
    ) {
        // Validate parameters.
        $params = self::validate_parameters(self::update_report_webhook_parameters(), [
          'coursemoduleid' => $coursemoduleid,
          'moodleuserid' => $moodleuserid,
          'identifier' => $identifier,
          'scanid' => $scanid,
          'status' => $status,
          'plagiarismscore' => $plagiarismscore,
          'aiscore' => $aiscore,
          'writingfeedbackissues' => $writingfeedbackissues,
          'ischeatingdetected' => $ischeatingdetected,
          'errormessage' => $errormessage,
        ]);

        $result = \plagiarism_copyleaks_submissions::update_report(
          $params['coursemoduleid'],
          $params['moodleuserid'],
          $params['identifier'],
          $params['scanid'],
          $params['status'],
          $params['plagiarismscore'],
          $params['aiscore'],
          $params['writingfeedbackissues'],
          $params['ischeatingdetected'],
          $params['errormessage'],
        );
        if (!$result) {
            throw new plagiarism_copyleaks_webservice_exception('clreportupdatefailed');
        }
         return null;
    }

    /**
     * Describes the return value for Update_report_webhook
     * @return external_single_structure
     */
    public static function update_report_webhook_returns() {
        return null;
    }

    // EndRegion Update Report Webhook.

    // Region Disconnect Web Service Webhook.
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function update_api_connection_webhook_parameters() {
        return new external_function_parameters(
          [
            'pluginintegrationkey' => new external_value(PARAM_TEXT, 'Plugin integration key'),
            'isconnected' => new external_value(PARAM_BOOL, 'isconnected'),
          ]
        );
    }

    /**
     * Update failed scan to queued webhook
     * @param  string $coursemoduleid  Course module ID
     * @param  string $moodleuserid    Moodle user ID
     * @param  string $identifier      Identifier
     * @return array  Warnings and success status
     */
    public static function update_api_connection_webhook($pluginintegrationkey, $isconnected) {
        // Validate parameters.
        self::validate_parameters(
          self::update_api_connection_webhook_parameters(),
          ['pluginintegrationkey' => $pluginintegrationkey, 'isconnected' => $isconnected]
        );

        if (!plagiarism_copyleaks_pluginconfig::validate_admin_config_key($pluginintegrationkey)) {
            return ['successfullyUpdated' => false];
        }

        plagiarism_copyleaks_dbutils::upsert_config_api_connection_status($isconnected);

        return ['successfullyUpdated' => true];
    }

    /**
     * Describes the return value for update_api_connection_webhook
     * @return external_single_structure
     */
    public static function update_api_connection_webhook_returns() {
        return new external_single_structure(
          [
            'successfullyUpdated' => new external_value(PARAM_BOOL, 'Successfully Updated'),
          ]
        );
    }
    // EndRegion Disconnect Web Service Webhook.
}
