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

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_submissions.class.php');

class plagiarism_copyleaks_webhooks extends external_api {

  /**
   * Returns description of method parameters
   * @return external_function_parameters
   */
  public static function update_report_webhook_parameters() {
    return new external_function_parameters(
      array(
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
      )
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
    global $DB;

    // Validate parameters
    $params = self::validate_parameters(self::update_report_webhook_parameters(), array(
      'coursemoduleid' => $coursemoduleid,
      'moodleuserid' => $moodleuserid,
      'identifier' => $identifier,
      'scanid' => $scanid,
      'status' => $status,
      'plagiarismscore' => $plagiarismscore,
      'aiscore' => $aiscore,
      'writingfeedbackissues' => $writingfeedbackissues,
      'ischeatingdetected' => $ischeatingdetected,
      'errormessage' => $errormessage
    ));

    $result =  \plagiarism_copyleaks_submissions::update_report(
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
    return array('success' => $result);
  }

  /**
   * Describes the return value for Update_report_webhook
   * @return external_single_structure
   */
  public static function update_report_webhook_returns() {
    return new external_single_structure(
      array(
        'success' => new external_value(PARAM_BOOL, 'Status of the update')
      )
    );
  }


  /**
   * Returns description of method parameters
   * @return external_function_parameters
   */
  public static function update_failed_scan_to_queued_webhook_parameters() {
    return new external_function_parameters(
      array(
        'coursemoduleid' => new external_value(PARAM_TEXT, 'Course module ID'),
        'moodleuserid' => new external_value(PARAM_TEXT, 'Moodle user ID'),
        'identifier' => new external_value(PARAM_TEXT, 'Identifier'),
      )
    );
  }

  /**
   * Update failed scan to queued webhook
   * @param  string $coursemoduleid  Course module ID
   * @param  string $moodleuserid    Moodle user ID
   * @param  string $identifier      Identifier

   * @return array  Warnings and success status
   */

  public static function update_failed_scan_to_queued_webhook(
    $coursemoduleid,
    $moodleuserid,
    $identifier
  ) {
    global $DB;
    // Validate parameters
    $params = self::validate_parameters(self::update_failed_scan_to_queued_webhook_parameters(), array(
      'coursemoduleid' => $coursemoduleid,
      'moodleuserid' => $moodleuserid,
      'identifier' => $identifier
    ));

    $fileid = $DB->get_field('plagiarism_copyleaks_files', 'id', array('cm' => $params['coursemoduleid'], 'userid' => $params['moodleuserid'], 'identifier' => $params['identifier']));
    plagiarism_copyleaks_submissions::change_failed_scan_to_queued($fileid);

    return array('success' => true);
  }

  /**
   * Describes the return value for Update_report_webhook
   * @return external_single_structure
   */
  public static function update_failed_scan_to_queued_webhook_returns() {
    return new external_single_structure(
      array(
        'success' => new external_value(PARAM_BOOL, 'Status of the update')
      )
    );
  }
}
