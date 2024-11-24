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
 * submissions helpers methods
 *
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');

/**
 * submissions helpers methods
 */
class plagiarism_copyleaks_submissions {
    /**
     * Initialise submission values
     * @param stdClass $cm
     * @param string $userid
     * @param string $identifier
     * @param string $submissiontype
     **/
    public static function create($cm, $userid, $identifier, $submissiontype, $hashedcontent = null) {
        global $DB;

        $file = new stdClass();
        $file->cm = $cm->id;
        $file->userid = $userid;
        $file->identifier = $identifier;
        $file->statuscode = "queued";
        $file->similarityscore = null;
        $file->submissiontype = $submissiontype;
        $file->hashedcontent = $hashedcontent;

        if (!$fileid = $DB->insert_record('plagiarism_copyleaks_files', $file)) {
            \plagiarism_copyleaks_logs::add(
                "failed to insert database record for cmid: " . $cm->id . ", userid: " . $userid,
                "INSERT_RECORD_FAILED"
            );
            $fileid = 0;
        }

        return $fileid;
    }

    /**
     * Save the submission data to the files table.
     * @param stdClass $cm
     * @param string $userid
     * @param string $fileid
     * @param string $identifier
     * @param string $statuscode
     * @param string $clsubmissionid
     * @param string $submitter
     * @param string $itemid
     * @param string $submissiontype
     * @param int $scheduledscandate
     * @param string $errormsg
     */
    public static function save(
        $cm,
        $userid,
        $fileid,
        $identifier,
        $statuscode,
        $clsubmissionid,
        $submitter,
        $itemid,
        $submissiontype,
        $scheduledscandate,
        $errormsg = null
    ) {
        global $DB;

        $file = new stdClass();
        if ($fileid != 0) {
            $file->id = $fileid;
        }
        $file->cm = $cm->id;
        $file->userid = $userid;
        $file->identifier = $identifier;
        $file->statuscode = $statuscode;
        $file->similarityscore = null;
        $file->externalid = $clsubmissionid;
        $file->errormsg = $errormsg;
        $file->lastmodified = time();
        $file->submissiontype = $submissiontype;
        $file->itemid = $itemid;
        $file->submitter = $submitter;
        $file->scheduledscandate = $scheduledscandate;

        if ($fileid != 0) {
            if (!$DB->update_record('plagiarism_copyleaks_files', $file)) {
                \plagiarism_copyleaks_logs::add(
                    "failed to update database record for cmid: "
                        . $cm->id . ", userid: " . $userid,
                    "UPDATE_RECORD_FAILED"
                );
            }
        } else {
            if (!$DB->insert_record('plagiarism_copyleaks_files', $file)) {
                \plagiarism_copyleaks_logs::add(
                    "failed to insert database record for cmid: "
                        . $cm->id . ", userid: " . $userid,
                    "INSERT_RECORD_FAILED"
                );
            }
        }

        return true;
    }

    /**
     * Update an errored submission in the files table.
     * @param string $fileid submission id
     * @param string $errormsg error message (optional)
     */
    public static function mark_error($fileid, $errormsg = null, $failedafterretry = false) {
        global $DB;

        $file = new stdClass();
        $file->id = $fileid;
        $file->statuscode = 'error';
        $file->lastmodified = time();
        $errorlog = "MARK_ERROR_SUBMISSION";

        if ($failedafterretry) {
            $file->retrycnt = 0;
            $errorlog = "SUBMISSION_MAX_RETRY_FAILED";
        }

        if (!empty($errormsg)) {
            $file->errormsg = $errormsg;
        }

        if (!$DB->update_record('plagiarism_copyleaks_files', $file)) {
            \plagiarism_copyleaks_logs::add(
                "failed to update database record for fileid: " . $fileid,
                "UPDATE_RECORD_FAILED"
            );
        } else {
            \plagiarism_copyleaks_logs::add($errormsg . " (fileid: " . $fileid . ") - ", $errorlog);
        }

        return true;
    }

    /**
     * Update pending submission in the files table.
     * @param string $fileid submission id
     */
    public static function mark_pending($fileid) {
        global $DB;

        $file = new stdClass();
        $file->id = $fileid;
        $file->statuscode = 'pending';
        $file->errormsg = '';
        $file->lastmodified = time();

        if (!$DB->update_record('plagiarism_copyleaks_files', $file)) {
            \plagiarism_copyleaks_logs::add(
                "failed to update database record for fileid: " . $fileid,
                "UPDATE_RECORD_FAILED"
            );
        }

        return true;
    }

    /**
     * check if submission already submitted, to avoid resubmitting them to Copyleaks.
     * @param string $cmid
     * @param string $author
     * @param string $identifier
     * @return array $instance
     */
    public static function successful_submission_instances($cmid, $author, $identifier) {
        global $DB, $CFG;

        list(
            $insql,
            $inparams
        ) = $DB->get_in_or_equal(
            ['success', 'queued'],
            SQL_PARAMS_QM,
            'param',
            false
        );

        $typefield = ($CFG->dbtype == "oci") ?
            " to_char(statuscode) " : " statuscode ";

        $instance = $DB->get_records_select(
            "plagiarism_copyleaks_files",
            " userid = ? AND cm = ? AND identifier = ? AND " . $typefield . " " . $insql,
            array_merge(
                [
                    $author,
                    $cmid,
                    $identifier,
                ],
                $inparams
            )
        );

        return $instance;
    }

    /**
     * Update exisited file to pending to trigger resubmission to Copyleaks
     * @param string $fileid submission id
     */
    public static function change_failed_scan_to_queued($fileid) {
        global $DB;
        $record = $DB->get_record(
            'plagiarism_copyleaks_files',
            [
                'id' => $fileid,
            ]
        );
        $record->statuscode = "queued";
        $record->errormsg = null;
        if ($record) {
            if (!$DB->update_record('plagiarism_copyleaks_files', $record)) {
                \plagiarism_copyleaks_logs::add(
                    "failed to update to resubmit, fileid: $fileid",
                    "UPDATE_RECORD_FAILED"
                );
            }
        }
    }

    /**
     * Handle submission error.
     * @param object $submission - will update the submission reference.
     * @param string $errormessage
     */
    public static function handle_submission_error(&$submission, $errormessage = '') {
        global $DB;
        if (isset($submission->retrycnt) && $submission->retrycnt > PLAGIARISM_COPYLEAKS_MAX_AUTO_RETRY) {
            self::mark_error($submission->id, $errormessage, true);
            return;
        }

        if (!isset($submission->retrycnt)) {
            $submission->retrycnt = 0;
        }

        $submission->retrycnt += 1;
        $submission->scheduledscandate = strtotime('+ 5 minutes');
        $submission->statuscode = 'queued';

        if (!$DB->update_record('plagiarism_copyleaks_files', $submission)) {
            $logtxt = empty($submission->errormsg) ? 'retry count' : 'error';
            \plagiarism_copyleaks_logs::add(
                "failed to update submission $logtxt, fileid: " . $submission->id,
                "UPDATE_RECORD_FAILED"
            );
        }
    }


    /**
     * Update report
     * @param  string $coursemoduleid  Course module ID
     * @param  string $moodleuserid    Moodle user ID
     * @param  string $identifier      Identifier
     * @param  string $scanid          Scan ID
     * @param  int    $status          Report scan status
     * @param  float  $plagiarismscore Plagiarism score
     * @param  float  $aiscore         AI score
     * @param  int    $writingfeedbackissues Writing feedback issues
     * @param  bool   $ischeatingdetected    Is cheating detected
     * @param  string $errormessage    Error message
     * @return bool  true if success, false if failed
     */
    public static function update_report(
        $coursemoduleid,
        $moodleuserid,
        $identifier,
        $scanid,
        $status,
        $plagiarismscore,
        $aiscore,
        $writingfeedbackissues,
        $ischeatingdetected,
        $errormessage

    ) {
        global $DB;
        $submission = $DB->get_record(
            'plagiarism_copyleaks_files',
            [
                'cm' => $coursemoduleid,
                'userid' => $moodleuserid,
                'identifier' => $identifier,
            ]
        );

        if (isset($submission) && $submission) {
            $submission->externalid = $scanid;
            if ($status == 1) {
                $submission->statuscode = 'success';
                $submission->similarityscore = isset($plagiarismscore) ?
                    round($plagiarismscore, 1) : null;
                $submission->aiscore = isset($aiscore) ?
                    round($aiscore, 1) : null;
                $submission->writingfeedbackissues = isset($writingfeedbackissues) ?
                    $writingfeedbackissues : null;
                $submission->ischeatingdetected = $ischeatingdetected;
                if (!$DB->update_record('plagiarism_copyleaks_files', $submission)) {
                    \plagiarism_copyleaks_logs::add(
                        "Update record failed (CM: " . $coursemoduleid . ", User: "
                            . $moodleuserid . ") - ",
                        "UPDATE_RECORD_FAILED"
                    );
                    return false;
                }
            } else if ($status == 2) {
                $submission->statuscode = 'error';
                $submission->errormsg = $errormessage;
                if (!$DB->update_record('plagiarism_copyleaks_files', $submission)) {
                    \plagiarism_copyleaks_logs::add(
                        "Update record failed (CM: " . $coursemoduleid . ", User: "
                            . $moodleuserid . ") - ",
                        "UPDATE_RECORD_FAILED"
                    );
                    return false;
                }
            } else if ($status == 3) {
                self::mark_pending($submission->id);
            }
        } else {
            \plagiarism_copyleaks_logs::add(
                "Submission not found for Copyleaks API scan instances with the identifier: "
                    . $identifier,
                "SUBMISSION_NOT_FOUND"
            );

            return false;
        }

        return true;
    }
}
