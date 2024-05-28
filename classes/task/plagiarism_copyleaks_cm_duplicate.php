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
require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/enums/plagiarism_copyleaks_enums.php');

/**
 * Copyleaks Plagiarism Plugin - Handle Course Modules duplication
 */
class plagiarism_copyleaks_cm_duplicate extends \core\task\scheduled_task
{
    /**
     * Get scheduler name, this will be shown to admins on schedulers dashboard.
     */
    public function get_name()
    {
        return "Copyleaks plagiarism plugin - Handle Course Modules duplication"; // need to create langestring 
    }

    /**
     * Execute the task.
     */
    public function execute()
    {
        global $CFG;

        $this->handle_cm_duplication();
    }

    /**
     * Handle .
     */
    private function handle_cm_duplication()
    {
        global $DB;

        $canloadmoredata = true;
        $maxdataloadloops = PLAGIARISM_COPYLEAKS_CRON_MAX_DATA_LOOP;

        while ($canloadmoredata && (--$maxdataloadloops) > 0) {
            $cmstocopy = $DB->get_records_select(
                "plagiarism_copyleaks_cm_copy",
                "status = ?",
                array(\plagiarism_copyleaks_cm_duplication_status::QUEUED),
                '',
                '*',
                0,
                PLAGIARISM_COPYLEAKS_CRON_QUERY_LIMIT
            );

            $canloadmoredata = count($cmstocopy) == PLAGIARISM_COPYLEAKS_CRON_QUERY_LIMIT;

            if (count($cmstocopy) == 0 || !\plagiarism_copyleaks_comms::test_copyleaks_connection('scheduler_task')) {
                break;
            }

            $coursemodules = array();
             // Add course modules to the request.
             foreach ($cmstocopy as $cmduplicationdata) {
                if ($cm = get_coursemodule_from_id('',$cmduplicationdata->new_cm_id)) {
                    $coursemodules[]=array(
                        'coursemoduleid' => $cmduplicationdata->new_cm_id,
                        'oldcoursemoduleid' => $cmduplicationdata->original_cm_id,
                        'courseid' => $cmduplicationdata->course_id,
                        'createddate' => $cm->added,
                      );
                } else {
                     // course module (cm) wasnt found for this record / log?
                }
            }

            if (count($coursemodules) > 0) {
                try {
                    $cl = new \plagiarism_copyleaks_comms();
                    $result = $cl->duplicate_course_modules(array('coursemodules' => $coursemodules));
                  
                } catch (\Exception $e) {
                  // log error?
                }
            }
        }
    }
}
