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
 * Contains Plagiarism plugin specific functions called by Modules.
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Gil Coehn <gilc@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/forms/plagiarism_copyleaks_adminform.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_moduleconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_dbutils.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_eventshandler.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/tests/generators/lib.php');

class copyleaks_base_test_lib extends advanced_testcase {
    protected $user;
    protected  $course;
    protected  $activity;
    protected $context;

    /**
     * Construct the test data.
     */
    protected function construct_copyleaks_parent_test($activitytype = 'assign', $accepteulabyuser = true, $configallactivities = true) {
        plagiarism_copyleaks_test_lib::set_admin_configuration($configallactivities);

        $config = get_config('plagiarism_copyleaks');
        $this->assertTrue(isset($config->plagiarism_copyleaks_apiurl) && !empty($config->plagiarism_copyleaks_apiurl));
        $this->assertTrue(isset($config->plagiarism_copyleaks_secret) && !empty($config->plagiarism_copyleaks_secret));
        $this->assertTrue(isset($config->plagiarism_copyleaks_key) && !empty($config->plagiarism_copyleaks_key));
        $this->assertTrue(plagiarism_copyleaks_comms::test_copyleaks_connection('admin_settings_page'));


        $this->course = $this->getDataGenerator()->create_course();
        $this->create_user($accepteulabyuser);
        $this->create_activity_and_enroll_user($activitytype);
        $this->context = context_module::instance($this->activity->cmid);
    }

    /**
     * Create a user for the test environment.
     * @param boolean $accepteula - should the user accept Copyleaks EULA.
     */
    protected function create_user($accepteula = false) {
        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);

        if ($accepteula) {
            plagiarism_copyleaks_dbutils::upsert_eula_by_user_id($this->user->id);
        }
    }

    /**
     * Create new assignment for the submission and enroll the user member to the assignment's course.
     */
    protected function create_activity_and_enroll_user($activitytype) {
        $this->activity = $this->getDataGenerator()->create_module(
            $activitytype,
            [
                'course' => $this->course->id,
                'course' => $this->course->id,
                'grade' => 100.0, 'sumgrades' => 1
            ],
            [
                'assignsubmission_file_enabled' => 1,
                'assignsubmission_file_maxfiles' => 12,
                'assignsubmission_file_maxsizebytes' => 10,
            ]
        );

        plagiarism_copyleaks_test_lib::enable_copyleaks_plugin_for_module($this->activity->cmid);

        $this->getDataGenerator()->enrol_user($this->user->id, $this->activity->cmid);
    }

    /**
     * @param string $contextid - the context of the submission.
     * @param string $userid - the user submitter.
     * @return array of path name hash and the item id  
     */
    protected function insert_file_record() {
        $filename = 'file2.txt';
        $filearea = 'submission_files';
        $component = 'assignsubmission_file';
        $itemid = 4;

        $filepath = dirname(__DIR__) . '/fixtures/' . $filename;
        $filecontent = file_get_contents($filepath);

        $pathnamehash = file_storage::get_pathname_hash($this->context->id, $component, $filearea, $itemid, '/', $filename);
        $hashcontent = \file_storage::hash_from_string($filecontent);

        $filerecord = array(
            'contextid' => $this->context->id,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $this->user->id,
            'filesize' => 1024,
            'mimetype' => 'text/plain',
            'status' => 0,
            'source' => 'file',
            'author' => 'testuser',
            'license' => 'allrightsreserved',
            'timecreated' => time(),
            'timemodified' => time(),
            'pathnamehash' => $pathnamehash,
            'contenthash' => $hashcontent
        );

        file_save_draft_area_files(time(), $this->context->id, $component, $filearea, $itemid, null, null);

        $fs = get_file_storage();

        $file1 = $fs->create_file_from_string($filerecord, $filecontent);
        $this->assertInstanceOf('stored_file', $file1);

        $fileref = $fs->get_file_by_hash($pathnamehash);
        $this->assertTrue(isset($fileref));
        $filecontent = $fileref->get_content();
        $this->assertTrue(isset($filecontent));

        return array(
            'pathnamehash' => $pathnamehash,
            'itemid' => $itemid
        );
    }

    protected function submit_text_answer_to_quiz() {
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $qa = $questiongenerator->create_question('essay', 'plain', array('category' => $cat->id));
        quiz_add_quiz_question($qa->id, $this->activity);

        // Create quiz attempt.
        $content = "My Wonderfull essay!!!!";

        $quizobj = \quiz::create($this->activity->id, $this->user->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $this->user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = \quiz_attempt::create($attempt->id);

        $attemptobj->process_submitted_actions(
            $timenow + 300,
            false,
            array(1 => ['answer' => $content, 'answerformat' => FORMAT_PLAIN])
        );
        $attemptobj->process_finish($timenow + 600, false);

        return array(
            'pathnamehash' => sha1($content),
            'itemid' => $quizobj->get_context()->id
        );
    }


    protected function create_workshop_submission() {
        // Switch phase.
        $title = 'Submission title';
        $content = 'Submission contents';

        // Create a file in a draft area for inline attachments.
        $fs = get_file_storage();
        $draftidinlineattach = file_get_unused_draft_itemid();
        $usercontext = \context_user::instance($this->user->id);
        $filenameimg = 'shouldbeanimage.txt';
        $filerecordinline = array(
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => $draftidinlineattach,
            'filepath'  => '/',
            'filename'  => $filenameimg,
        );
        $fs->create_file_from_string($filerecordinline, 'image contents (not really)');

        // Create a file in a draft area for regular attachments.
        $draftidattach = file_get_unused_draft_itemid();
        $filerecordattach = $filerecordinline;
        $attachfilename = 'attachment.txt';
        $filerecordattach['filename'] = $attachfilename;
        $filerecordattach['itemid'] = $draftidattach;
        $fs->create_file_from_string($filerecordattach, 'simple text attachment');

        // Switch to submission phase.
        $cm = get_coursemodule_from_instance('workshop', $this->activity->id);
        $workshop = new workshop($this->activity, $cm, $this->course);
        $workshop->switch_phase(workshop::PHASE_SUBMISSION);

        mod_workshop_external::add_submission(
            $this->activity->id,
            $title,
            $content,
            FORMAT_MOODLE,
            $draftidinlineattach,
            $draftidattach
        );

        return array(
            'pathnamehash' => sha1($content),
            'itemid' => $draftidattach
        );
    }

    protected function post_to_forum() {
        $filecontent = 'This is the message';
        $component = 'mod_forum';
        $filearea = 'attachment';
        $filename = 'example.txt';

        $now = time();
        $forumgenerator = $this->getDataGenerator()->get_plugin_generator('mod_forum');
        $forumgenparams = [
            'course' => $this->course->id,
            'userid' => $this->user->id,
            'forum' => $this->activity->id,
        ];
        $forumgenparams['timestart'] = $now;
        $discussion = $forumgenerator->create_discussion((object) $forumgenparams);
        $post = $forumgenerator->create_post((object) [
            'discussion' => $discussion->id,
            'parent' => 0,
            'userid' => $this->user->id,
            'created' => $now,
            'modified' => $now,
            'subject' => 'This is the subject',
            'message' => $filecontent,
            'messagetrust' => 1,
            'attachment' => 0,
            'totalscore' => 0,
            'mailnow' => 0,
            'deleted' => 0
        ]);

        $pathnamehash = file_storage::get_pathname_hash($this->context->id, $component, $filearea, $post->id, '/', $filename);
        $hashcontent = \file_storage::hash_from_string($filecontent);

        $filestorage = get_file_storage();

        file_save_draft_area_files(time(), $this->context->id, $component, $filearea, $post->id, null, null);

        $file1 = $filestorage->create_file_from_string(
            [
                'contextid' => $this->context->id,
                'component' => 'mod_forum',
                'filearea'  => 'attachment',
                'itemid'    => $post->id,
                'filepath'  => '/',
                'filename'  => $filename,
                'contenthash' => $hashcontent,
                'pathnamehash' => $pathnamehash,
            ],
            $filecontent
        );

        $this->assertInstanceOf('stored_file', $file1);

        $fileref = $filestorage->get_file_by_hash($pathnamehash);
        $this->assertTrue(isset($fileref));
        $filecontent = $fileref->get_content();
        $this->assertTrue(isset($filecontent));

        return array(
            'pathnamehash' => $pathnamehash,
            'itemid' => $post->id
        );
    }

    /**
     * @param string $pathnamehash - the submission hashed name;
     * @param string $itemid - the submission item id.
     * @param string $eventtype - the event of the submission - 'assessable_submitted'/'file_uploaded' etc.
     * @param string $moduletype - the course module type of the submission.
     */
    protected function queue_submission_to_copyleaks($pathnamehash, $itemid, $eventtype, $moduletype) {
        $data["contextinstanceid"] = $this->activity->cmid;
        $data["userid"] = $this->user->id;
        $data["courseid"] = $this->course->id;
        $data["other"]["pathnamehashes"] = [0 => $pathnamehash];
        if ($moduletype == "quiz") {
            $data["other"]["quizid"] = $this->activity->id;
            $data["other"]["content"] = $pathnamehash;
        }
        $data["timecreated"] = time();
        $data["objectid"] = $itemid;

        $submissionhandler = new plagiarism_copyleaks_eventshandler($eventtype, $moduletype);
        $submissionhandler->handle_submissions($data);
    }

    /**
     * @param string $identifier
     */
    protected function assert_copyleaks_result($identifier) {
        global $DB;
        $result = false;
        $reportupdater = new \plagiarism_copyleaks\task\plagiarism_copyleaks_updatereports();


        $maxexecutiontime = 600; // Sets 10 minutes wait for submission to end.
        $starttime = time();
        $elapsedtime = time() - $starttime;

        while ($elapsedtime <= $maxexecutiontime) {
            $reportupdater->execute();

            $result = $DB->get_record('plagiarism_copyleaks_files', array('identifier' => $identifier));
            if (isset($result) && $result->statuscode != 'pending') {
                break;
            }

            sleep(10);
            $elapsedtime = time() - $starttime;
        }

        $this->assertTrue($result->statuscode == "success");
        $this->assertTrue(isset($result->similarityscore) && $result->similarityscore >= 0);
    }
}
