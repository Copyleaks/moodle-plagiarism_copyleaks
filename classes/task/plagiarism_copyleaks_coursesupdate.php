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
        $maxdataloadloops = PLAGIARISM_COPYLEAKS_CRON_MAX_DATA_LOOP;
        $maxitemsperloop = PLAGIARISM_COPYLEAKS_CRON_QUERY_LIMIT;
        $cl = new \plagiarism_copyleaks_comms();
        $alreadyupdatedcourses = array();


        while ($canloadmore && (--$maxdataloadloops) > 0) {

            // Get all course modules that activated copyleaks plugin.
            $coursemodules =  $DB->get_records(
                'plagiarism_copyleaks_config',
                array(
                    'name' => 'plagiarism_copyleaks_enable',
                    'value' => true
                ),
                '',
                'cm',
                $startindex,
                $maxitemsperloop
            );

            $coursemodulesscount = count($coursemodules);

            // If there is no cm's break the loop.
            if ($coursemodulesscount == 0) {
                break;
            }

            // If the amount of the course module is lower then the max records amount - can't load more.
            if ($coursemodulesscount < $maxitemsperloop) {
                $canloadmore = false;
            }

            $startindex += $coursemodulesscount;

            $courseobjects = array();


            // For each cm we'll find its course and add it to the request array only if not already upserted.
            foreach ($coursemodules as $record) {
                $courseModule = get_coursemodule_from_id('', $record->cm);

                if ($courseModule) {
                    $course = get_course($courseModule->course);

                    // Check if the course already upserted.
                    if ($alreadyupdatedcourses[$course->id]) {
                        continue;
                    } else {
                        $alreadyupdatedcourses[$course->id] = true;
                    }

                    if ($course) {
                        $courseobjects[] = array(
                            "id" => $course->id,
                            "name" => $course->fullname
                        );
                    }
                }
            }

            // Send the upsert request only if there is any courses.
            if (count($courseobjects) > 0) {
                $cl->upsert_courses(array('courses' => $courseobjects));
            }
        }
    }
}
