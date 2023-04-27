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

use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/enums/plagiarism_copyleaks_enums.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_httpclient.class.php');

/**
 * Copyleaks Plagiarism Plugin - Handle Resubmit Files
 */
class plagiarism_copyleaks_requestsqueue extends \core\task\scheduled_task {
    /**
     * Get scheduler name, this will be shown to admins on schedulers dashboard.
     */
    public function get_name() {
        return get_string('clsendrequestqueue', 'plagiarism_copyleaks');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        if (!\plagiarism_copyleaks_comms::test_copyleaks_connection('scheduler_task')) {
            return;
        }

        $this->handle_queued_requests();
    }

    /**
     * Handle and change the score of resubmitted files.
     */
    private function handle_queued_requests() {
        global $DB;

        $successrequestsids = array();
        $failedrequests = array();

        $canloadmoredata = true;
        $startqueryfrom = 0;

        while ($canloadmoredata) {
            /* Get all the rows, max 100, ascending by creation date first (let the old ones execute first),
            that have less then 5 attempts*/
            $queuedrequests = $DB->get_records_select(
                'plagiarism_copyleaks_request',
                'total_retry_attempts < ?',
                array(PLAGIARISM_COPYLEAKS_MAX_RETRY),
                'created_date ASC',
                '*',
                $startqueryfrom,
                PLAGIARISM_COPYLEAKS_CRON_MAX_DATA_LOOP
            );
            $canloadmoredata = count($queuedrequests) == PLAGIARISM_COPYLEAKS_CRON_MAX_DATA_LOOP;

            foreach ($queuedrequests as $item) {
                try {
                    // Send the request to the server.
                    $url = \plagiarism_copyleaks_comms::copyleaks_api_url() . $item->endpoint;
                    \plagiarism_copyleaks_http_client::execute($item->verb, $url, $item->require_auth, $item->data);
                    $successrequestsids[] = $item->id;
                } catch (\Exception $e) {
                    $item->fail_message = $e->getMessage();
                    $item->total_retry_attempts = $item->total_retry_attempts + 1;
                    $failedrequests[] = $item;
                }
            }
            $this->delete_queued_request($successrequestsids);
            $this->update_queued_request($failedrequests);
            $startqueryfrom = $startqueryfrom + PLAGIARISM_COPYLEAKS_CRON_MAX_DATA_LOOP;
        }
    }

    private function delete_queued_request(&$successrequestsids) {
        global $DB;
        if (count($successrequestsids) > 0) {
            if (!$DB->delete_records_list('plagiarism_copyleaks_request', 'id', $successrequestsids)) {
                \plagiarism_copyleaks_logs::add(
                    "failed to delete all success queued request",
                    "DELETE_RECORD_FAILED"
                );
            }
            $successrequestsids = array();
        }
    }

    private function update_queued_request(&$failedrequests) {
        global $DB;
        if (count($failedrequests) > 0) {
            foreach ($failedrequests as $request) {
                if (!$DB->update_record('plagiarism_copyleaks_request', $request)) {
                    \plagiarism_copyleaks_logs::add(
                        "failed to update database record for cmid: " .
                            $request->cmid . $request->verb . ", to " . $request->enpoint,
                        "UPDATE_RECORD_FAILED"
                    );
                }
            }
            $failedrequests = array();
        }
    }
}
