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
    public static function create($cm, $userid, $identifier, $submissiontype) {
        global $DB;

        $file = new stdClass();
        $file->cm = $cm->id;
        $file->userid = $userid;
        $file->identifier = $identifier;
        $file->statuscode = "queued";
        $file->similarityscore = null;
        $file->submissiontype = $submissiontype;

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
    public static function mark_error($fileid, $errormsg = null) {
        global $DB;

        $file = new stdClass();
        $file->id = $fileid;
        $file->statuscode = 'error';
        $file->lastmodified = time();

        if (!empty($errormsg)) {
            $file->errormsg = $errormsg;
        }

        if (!$DB->update_record('plagiarism_copyleaks_files', $file)) {
            \plagiarism_copyleaks_logs::add(
                "failed to update database record for fileid: " . $fileid,
                "UPDATE_RECORD_FAILED"
            );
        } else {
            \plagiarism_copyleaks_logs::add($errormsg . " (fileid: " . $fileid . ") - ", "MARK_ERROR_SUBMISSION");
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
            array('success', 'queued'),
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
                array(
                    $author,
                    $cmid,
                    $identifier
                ),
                $inparams
            )
        );

        return $instance;
    }
}
