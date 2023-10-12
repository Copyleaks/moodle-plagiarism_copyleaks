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
global $CFG;
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/background_tasks/plagiarism_copyleaks_synccoursesdata.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/background_tasks/plagiarism_copyleaks_syncusersdata.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/enums/plagiarism_copyleaks_enums.php');

/**
 * Copyleaks Plagiarism Plugin - Handle Resubmit Files
 */
class plagiarism_copyleaks_background_task extends \core\task\scheduled_task {
    /**
     * Get scheduler name, this will be shown to admins on schedulers dashboard.
     */
    public function get_name() {
        return get_string('clbackgroundtask', 'plagiarism_copyleaks');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        if (!\plagiarism_copyleaks_comms::test_copyleaks_connection('scheduler_task')) {
            return;
        }

        $this->handle_task_once(\plagiarism_copyleaks_background_tasks::SYNC_COURSES_DATA);
        $this->handle_task_once(\plagiarism_copyleaks_background_tasks::SYNC_USERS_DATA);
    }

    /**
     * @param number $background_tasks - determine which task to run and log if necessary.
     */
    private function handle_task_once($background_task_type) {
        try {
            global $DB;
            if ($DB->get_record('plagiarism_copyleaks_bgtasks', array('task' => $background_task_type))) {

                $this->run_task_in_background($background_task_type);
                if (!($DB->delete_records('plagiarism_copyleaks_bgtasks', ['task' => $background_task_type]))) {
                    \plagiarism_copyleaks_logs::add(
                        "Faild to delete row in 'plagiarism_copyleaks_bgtasks'. (task: " . $background_task_type,
                        "DELETE_RECORD_FAILED"
                    );
                }
            }
        } catch (\Error $e) {
            \plagiarism_copyleaks_logs::add(
                "Faild to excute successfuly background task'. (task: " . $background_task_type,
                "RUN_BACKGROUND_TASK_FAILED"
            );
        }
    }

    /**
     * @param number $background_tasks - determine which task to run. 
     */
    private function run_task_in_background($background_task_type) {
        switch ($background_task_type) {
            case \plagiarism_copyleaks_background_tasks::SYNC_COURSES_DATA:
                \plagiarism_copyleaks_synccoursesdata::sync_courses_data();
                break;
            case \plagiarism_copyleaks_background_tasks::SYNC_USERS_DATA:
                \plagiarism_copyleaks_synusersdata::sync_users_data();
                break;
            default:
                break;
        }
    }
}
