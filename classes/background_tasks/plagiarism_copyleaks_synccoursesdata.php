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
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_utils.class.php');

/**
 * Class plagiarism_copyleaks_synccoursesdata
 *
 * Handles the synchronization of course data with Copyleaks servers.
 * This class provides a static method to identify courses with Copyleaks enabled
 * and upserts their data (such as name and start date) to the Copyleaks platform.
 *
 * Key Features:
 * - Fetches course modules with Copyleaks plagiarism detection enabled.
 * - Ensures courses are upserted only once during the synchronization process.
 * - Limits the number of records processed in a single execution to prevent excessive load.
 * - Communicates with Copyleaks servers to update course data.
 */
class plagiarism_copyleaks_synccoursesdata {
    /**
     * Handle Courses upsert to Copyleaks
     */
    public static function sync_data() {
        global $DB;
        $startindex = 0;
        $canloadmore = true;
        $maxdataloadloops = PLAGIARISM_COPYLEAKS_CRON_MAX_DATA_LOOP;
        $maxitemsperloop = PLAGIARISM_COPYLEAKS_CRON_QUERY_LIMIT;
        $cl = new \plagiarism_copyleaks_comms();
        $alreadyupdatedcourses = [];

        while ($canloadmore && (--$maxdataloadloops) > 0) {

            // Get all course modules that activated copyleaks plugin.
            $coursemodules = $DB->get_records(
                'plagiarism_copyleaks_config',
                [
                    'name' => 'plagiarism_copyleaks_enable',
                    'value' => true,
                ],
                '',
                'cm',
                $startindex,
                $maxitemsperloop
            );

            $coursemodulesscount = count($coursemodules);

            // If there are no cm's break the loop.
            if ($coursemodulesscount == 0) {
                break;
            }

            // If the amount of the course module is lower then the max records amount - can't load more.
            if ($coursemodulesscount < $maxitemsperloop) {
                $canloadmore = false;
            }

            $startindex += $coursemodulesscount;

            $courseobjects = [];

            // For each cm we'll find its course and add it to the request array only if not already upserted.
            foreach ($coursemodules as $record) {
                $coursemodule = get_coursemodule_from_id('', $record->cm);

                if ($coursemodule) {
                    $course = get_course($coursemodule->course);

                    // Check if the course already upserted.
                    if ($alreadyupdatedcourses[$course->id]) {
                        continue;
                    } else {
                        $alreadyupdatedcourses[$course->id] = true;
                    }

                    if ($course) {
                        $coursestartdate = plagiarism_copyleaks_utils::get_course_start_date($coursemodule->course);
                        $courseobjects[] = [
                            "id" => $course->id,
                            "name" => $course->fullname,
                            "startdate" => $coursestartdate,
                        ];
                    }
                }
            }

            // Send the upsert request only if there is any courses.
            if (count($courseobjects) > 0) {
                $cl->upsert_courses(['courses' => $courseobjects]);
            }
        }
    }
}
