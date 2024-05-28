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
 * Copyleaks Plagiarism Plugin
 * @package   plagiarism_copyleaks
 * @copyright 2023 Copyleaks
 * @author    Gil Cohen <gilc@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/enums/plagiarism_copyleaks_enums.php');


class plagiarism_copyleaks_duplicatecoursesdata
{
  /**
   * Handle Courses duplication to Copyleaks
   */
  public static function duplicate_data()
  {
    global $DB;
    $coursesstartindex = 0;
    $canloadmorecourses = true;
    $coursesmaxdataloadloops = 2;
    $maxitemsperloop = 10;
    $cl = new \plagiarism_copyleaks_comms();


    while ($canloadmorecourses && (--$coursesmaxdataloadloops) > 0) {
      // Get all pending duplication courses 
      $currentdate = strtotime("now"); 
      $courses = $DB->get_records_select(
        'plagiarism_copyleaks_bgtasks',
        'task = ? AND executiontime < ?',
        array(
           plagiarism_copyleaks_background_tasks::DUPLICATE_COURSES_DATA,
           $currentdate,
        ),
        '',
        'payload',
        $coursesstartindex,
        $maxitemsperloop
      );

      $coursescount = count($courses);

      // If there are no courses break the loop.
      if ($coursescount == 0) {
        break;
      }

      // If the amount of the courses is lower then the max records amount - can't load more.
      if ($coursescount < $maxitemsperloop) {
        $canloadmorecourses = false;
      }


      $mergedpayload = array();

      foreach ($courses as $course) {
        if (!empty($course->payload)) {
          $payload = json_decode($course->payload, true);
          if (json_last_error() === JSON_ERROR_NONE) {
            $mergedpayload = array_merge($mergedpayload, $payload);
          }
        }
      }

      $mergedpayloadcount = count($mergedpayload);
      // Calculate the number of iterations needed 
      $iterations = ceil($mergedpayloadcount / $maxitemsperloop);

      for ($i = 0; $i < $iterations; $i++) {
        $modulesstartindex = $i * $maxitemsperloop;
        $coursemodules = array_slice($mergedpayload, $modulesstartindex, $maxitemsperloop);

        if (count($coursemodules) > 0) {
          $cl->duplicate_course_module(array('coursemodules' => $coursemodules));
        }
      }
    }
  }
}
