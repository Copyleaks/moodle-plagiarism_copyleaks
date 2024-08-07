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
 * Copyleaks Plagiarism Plugin - Handle course moudles duplication
 * @package   plagiarism_copyleaks
 * @copyright 2024 Copyleaks
 * @author    Shade Amasha <shadea@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_copyleaks\task;

use DateTime;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/enums/plagiarism_copyleaks_enums.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_moduleconfig.class.php');

/**
 * Copyleaks Plagiarism Plugin - Handle Course Modules duplication
 */
class plagiarism_copyleaks_cm_duplicate extends \core\task\scheduled_task {
    /**
     * Get scheduler name, this will be shown to admins on schedulers dashboard.
     */
    public function get_name() {
        return get_string('clduplicatecoursemodules', 'plagiarism_copyleaks');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        $this->handle_cm_duplication();
    }

    /**
     * Handle the duplication of course modules.
     */
    private function handle_cm_duplication() {
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

            // A set to check for chained duplication requests.
            // This is for cases were we duplicated newly duplicated activity and it's not finished it's duplication proccess.
            $chainedduplicationchecker = array();
            foreach ($cmstocopy as $cmduplicationdata) {
                $chainedduplicationchecker[$cmduplicationdata->new_cm_id] = true;
            }

            // Add course modules to the request.
            $coursemodules = array();
            foreach ($cmstocopy as $cmduplicationdata) {
                if (\plagiarism_copyleaks_moduleconfig::is_course_module_request_queued($cmduplicationdata->original_cm_id)) {
                    continue;
                }

                // We won't send chained duplicated cm.
                if ($chainedduplicationchecker[$cmduplicationdata->original_cm_id]) {
                    // Make this task get the cm data again in the next loop in case $canloadmoredata is false.
                    $canloadmoredata = true;
                    continue;
                }

                if ($cm = get_coursemodule_from_id('', $cmduplicationdata->new_cm_id)) {
                    $datetime = new DateTime();
                    $coursemodules[] = array(
                        'coursemoduleid' => $cmduplicationdata->new_cm_id,
                        'oldcoursemoduleid' => $cmduplicationdata->original_cm_id,
                        'courseid' => $cmduplicationdata->course_id,
                        'createddate' => $datetime->setTimestamp($cm->added)->format('Y-m-d H:i:s'),
                    );
                } else {
                    \plagiarism_copyleaks_logs::add(
                        "Duplicate module failed (CM: " . $cm->id . ") - ",
                        "RECORD_NOT_FOUND"
                    );

                    if (!($DB->delete_records("plagiarism_copyleaks_cm_copy", ['id' => $cmduplicationdata->id]))) {
                        \plagiarism_copyleaks_logs::add(
                            "Faild to delete row in 'plagiarism_copyleaks_cm_copy'. (new cm id " . $cm->id,
                            "DELETE_RECORD_FAILED"
                        );
                    };
                }
            }

            if (count($coursemodules) > 0) {
                try {
                    $cl = new \plagiarism_copyleaks_comms();
                    $response = $cl->duplicate_course_modules(array('duplicatedmodules' => $coursemodules));

                    // Delete the successfully duplicated modules.
                    if (count($response->successded) > 0) {
                        if (!$DB->delete_records_list('plagiarism_copyleaks_cm_copy', 'new_cm_id', $response->successded)) {
                            \plagiarism_copyleaks_logs::add(
                                "Failed to delete successfully duplicated modules cmids: " . implode(',', $response->successded),
                                "DELETE_RECORD_FAILED"
                            );
                        }
                    }

                    // Update Failed Entites.
                    if (count($response->failed) > 0) {
                        $faileditems = [];

                        // Collect failed item IDs and messages in an associative array for quick lookup.
                        foreach ($response->failed as $faileditem) {
                            $faileditems[$faileditem->id] = $faileditem->message;
                        }

                        // Filter the records to update only those that have failed.
                        $failedcopycms = array_filter($cmstocopy, function ($module) use ($faileditems) {
                            return isset($faileditems[$module->new_cm_id]);
                        });

                        foreach ($failedcopycms as $faileditem) {
                            $faileditem->status = \plagiarism_copyleaks_cm_duplication_status::ERROR;
                            $faileditem->errormsg = $faileditems[$faileditem->new_cm_id];;
                            if (!$DB->update_record('plagiarism_copyleaks_cm_copy', $faileditem)) {
                                \plagiarism_copyleaks_logs::add(
                                    "Failed to update plagiarism_copyleaks_cm_copy: $faileditem->new_cm_id",
                                    "UPDATE_RECORD_FAILED"
                                );
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \plagiarism_copyleaks_logs::add(
                        "Modules Duplication Error - " . $e->getMessage(),
                        "API_ERROR"
                    );
                }
            }
        }
    }
}
