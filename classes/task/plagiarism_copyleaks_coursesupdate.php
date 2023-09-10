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
 * Copyleaks Plagiarism Plugin - Handle Resubmit Files
 * @package   plagiarism_copyleaks
 * @copyright 2022 Copyleaks
 * @author    Gil Cohen <gilc@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_copyleaks\task;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');



class plagiarism_copyleaks_coursesupdate extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('clupdatecourses', 'plagiarism_copyleaks');
    }


    public function execute() {

        $this->hendle_courses_upsert();
    }

    /**
     * Handle Courses upsert to Copyleaks
     */
    private function hendle_courses_upsert() {
        global $DB;
        $startindex = 0;
        $canloadmore = true;
        $currentTimestamp = time();
        $maxdataloadloops = PLAGIARISM_COPYLEAKS_CRON_MAX_DATA_LOOP;
        $maxitemsperloop = PLAGIARISM_COPYLEAKS_CRON_QUERY_LIMIT;
        $cl = new \plagiarism_copyleaks_comms();


        while ($canloadmore && (--$maxdataloadloops) > 0) {

            $courses = $DB->get_records_select(
                'course',
                " (enddate > ? OR enddate = ?) AND format = ?",
                array($currentTimestamp, 0, 'topics'),
                '',
                '*',
                $startindex,
                $maxitemsperloop
            );
            $coursescount = count($courses);

            if ($coursescount == 0) {
                return;
            }

            if ($coursescount < $maxitemsperloop) {
                $canloadmore = false;
            }

            $startindex += $coursescount;
            $courseobjects = array();

            // Loop through the courses and create objects for each course
            foreach ($courses as $course) {
                $mapcourse = [];
                // Create a new stdClass object

                // Set properties for the object
                $mapcourse['id'] = $course->id;
                $mapcourse['name'] = $course->fullname;

                // Add the course object to the array
                $courseobjects[] = $mapcourse;
            }
            $req['courses'] = $courseobjects;
            $cl->upsert_courses($req);
        }
    }
}
