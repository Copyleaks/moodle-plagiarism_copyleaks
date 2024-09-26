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
 * Copyleaks Plagiarism Plugin - Handle plagiairsm check similarity score update
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_copyleaks\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');

/**
 * Copyleaks Plagiarism Plugin - Handle plagiairsm check similarity score update
 */
class plagiarism_copyleaks_updatereports extends \core\task\scheduled_task {
    /**
     * get scheduler name, this will be shown to admins on schedulers dashboard
     */
    public function get_name() {
        return get_string('clupdatereportscores', 'plagiarism_copyleaks');
    }

    /**
     * execute the task
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
        require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_dbutils.class.php');
        require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_submissions.class.php');
        // Execute only if the API is not connected.
        if (!\plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            $this->update_reports();
        }
    }

    /**
     * sync files with Copyleaks API
     */
    private function update_reports() {
        global $DB;

        $canloadmoredata = true;
        $maxdataloadloops = PLAGIARISM_COPYLEAKS_CRON_MAX_DATA_LOOP;

        while ($canloadmoredata && (--$maxdataloadloops) > 0) {
            $submissionsinstances = array();

            $expectedfinishtime = strtotime('- 1 minutes');

            $submissions = $DB->get_records_select(
                "plagiarism_copyleaks_files",
                "statuscode = ? AND lastmodified < ? AND (similarityscore IS NULL)",
                array('pending', $expectedfinishtime),
                '',
                '*',
                0,
                PLAGIARISM_COPYLEAKS_CRON_QUERY_LIMIT
            );

            $canloadmoredata = count($submissions) == PLAGIARISM_COPYLEAKS_CRON_QUERY_LIMIT;

            // Add submission ids to the request.
            foreach ($submissions as $clsubmission) {
                // Only add the submission to the request if the module still exists.
                if ($cm = get_coursemodule_from_id('', $clsubmission->cm)) {
                    $submissioninstance = new \stdClass();
                    $submissioninstance->courseModuleId = $clsubmission->cm;
                    $submissioninstance->moodleUserId = $clsubmission->userid;
                    $submissioninstance->identitfier = $clsubmission->identifier;
                    array_push($submissionsinstances, $submissioninstance);
                } else {
                    $clsubmission->statuscode = 'error';
                    $clsubmission->errormsg = 'course module (cm) wasnt found for this record';
                    if (!$DB->update_record('plagiarism_copyleaks_files', $clsubmission)) {
                        \plagiarism_copyleaks_logs::add(
                            "Update record failed (CM: " . $cm->id . ", User: " . $clsubmission->userid . ") - ",
                            "UPDATE_RECORD_FAILED"
                        );
                    }
                }
            }

            if (count($submissionsinstances) > 0) {
                try {

                    if (!\plagiarism_copyleaks_comms::test_copyleaks_connection('scheduler_task')) {
                        return;
                    }
                    $copyleakscomms = new \plagiarism_copyleaks_comms();
                    $scaninstances = $copyleakscomms->get_plagiarism_scans_instances($submissionsinstances);
                    if (count($scaninstances) > 0) {
                        foreach ($scaninstances as $clscaninstance) {

                            \plagiarism_copyleaks_submissions::update_report(
                                $clscaninstance->courseModuleId,
                                $clscaninstance->moodleUserId,
                                $clscaninstance->identitfier,
                                $clscaninstance->scanId,
                                $clscaninstance->status,
                                $clscaninstance->plagiarismScore,
                                $clscaninstance->aiScore,
                                $clscaninstance->writingFeedbackIssues,
                                $clscaninstance->isCheatingDetected,
                                $clscaninstance->errorMessage,
                            );
                              
                        }
                    }
                } catch (\Exception $e) {
                    \plagiarism_copyleaks_logs::add(
                        "Update reports failed - " . $e->getMessage(),
                        "API_ERROR"
                    );
                }
            }
        }

        return true;
    }
}
