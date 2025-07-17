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
 * Copyleaks Plagiarism Plugin - Handle Queued Files
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_copyleaks\task;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_dbutils.class.php');


/**
 * Copyleaks Plagiarism Plugin - Handle Queued Files
 */
class plagiarism_copyleaks_sendsubmissions extends \core\task\scheduled_task {
    /**
     * Get scheduler name, this will be shown to admins on schedulers dashboard.
     */
    public function get_name() {
        return get_string('clsendqueuedsubmissions', 'plagiarism_copyleaks');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_submissions.class.php');
        require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
        require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_utils.class.php');

        $this->send_queued_submissions();
    }

    /**
     * Check and send queue files to Copyleaks for plagiarism scan.
     */
    private function send_queued_submissions() {
        global $CFG, $DB;

        $canloadmoredata = true;
        $maxdataloadloops = PLAGIARISM_COPYLEAKS_CRON_MAX_DATA_LOOP;

        while ($canloadmoredata && (--$maxdataloadloops) > 0) {

            $currentdate = strtotime("now");
            $queuedsubmissions = $DB->get_records_select(
                "plagiarism_copyleaks_files",
                "statuscode = ? AND scheduledscandate < ?",
                ['queued', $currentdate],
                '',
                '*',
                0,
                PLAGIARISM_COPYLEAKS_CRON_QUERY_LIMIT
            );

            $canloadmoredata = count($queuedsubmissions) == PLAGIARISM_COPYLEAKS_CRON_QUERY_LIMIT;

            if (count($queuedsubmissions) == 0 || !\plagiarism_copyleaks_comms::test_copyleaks_connection('scheduler_task')) {
                return;
            }

            // Create a counter id for the batch.
            $copyleakscomms = new \plagiarism_copyleaks_comms();
            $response = $copyleakscomms->create_scan_batch_counter([
                "countervalue" => count($queuedsubmissions),
            ]);
            $counterid = $response->counterId;

            // Additional check to make sure counterid ( batchid ) is not null.
            if (isset($counterid)) {
                foreach ($queuedsubmissions as $submission) {
                    $submittedtextcontent = "";
                    $errormessage = "";
                    $errorcode = null;
                    // Check if submission type is supported.
                    $subtype = $submission->submissiontype;
                    if (!in_array(
                        $subtype,
                        PLAGIARISM_COPYLEAKS_SUPPORTED_SUBMISSION_TYPES
                    )) {
                        \plagiarism_copyleaks_submissions::mark_error(
                            $submission->id,
                            'Submission type is not supported.',
                            \plagiarism_copyleaks_errorcode::INTERNAL_PLUGIN_ERROR_NOT_RESCANNABLE
                        );
                        $copyleakscomms->handle_failed_to_submit($counterid);
                        continue;
                    }

                    if (\plagiarism_copyleaks_moduleconfig::is_course_module_request_queued_and_retryable($submission->cm)) {
                        $copyleakscomms->handle_failed_to_submit($counterid);
                        continue;
                    }

                    // Check if course module exists.
                    $coursemodule = get_coursemodule_from_id('', $submission->cm);
                    if (empty($coursemodule)) {
                        \plagiarism_copyleaks_submissions::handle_submission_error(
                            $submission,
                            $counterid,
                            "Course Module wasnt found for this record.",
                            \plagiarism_copyleaks_errorcode::INTERNAL_PLUGIN_ERROR_NOT_RESCANNABLE
                        );
                        continue;
                    }

                    $userid = $submission->userid;

                    // Set submitter if it was not set previously.
                    if (empty($submission->submitter)) {
                        $submission->submitter = $submission->userid;
                    }

                    // Mark as error if user id is 0 (user id should never be 0).
                    if (empty($submission->userid)) {
                        \plagiarism_copyleaks_submissions::handle_submission_error(
                            $submission,
                            $counterid,
                            'User Id should never be 0.',
                            \plagiarism_copyleaks_errorcode::INTERNAL_PLUGIN_ERROR_NOT_RESCANNABLE
                        );
                        continue;
                    }

                    // Handle submission data according to the submission type.
                    if ($submission->submissiontype == 'text_content') {
                        $moduledata = $DB->get_record($coursemodule->modname, ['id' => $coursemodule->instance]);

                        if ($coursemodule->modname == 'workshop') {
                            $workshopsubmission = $DB->get_record(
                                'workshop_submissions',
                                ['id' => $submission->itemid],
                                'content'
                            );
                            $submittedtextcontent = $workshopsubmission->content;
                        } else if ($coursemodule->modname == 'assign') {
                            $submissionref = $DB->get_record(
                                'assign_submission',
                                [
                                    'id' => $submission->itemid,
                                    'userid' => ($moduledata->teamsubmission) ? 0 : $submission->userid,
                                    'assignment' => $coursemodule->instance,
                                ],
                                'id'
                            );

                            $txtsubmissionref = $DB->get_record(
                                'assignsubmission_onlinetext',
                                ['submission' => $submissionref->id],
                                'onlinetext'
                            );
                            $submittedtextcontent = $txtsubmissionref->onlinetext;
                        } else {
                            $errormessage = 'Content not found for the submission.';
                            $errorcode = \plagiarism_copyleaks_errorcode::INTERNAL_PLUGIN_ERROR_NOT_RESCANNABLE;
                        }

                        $filename = 'online_text_'
                            . $userid . "_"
                            . $coursemodule->id . "_"
                            . $coursemodule->instance . '.txt';

                        $submittedtextcontent = html_to_text($submittedtextcontent);
                    } else if ($submission->submissiontype == 'forum_post') {
                        $forumpost = $DB->get_record_select(
                            'forum_posts',
                            " userid = ? AND id = ? ",
                            [$userid, $submission->itemid]
                        );
                        if ($forumpost) {
                            $filename = 'forumpost_'
                                . $userid . "_"
                                . $coursemodule->id . "_"
                                . $coursemodule->instance . "_"
                                . $submission->itemid . '.txt';

                            $submittedtextcontent = html_to_text(strip_tags($forumpost->message));
                        } else {
                            $errormessage = 'Content not found for the submission.';
                            $errorcode = \plagiarism_copyleaks_errorcode::INTERNAL_PLUGIN_ERROR_NOT_RESCANNABLE;
                        }
                    } else if ($submission->submissiontype == 'quiz_answer') {
                        try {
                            require_once($CFG->dirroot . '/mod/quiz/locallib.php');
                            $quizattempt = \quiz_attempt::create($submission->itemid);
                            foreach ($quizattempt->get_slots() as $slot) {
                                $questionattempt = $quizattempt->get_question_attempt($slot);
                                if ($submission->identifier == sha1($questionattempt->get_response_summary())) {
                                    $submittedtextcontent = $questionattempt->get_response_summary();
                                    break;
                                }
                            }
                        } catch (\Throwable $th) {
                            $errormessage = 'Connot find quiz attempt.';
                            $errorcode = \plagiarism_copyleaks_errorcode::INTERNAL_PLUGIN_ERROR_NOT_RESCANNABLE;
                        }

                        if (!empty($submittedtextcontent)) {
                            $submittedtextcontent = strip_tags($submittedtextcontent);
                            $filename = 'quizanswer_'
                                . $userid . "_"
                                . $coursemodule->id . "_"
                                . $coursemodule->instance . "_"
                                . $submission->itemid . '.txt';
                        } else {
                            $errormessage = 'Content not found for the submission.';
                            $errorcode = \plagiarism_copyleaks_errorcode::INTERNAL_PLUGIN_ERROR_NOT_RESCANNABLE;
                        }
                    } else {
                        // In case $submission->submissiontype == 'file'.
                        $filestorage = get_file_storage();
                        $fileref = $filestorage->get_file_by_hash($submission->identifier);

                        if (!$fileref) {
                            $errormessage = 'File/Content not found for the submission.';
                            $errorcode = \plagiarism_copyleaks_errorcode::INTERNAL_PLUGIN_ERROR_NOT_RESCANNABLE;
                        } else {
                            try {
                                $filename = $fileref->get_filename();
                                $submittedtextcontent = $fileref->get_content();
                            } catch (\Exception $e) {
                                $errormessage = 'File/Content not found for the submission.';
                                $errorcode = \plagiarism_copyleaks_errorcode::INTERNAL_PLUGIN_ERROR_NOT_RESCANNABLE;
                            }
                        }
                    }

                    // If $errormessage is not empty, then there was an error.
                    if (isset($errormessage) && $errormessage != "") {
                        \plagiarism_copyleaks_submissions::handle_submission_error(
                            $submission,
                            $counterid,
                            $errormessage,
                            $errorcode
                        );
                        continue;
                    }

                    try {
                        // Read the submited work into a temp file for submitting.
                        $tempfilepath = $this->create_copyleaks_tempfile($coursemodule->id, $filename);
                    } catch (\Exception $e) {
                        \plagiarism_copyleaks_submissions::handle_submission_error(
                            $submission,
                            $counterid,
                            "Fail to create a tempfile.",
                            \plagiarism_copyleaks_errorcode::INTERNAL_PLUGIN_ERROR_NOT_RESCANNABLE
                        );
                        continue;
                    }

                    $fileref = fopen($tempfilepath, "w");
                    fwrite($fileref, $submittedtextcontent);
                    fclose($fileref);

                    try {
                        $copyleakscomms->submit_for_plagiarism_scan(
                            $tempfilepath,
                            $filename,
                            $coursemodule->id,
                            $userid,
                            $submission->identifier,
                            $submission->submissiontype,
                            $counterid,
                            $submission->externalid
                        );
                        \plagiarism_copyleaks_submissions::mark_pending($submission->id);
                    } catch (\Exception $e) {
                        $errorcode = $e->getCode();
                        $error = get_string(
                            'clapisubmissionerror',
                            'plagiarism_copyleaks'
                        ) . ' ' . $e->getMessage();
                        if ($errorcode < 500 && $errorcode != 429) {
                            \plagiarism_copyleaks_submissions::mark_error(
                                $submission->id,
                                $error,
                                \plagiarism_copyleaks_errorcode::INTERNAL_SERVER_ERROR
                            );
                        } else {
                            \plagiarism_copyleaks_logs::add($error, 'API_ERROR_RETRY_WILL_BE_DONE');
                        }
                        $copyleakscomms->handle_failed_to_submit($counterid);
                    }

                    // After finished the scan proccess, delete the temp file (if it exists).
                    if (!is_null($tempfilepath)) {
                        unlink($tempfilepath);
                    }
                }
            }
        }
    }

    /**
     * Generate a temp file for the submission.
     * @param string $cmid
     * @param string $name
     * @return string temp file path
     */
    private function create_copyleaks_tempfile($cmid, $name) {
        // Create Copyleaks directory under tempdir for submission usage.
        $tempdirref = make_temp_directory('plagiarism_copyleaks');

        $parts = explode('.', $name);
        $extension = '';
        if (count($parts) > 1) {
            $extension = '.' . array_pop($parts);
        }

        $filestring = [$parts[0], $cmid];
        $filename = implode('_', $filestring);
        $filename = str_replace(' ', '_', $filename);
        $filename = clean_param(strip_tags($filename), PARAM_FILE);

        $maxstrlength = PLAGIARISM_COPYLEAKS_MAX_FILENAME_LENGTH -
            mb_strlen(
                $tempdirref . DIRECTORY_SEPARATOR,
                'UTF-8'
            );

        $extensionlength = mb_strlen(
            '_' . mt_getrandmax()
                . $extension,
            'UTF-8'
        );

        if ($extensionlength > $maxstrlength) {
            $extensionlength = $maxstrlength;
        }

        // Make the filename smaller if needed.
        $maxstrlength -= $extensionlength;
        $name = mb_substr($name, 0, $maxstrlength, 'UTF-8');

        // Clear invalid characters.
        $name = clean_param(
            $name
                . mb_substr(
                    '_' . mt_rand() . $extension,
                    0,
                    $extensionlength,
                    'UTF-8'
                ),
            PARAM_FILE
        );

        $tries = 0;
        do {
            if ($tries == 10) {
                throw new \invalid_dataroot_permissions("Copyleaks plagiarism plugin temporary file cannot be created.");
            }
            $tries++;

            $file = $tempdirref . DIRECTORY_SEPARATOR . $name;
        } while (!touch($file));

        return $file;
    }
}
