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
 * Copyleaks Plagiarism Plugin - Recover scan results for submissions still pending after the delivery poll
 * @package   plagiarism_copyleaks
 * @copyright 2026 Copyleaks
 * @author    Shade Amasha <shadea@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_copyleaks\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');

/**
 * Copyleaks Plagiarism Plugin - Recover scan results for submissions still pending after the delivery poll
 */
class plagiarism_copyleaks_recoverreports extends \core\task\scheduled_task {
    /**
     * get scheduler name, this will be shown to admins on schedulers dashboard
     */
    public function get_name() {
        return get_string('clrecoverreports', 'plagiarism_copyleaks');
    }

    /**
     * execute the task
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
        require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_dbutils.class.php');
        require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_submissions.class.php');
        $this->recover_reports();
    }

    /**
     * Fetch results for rows still pending after the delivery poll should have applied them.
     *
     * Runs every 10 minutes and only asks about rows pending for at least
     * PLAGIARISM_COPYLEAKS_RECOVERY_PENDING_MINUTES - above the scan-duration tail, so a slow scan is not
     * mistaken for a lost result. The server answers this route from its database for any plugin version.
     */
    private function recover_reports() {
        global $DB;

        $canloadmoredata = true;
        $maxdataloadloops = PLAGIARISM_COPYLEAKS_CRON_MAX_DATA_LOOP;
        $lastid = 0;
        $consecutivefailures = 0;

        while ($canloadmoredata && (--$maxdataloadloops) > 0) {
            $submissionsinstances = [];

            $pendingsince = strtotime('- ' . PLAGIARISM_COPYLEAKS_RECOVERY_PENDING_MINUTES . ' minutes');

            $submissions = $DB->get_records_select(
                "plagiarism_copyleaks_files",
                "statuscode = ? AND lastmodified < ? AND (similarityscore IS NULL) AND id > ?",
                ['pending', $pendingsince, $lastid],
                'id ASC',
                '*',
                0,
                PLAGIARISM_COPYLEAKS_CRON_QUERY_LIMIT
            );

            $canloadmoredata = count($submissions) == PLAGIARISM_COPYLEAKS_CRON_QUERY_LIMIT;

            if (count($submissions) > 0) {
                $lastid = max(array_keys($submissions));
            }

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
                    $clsubmission->errormsg = 'course module (cm) was not found for this record';
                    if (!$DB->update_record('plagiarism_copyleaks_files', $clsubmission)) {
                        \plagiarism_copyleaks_logs::add(
                            "Update record failed (CM: " . $clsubmission->cm . ", User: " . $clsubmission->userid . ") - ",
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
                    $scaninstances = $copyleakscomms->recover_plagiarism_scans_instances($submissionsinstances);
                    if (is_array($scaninstances)) {
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
                                $clscaninstance->errorCode,
                            );
                        }
                    }
                    $consecutivefailures = 0;
                } catch (\Throwable $e) {
                    \plagiarism_copyleaks_logs::add(
                        "Recover reports failed - " . $e->getMessage(),
                        "API_ERROR"
                    );
                    $consecutivefailures = $consecutivefailures + 1;
                    if ($consecutivefailures >= 3) {
                        break;
                    }
                }
            }
        }

        return true;
    }
}
