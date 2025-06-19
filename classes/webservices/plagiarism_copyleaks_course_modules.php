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
 * Copyleaks course groups
 * @package   plagiarism_copyleaks
 * @author    Shade Amasha <shadea@copyleaks.com>
 * @copyright 2021 Copyleaks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/lib/grouplib.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_webserviceexception.class.php');

/**
 * plagiarism_copyleaks_course_groups external API class
 */
class plagiarism_copyleaks_course_modules extends external_api {

  /**
   * Parameters for get_student_report_access_by_cmids
   * @return external_function_parameters
   */
  public static function get_student_report_access_by_cmids_parameters() {

    return new external_function_parameters(
      [
        'cmids' => new external_multiple_structure(
          new external_single_structure(
            [
              'id' => new external_value(PARAM_INT, 'The ID of the course module'),
            ]
          )
        ),
      ]
    );
  }


  /**
   * Get student plagiarism report access flags by course module IDs
   * @param array $cmids
   * @return array
   */
public static function get_student_report_access_by_cmids($cmids) {
    global $DB;

    // Validate parameters.
    $params = self::validate_parameters(self::get_student_report_access_by_cmids_parameters(), ['cmids' => $cmids]);

    $reportsaccesspermission = [];

    foreach ($params['cmids'] as $cm) {
        $cmid = $cm['id'];

        $settings = $DB->get_records_menu(
            'plagiarism_copyleaks_config',
            ['cm' => $cmid],
            '',
            'name,value'
        );

        $allowaccess =
            isset($settings['plagiarism_copyleaks_allowstudentaccess']) &&
            $settings['plagiarism_copyleaks_allowstudentaccess'] === '1';

      $reportsaccesspermission[] = [
            'courseModuleId' => $cmid,
            'allowStudentReportAccess' => $allowaccess
        ];
    }

    return [
      'reportsAccessPermission' => $reportsaccesspermission
    ];
}


  /**
   * Describes the return value for get_student_report_access_by_cmids
   * @return external_single_structure
   */
  public static function get_student_report_access_by_cmids_returns() {
    return new external_single_structure([
      'reportsAccessPermission' => new external_multiple_structure(
        new external_single_structure([
          'courseModuleId' => new external_value(PARAM_INT, 'Course Module ID'),
          'allowStudentReportAccess' => new external_value(PARAM_BOOL, 'True if student access is allowed'),
        ]),
        'Access settings for each course module'
      ),
    ]);
  }
}
