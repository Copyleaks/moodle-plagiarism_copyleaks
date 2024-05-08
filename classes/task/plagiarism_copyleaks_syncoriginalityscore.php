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
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');

/**
 * Copyleaks Plagiarism Plugin - Handle Resubmit Files
 */
class plagiarism_copyleaks_syncoriginalityscore extends \core\task\scheduled_task {
    /**
     * Get scheduler name, this will be shown to admins on schedulers dashboard.
     */
    public function get_name() {
        return get_string('clsyncoriginalityscore', 'plagiarism_copyleaks');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');

        $this->handle_scores_sync();
    }

    /**
     * Handle and change the score of resubmitted files.
     */
    private function handle_scores_sync() {
        global $DB;

        $copyleakscomms = new \plagiarism_copyleaks_comms();
        $canloadmoredata = true;

        if (!\plagiarism_copyleaks_comms::test_copyleaks_connection('scheduler_task')) {
            return;
        }

        while ($canloadmoredata) {

            $succeedids = [];
            $response = $copyleakscomms->sync_originality_report_scores();
            if (!is_object($response) || !isset($response->data) || count($response->data) == 0) {
                break;
            }

            $reports = $response->data;
            $canloadmoredata = $response->canLoadMore;
            $scanids = array_column($reports, 'scanId');

            $currentdbresults = [];

            /* Get all the scans from db with the ids of the 'response' old ids */
            $dbrecordset = $DB->get_recordset_list('plagiarism_copyleaks_files', 'externalid', $scanids);
            if (!$dbrecordset->valid()) {
                break;
            }

            /* Getting the result by the consition the all the external ids must contains in $oldids */
            foreach ($dbrecordset as $result) {
                $currentdbresults[] = $result;
            }

            $dbrecordset->close();

            if (count($currentdbresults) == 0) {
                break;
            }

            /* For each db result - Replace the new data */
            foreach ($currentdbresults as $currentresult) {
                if ($currentresult->externalid == null) {
                    continue;
                }

                /* Get the copyleaks db entity with the external id */
                foreach ($reports as $element) {
                    if ($element->scanId == $currentresult->externalid && $currentresult->statuscode == "success") {
                        $currentresult->similarityscore = isset($element->plagiarismScore) ? $element->plagiarismScore : null;
                        $currentresult->lastmodified = time();
                        if (!$DB->update_record('plagiarism_copyleaks_files',  $currentresult)) {
                            \plagiarism_copyleaks_logs::add(
                                "Sync plagiarism score failed for scan id: " . $element->scanId,
                                "UPDATE_RECORD_FAILED"
                            );
                        } else {
                            array_push($succeedids,  $element->scanId);
                        }
                        break;
                    }
                }
            }

            if (count($succeedids) > 0) {
                $copyleakscomms->delete_synces_report_ids($succeedids);
            }
        }
    }
}
