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
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_dbutils.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');




/**
 * Copyleaks Plagiarism Plugin - Handle Resubmit Files
 */
class plagiarism_copyleaks_synceulausers extends \core\task\scheduled_task {
    /**
     * Get scheduler name, this will be shown to admins on schedulers dashboard.
     */
    public function get_name() {
        return get_string('clupserteulausers', 'plagiarism_copyleaks');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        if (!\plagiarism_copyleaks_comms::test_copyleaks_connection('scheduler_task')) {
            return;
        }

        $this->handle_synced_users();
    }

    /**
     * Handle and change the score of resubmitted files.
     */
    private function handle_synced_users() {
        global $DB;

        $canloadmoredata = true;
        $limitfrom = 0;

        $condition = array('is_synced' => false);
        $cl = new \plagiarism_copyleaks_comms();



        while ($canloadmoredata) {
            try {
                $eulausers = $DB->get_records('plagiarism_copyleaks_eula', $condition, '', '*', $limitfrom, PLAGIARISM_COPYLEAKS_CRON_MAX_DATA_LOOP);
                if (count($eulausers) == 0) {
                    break;
                }
                $canloadmoredata = count($eulausers) == PLAGIARISM_COPYLEAKS_CRON_MAX_DATA_LOOP;

                $model = $this->arrange_request_model($eulausers);
                $cl->upsert_synced_eula($model);
            } catch (\Exception $e) {
                \plagiarism_copyleaks_logs::add(
                    "Update eula users tasks failed",
                    "UPDATE_RECORD_FAILED"
                );
            }

            foreach ($eulausers as $eulauser) {
                $eulauser->is_synced = true;
                if (!$DB->update_record('plagiarism_copyleaks_eula', $eulauser)) {
                    \plagiarism_copyleaks_logs::add(
                        "Failed to update synced user: $eulauser->user_id",
                        "UPDATE_RECORD_FAILED"
                    );
                }
            }

            $limitfrom = $limitfrom + PLAGIARISM_COPYLEAKS_CRON_MAX_DATA_LOOP;
        }
    }

    /**
     * @param $data database data of users id with version to update 
     */
    private function arrange_request_model($eulausers) {
        $data = array();
        foreach ($eulausers as $eulauser) {
            if (isset($eulauser->date) && isset($eulauser->ci_user_id)) {
                $data[] = array(
                    'userid' => $eulauser->ci_user_id,
                    'version' => $eulauser->version,
                    'date' => $eulauser->date
                );
            }
        }
        return array('eulaUsersData' => $data);
    }
}
