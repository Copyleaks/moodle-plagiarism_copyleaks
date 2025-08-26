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
 * handle observer events
 *
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_pluginconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_moduleconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_submissions.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');

require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/enums/plagiarism_copyleaks_enums.php');

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');
/**
 * handle observer events
 */
class plagiarism_copyleaks_eventshandler {
    /** @var string moodle event type */
    public $eventtype;
    /** @var string module name*/
    public $modulename;

    /**
     * class constructor
     * @param string $eventtype
     * @param string $modulename
     */
    public function __construct(string $eventtype, string $modulename) {
        $this->eventtype = $eventtype;
        $this->modulename = $modulename;
    }

    /**
     * Handle Submissions event
     * @param object $data
     * @return bool result
     */
    public function handle_submissions($data) {
        global $DB;
        $result = true;

        // Get course module.
        $coursemodule = $this->get_coursemodule($data);

        // Stop event if the course module is not found.
        if (!$coursemodule) {
            return true;
        }

        // Check if module is enabled for this event.
        if (!plagiarism_copyleaks_moduleconfig::is_module_enabled($coursemodule->modname, $coursemodule->id)) {
            return true;
        }

        // Check the supported EULA acceptance module.
        if (plagiarism_copyleaks_moduleconfig::is_allowed_eula_acceptance($coursemodule->modname)) {
            plagiarism_copyleaks_dbutils::upsert_eula_by_user_id($data["userid"]);
        }

        // Get course module ref.
        $cmdata = $DB->get_record(
            $coursemodule->modname,
            ['id' => $coursemodule->instance]
        );

        // Support draft submission only for assignment module.
        if ($coursemodule->modname != 'assign') {
            $cmdata->submissiondrafts = 0;
        }

        // Initialise module config.
        $clmoduleconfig = plagiarism_copyleaks_moduleconfig::get_module_config($coursemodule->id);

        if ($coursemodule->modname == 'assign') {
            // Default to 0 => Submit file when first uploaded.
            $clmoduleconfig["plagiarism_copyleaks_draftsubmit"] = ($cmdata->submissiondrafts &&
                isset($clmoduleconfig["plagiarism_copyleaks_draftsubmit"])) ?
                $clmoduleconfig["plagiarism_copyleaks_draftsubmit"] : 0;
        }

        // Submit files only when students click the submit button (if enabled).
        if (
            ($this->eventtype == 'file_uploaded' || $this->eventtype == 'content_uploaded') &&
            $cmdata->submissiondrafts &&
            $clmoduleconfig["plagiarism_copyleaks_draftsubmit"] == 1
        ) {
            return true;
        }

        $submitteruserid = $data['userid'];
        $authoruserid = $this->get_author_id($data, $coursemodule);

        // Handle submitted files and content in 'Submit files only when students click the submit button' mode.
        if ($this->eventtype == "assessable_submitted" && $this->modulename == 'assign') {

            $submissionref = $DB->get_record(
                'assign_submission',
                ['id' => $data['objectid']],
                'id'
            );

            // Handle uploaded Files.
            $data['other']['pathnamehashes'] = [];
            if ($uploadedfiles = $DB->get_records(
                'files',
                [
                    'component' => 'assignsubmission_file',
                    'itemid' => $submissionref->id,
                    'userid' => ($cmdata->submissiondrafts &&
                        $this->is_instructor_submit($data)) ? $data['userid'] : $authoruserid,
                ]
            )) {
                foreach ($uploadedfiles as $uploadedfile) {
                    $data['other']['pathnamehashes'][] = $uploadedfile->pathnamehash;
                }
            }

            // Handle text content.
            if ($txtsubmissionref = $DB->get_record(
                'assignsubmission_onlinetext',
                [
                    'submission' => $submissionref->id,
                ],
                'onlinetext'
            )) {
                $data['other']['content'] = $txtsubmissionref->onlinetext;
            }
        }

        // Queue quizzes to be submitted to Copyleaks later by submission task.
        if ($this->eventtype == 'quiz_submitted') {
            $result = $this->queue_quizzes($data, $coursemodule, $authoruserid, $submitteruserid, $cmdata);
        }

        // Queue files to be submitted to Copyleaks later by submission task.
        if (!empty($data['other']['pathnamehashes'])) {
            $result = $this->queue_files($data, $coursemodule, $authoruserid, $submitteruserid, $cmdata);
        }

        // Queue text content to be submitted to Copyleaks later by submission task.
        if (
            !empty($data['other']['content']) &&
            in_array(
                $this->eventtype,
                [
                    "content_uploaded",
                    "assessable_submitted",
                ]
            )
        ) {
            $result = $this->queue_text_content($data, $coursemodule, $authoruserid, $submitteruserid, $cmdata);
        }

        return $result;
    }

    /**
     * get course module.
     * @param object $data
     * @return object course module (cm)
     */
    private function get_coursemodule($data) {
        if ($this->modulename == 'quiz') {
            // During quiz submission, we do have the quiz id.
            return get_coursemodule_from_instance($this->modulename, $data['other']['quizid']);
        } else {
            return get_coursemodule_from_id($this->modulename, $data['contextinstanceid']);
        }
    }

    /**
     * get author id depending on event.
     * @param object $data event data
     * @param object $coursemodule cousre module (cm)
     * @return string author id
     */
    private function get_author_id($data, $coursemodule) {
        global $DB;

        $authoruserid = (empty($data['relateduserid'])) ? $data['userid'] : $data['relateduserid'];
        /*
          If instructor is submitting on behalf of a student relateduserid will be null,
          Set the author to the student.
        */
        if (($coursemodule->modname == 'assign') && (empty($data['relateduserid'])) &&
            has_capability(
                'mod/assign:editothersubmission',
                context_module::instance($coursemodule->id),
                $data['userid']
            )
        ) {

            $submissionref = $DB->get_record(
                'assign_submission',
                ['id' => $data['objectid']],
                'id, groupid'
            );

            if (!empty($submissionref->groupid)) {
                // Get first group author.
                $groupmembers = groups_get_members($submissionref->groupid, "u.id");
                foreach ($groupmembers as $member) {
                    $authoruserid = $member->id;
                }
            }
        }

        return $authoruserid;
    }

    /**
     * queue text content to be send to Copyleaks later by scan submission task.
     * @param object $data
     * @param object $coursemodule
     * @param string $authoruserid
     * @param string $submitteruserid
     * @param object $cmdata
     */
    private function queue_text_content($data, $coursemodule, $authoruserid, $submitteruserid, $cmdata) {
        global $DB, $CFG;

        if ($coursemodule->modname == 'workshop' && !isset($data['other']['content'])) {
            $workshopsubmissions = $DB->get_record(
                'workshop_submissions',
                ['id' => $data['objectid']]
            );
            $data['other']['content'] = $workshopsubmissions->content;
        }

        $contentidentifier = sha1($data['other']['content']);

        // Check if the text content has already been submitted.
        $files = plagiarism_copyleaks_submissions::successful_submission_instances(
            $coursemodule->id,
            $authoruserid,
            $contentidentifier
        );
        if (count($files) > 0) {
            return true;
        } else {
            $typefield = ($CFG->dbtype == "oci") ? "to_char(submissiontype)" : "submissiontype";
            $typefieldvalue = ($coursemodule->modname == 'forum') ? 'forum_post' : 'text_content';
            $DB->delete_records_select(
                'plagiarism_copyleaks_files',
                " cm = ? AND itemid = ? AND " . $typefield . " = ?",
                [$coursemodule->id, $data['objectid'], $typefieldvalue]
            );

            return $this->queue_submission_to_copyleaks(
                $coursemodule,
                $authoruserid,
                $submitteruserid,
                $contentidentifier,
                $typefieldvalue,
                $data['objectid'],
                $cmdata
            );
        }
    }

    /**
     * queue quizzes to be send to Copyleaks later by scan submission task.
     * @param object $data
     * @param object $coursemodule
     * @param string $authoruserid
     * @param string $submitteruserid
     * @param object $cmdata
     */
    private function queue_quizzes($data, $coursemodule, $authoruserid, $submitteruserid, $cmdata) {
        $result = true;

        if (class_exists('\\mod_quiz\\quiz_attempt')) {
            // Moodle 4.4+/5.x
            $attempt = \mod_quiz\quiz_attempt::create($data['objectid']);
        } else {
            // Older Moodle – versions prior to 4.4.
            global $CFG;
            require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');
            $attempt = quiz_attempt::create($data['objectid']);
        }

        foreach ($attempt->get_slots() as $slot) {
            $qa = $attempt->get_question_attempt($slot);
            if ($qa->get_question()->get_type_name() != 'essay') {
                continue;
            }
            $data['other']['content'] = $qa->get_response_summary();

            // Queue text to Copyleaks.
            $identifier = sha1($data['other']['content']);
            $result = $this->queue_submission_to_copyleaks(
                $coursemodule,
                $authoruserid,
                $submitteruserid,
                $identifier,
                'quiz_answer',
                $data['objectid'],
                $cmdata
            );

            // Queue files to Copyleaks.
            $context = context_module::instance($coursemodule->id);
            $files = $qa->get_last_qt_files('attachments', $context->id);
            foreach ($files as $file) {
                $identifier = $file->get_pathnamehash();
                $result = $this->queue_submission_to_copyleaks(
                    $coursemodule,
                    $authoruserid,
                    $submitteruserid,
                    $identifier,
                    'file',
                    $data['objectid'],
                    $cmdata
                );
            }
        }

        return $result;
    }

    /**
     * queue files to be send to Copyleaks later by scan submission task.
     * @param object $data
     * @param object $coursemodule
     * @param string $authoruserid
     * @param string $submitteruserid
     * @param object $cmdata
     */
    private function queue_files($data, $coursemodule, $authoruserid, $submitteruserid, $cmdata) {
        $result = true;

        foreach ($data['other']['pathnamehashes'] as $pathnamehash) {

            $hashedcontent = $this->check_existing_file_identifier($pathnamehash, $coursemodule, $authoruserid);

            $filestorage = get_file_storage();
            $fileref = $filestorage->get_file_by_hash($pathnamehash);

            if ($fileref) {
                try {
                    $fileref->get_content();
                } catch (Exception $e) {
                    \plagiarism_copyleaks_logs::add(
                        'Fail to get file content, pathnamehash: ' . $pathnamehash,
                        'FILE_CONTENT_NOT_FOUND'
                    );
                    continue;
                }
            } else {
                \plagiarism_copyleaks_logs::add(
                    'Fail to get file: ' . $pathnamehash,
                    'FILE_NOT_FOUND'
                );
                continue;
            }

            if ($fileref->get_filename() === '.') {
                continue;
            }

            $result = $this->queue_submission_to_copyleaks(
                $coursemodule,
                $authoruserid,
                $submitteruserid,
                $pathnamehash,
                'file',
                $data['objectid'],
                $cmdata,
                $hashedcontent
            );
        }

        return $result;
    }

    /**
     * Check if same identifier already exists in copyleaks_files table,
     * in case the content is different it will be deleted.
     * @param string $pathnamehash
     * @return string hashed content
     */
    private function check_existing_file_identifier($pathnamehash, $coursemodule, $authoruserid) {
        global $DB, $CFG;

        $filerecord = $DB->get_record('files', ['pathnamehash' => $pathnamehash]);
        $hashedcontent = null;

        if ($filerecord) {
            $hashedcontent = $filerecord->contenthash;

            $typefield = ($CFG->dbtype == "oci") ? "to_char(submissiontype)" : "submissiontype";

            $savedfiles = $DB->get_records_select(
                'plagiarism_copyleaks_files',
                " cm = ? AND userid = ? AND " . $typefield . " = ? AND identifier = ?",
                [$coursemodule->id, $authoruserid, "file", $pathnamehash]
            );

            if (count($savedfiles) > 0) {
                $savedfile = reset($savedfiles);

                if (isset($savedfile->hashedcontent) && $savedfile->hashedcontent != $hashedcontent) {
                    $DB->delete_records_select(
                        'plagiarism_copyleaks_files',
                        " cm = ? AND userid = ? AND " . $typefield . " = ? AND identifier = ?",
                        [$coursemodule->id, $authoruserid, "file", $pathnamehash]
                    );
                }
            }
        }
        return $hashedcontent;
    }

    /**
     * Queue submission to be submitted later to Copyleaks by submission task.
     * @param object $coursemodule
     * @param string $authoruserid
     * @param string $submitteruserid
     * @param string $identifier
     * @param string $subtype
     * @param int $itemid
     * @param object $cmdata
     * @return bool
     */
    private function queue_submission_to_copyleaks(
        $coursemodule,
        $authoruserid,
        $submitteruserid,
        $identifier,
        $subtype,
        $itemid,
        $cmdata,
        $hashedcontent = null
    ) {
        global $DB, $CFG;

        $errormessage = null;
        $errorcode = null;
        $clsubmissionid = null;
        $cl = new plagiarism_copyleaks_comms();

        if ($subtype == 'file') {
            $filestorage = get_file_storage();
            $fileref = $filestorage->get_file_by_hash($identifier);
            $filename = $fileref->get_filename();
        }
        // Check if submission already exists.
        $typefield = ($CFG->dbtype == "oci") ? " to_char(submissiontype) " : " submissiontype ";
        if ($DB->get_records_select(
            'plagiarism_copyleaks_files',
            " cm = ? AND itemid = ? AND " . $typefield . " = ? AND identifier = ? AND hashedcontent = ?",
            [$coursemodule->id, $itemid, $subtype, $identifier, $hashedcontent],
            'id',
            'id'
        )) {
            // Submission already exists, do not queue it again.
            return true;
        } else {
            $submissionid = plagiarism_copyleaks_submissions::create(
                $coursemodule,
                $authoruserid,
                $identifier,
                $subtype,
                $hashedcontent
            );
        }

        // Check if file type is supported by Copyleaks.
        if ($subtype == 'file') {
            $parts = explode('.', $filename);
            $extension = strtolower(end($parts));
            if (!in_array(
                "." . $extension,
                PLAGIARISM_COPYLEAKS_ACCEPTED_FILES
            )) {
                $errormessage = 'File type is not supported.';
                $errorcode = plagiarism_copyleaks_errorcode::UNSUPPORTED_FILE_TYPE;
            }
        }

        // Check file size limitation.
        if ($subtype == 'file') {
            if ($fileref->get_filesize() > PLAGIARISM_COPYLEAKS_MAX_FILE_UPLOAD_SIZE) {
                $errormessage = 'Exceeded the maximum allowed file size';
                $errorcode = plagiarism_copyleaks_errorcode::FILE_TOO_LARGE;
            }
        }

        // Scan immediately.
        $scheduledscandate = strtotime('- 1 minutes');
        if (isset($cmdata->duedate)) {
            // Get module settings.
            $clmoduleconfig = $DB->get_records_menu(
                'plagiarism_copyleaks_config',
                ['cm' => $coursemodule->id],
                '',
                'name,value'
            );

            if ($clmoduleconfig["plagiarism_copyleaks_reportgen"]) {
                // Scan on due date.
                $scheduledscandate = $cmdata->duedate - (1 * 60);
            }
        }

        $submitstatus = $errormessage == null ? 'queued' : 'error';

        if (plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {

            if (plagiarism_copyleaks_submissions::save(
                $coursemodule,
                $authoruserid,
                $submissionid,
                $identifier,
                $submitstatus,
                $clsubmissionid,
                $submitteruserid,
                $itemid,
                $subtype,
                $scheduledscandate,
                $errormessage,
                $errorcode
            )) {
                if ($this->modulename == 'assign') {
                    list($data, $submissionid) = $this->get_assign_submission_data(
                        $itemid,
                        $coursemodule,
                        $authoruserid,
                        $cmdata,
                        $identifier,
                        $subtype,
                        $scheduledscandate
                    );
                    $cl->upsert_submission($data, $submissionid);
                }
                return true;
            };
        }

        return plagiarism_copyleaks_submissions::save(
            $coursemodule,
            $authoruserid,
            $submissionid,
            $identifier,
            $submitstatus,
            $clsubmissionid,
            $submitteruserid,
            $itemid,
            $subtype,
            $scheduledscandate,
            $errormessage,
            $errorcode
        );
    }

    /**
     * check if submitter is instructor
     * @param mixed $data
     * @return bool is submitter instructor
     */
    private function is_instructor_submit($data) {
        return $data['relateduserid'] != $data['userid'];
    }

    /**
     * Handle user deletion event
     * @param object $data
     * @return void
     */
    public function handle_user_deletion($data) {
        global $DB;
        $usertable = "plagiarism_copyleaks_users";
        $userid = $data['objectid'];

        // Check if the user not agreed already.
        $isuseragreed = $DB->record_exists("plagiarism_copyleaks_users", ['userid' => $userid]);

        if ($isuseragreed) {
            if (!($DB->delete_records($usertable, ['userid' => $userid]))) {
                \plagiarism_copyleaks_logs::add(
                    "Faild to delete row in $usertable. (user id " . $userid,
                    "DELETE_RECORD_FAILED"
                );
            };
        }
        $isuseragreed = $DB->record_exists("plagiarism_copyleaks_eula", ['ci_user_id' => $userid]);
        if ($isuseragreed) {
            if (!($DB->delete_records('plagiarism_copyleaks_eula', ['ci_user_id' => $userid]))) {
                \plagiarism_copyleaks_logs::add(
                    "Faild to delete row in 'plagiarism_copyleaks_eula'. (user id " . $userid,
                    "DELETE_RECORD_FAILED"
                );
            };
        }
    }

    /**
     * Handle user EULA acceptance event
     * @param object $data
     * @param bool $isretry
     * @return void
     */
    public function handle_eula_acceptance($data, $isretry = false) {
        global $DB;
        $usertable = "plagiarism_copyleaks_users";
        // Check if the user not agreed already.
        $isuseragreed = $DB->record_exists("plagiarism_copyleaks_users", ['userid' => $data["userid"]]);

        if (!$isuseragreed) {
            $dataobject = [
                "userid" => $data["userid"],
                "user_eula_accepted" => 1,
            ];

            if (!($DB->insert_record($usertable, $dataobject, true, false)) && !$isretry) {
                $this->handle_eula_acceptance($data, true);
            } else if ($isretry) {
                \plagiarism_copyleaks_logs::add(
                    "Create new row in $usertable is faild. (user id " . $data["userid"],
                    "UPDATE_RECORD_FAILED"
                );
            }
        }
    }


    /**
     * get assign submission data
     * @param object $data
     * @param object $coursemodule
     * @param string $authoruserid
     * @param string $submitteruserid
     * @param object $cmdata
     * @param object $identifier
     * @param string $subtype
     * @param int $scheduledscandate
     */
    private function get_assign_submission_data(
        $itemid,
        $coursemodule,
        $authoruserid,
        $cmdata,
        $identifier,
        $submissiontype,
        $scheduledscandate
    ) {
        global $DB;

        $submissiondata = [];
        $submissionrecord = $DB->get_record(
            'assign_submission',
            ['id' => $itemid]
        );

        $submissionid = $submissionrecord->id;

        $submissiondata['attempt'] = $submissionrecord->attemptnumber;
        $submissiondata['moodleUserId'] = $authoruserid;
        $submissiondata['courseModuleId'] = $coursemodule->id;
        if (isset($submissionrecord->timecreated)) {
            $submissiondata['createdAt'] = ((new DateTime(
                'now',
                new DateTimeZone('UTC')
            ))->setTimestamp($submissionrecord->timecreated))->format('Y-m-d H:i:s');
        }

        if ($cmdata->teamsubmission == "1") {
            $group = $DB->get_record('groups', ['id' => $submissionrecord->groupid], 'id, name');
            if ($group) {
                $submissiondata['groupId'] = $group->id;
            }
        }

        $filename = '';
        if ($submissiontype == 'file') {
            $filestorage = get_file_storage();
            $fileref = $filestorage->get_file_by_hash($identifier);
            if ($fileref) {
                $filename = $fileref->get_filename();
            }
        } else if ($submissiontype == 'text_content') {
            $filename = 'online_text_'
                . $authoruserid . "_"
                . $coursemodule->id . "_"
                . $coursemodule->instance . '.txt';
        }

        $scandate = new DateTime('now', new DateTimeZone('UTC'));
        $scandate->setTimestamp($scheduledscandate);

        $reportdata = (array)[
            'courseModuleId' => $coursemodule->id,
            'moodleUserId' => $authoruserid,
            'identifier' => $identifier,
            'submissionType' => $submissiontype,
            'fileName' => $filename,
            'scheduledScanDate' => $scandate->format('Y-m-d H:i:s'),
        ];
        $data = (array)[
            'submission' => $submissiondata,
            'report' => $reportdata,
        ];

        return [$data, $submissionid];
    }
}
