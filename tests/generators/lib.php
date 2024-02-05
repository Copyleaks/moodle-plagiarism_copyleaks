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
// require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/task/plagiarism_copyleaks_sendsubmissions.class.php');
// Get global class.

class plagiarism_copyleaks_test_lib extends advanced_testcase {
    protected $user;
    protected  $course;
    protected  $activity;
    protected $context;

    /**
     * Construct the test data.
     */
    protected function construct_copyleaks_parent_test($activitytype = 'assign', $accepteulabyuser = true, $configallactivities = true) {
        $this->set_admin_configuration($configallactivities);

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
     * Sets copyleaks admin configuration for the test environment.
     */
    protected function set_admin_configuration($configallactivities) {
        $config = new stdClass();
        $config->plagiarism_copyleaks_mod_assign = $configallactivities;
        $config->plagiarism_copyleaks_mod_forum = $configallactivities;
        $config->plagiarism_copyleaks_mod_quiz = $configallactivities;
        $config->plagiarism_copyleaks_mod_workshop = $configallactivities;
        $config->plagiarism_copyleaks_studentdisclosure = "By submitting your files...";
        $config->mform_isexpanded_id_plagiarism_copyleaks_accountconfigheader = "1";
        $config->plagiarism_copyleaks_apiurl = "https://gil.eu.ngrok.io";
        $config->plagiarism_copyleaks_key = "63ded63d-6ee4-4073-8cec-116c7ba60d2e";
        $config->plagiarism_copyleaks_secret = "CEE6C81185F8FC3EFC71C0513DF650ABCFB973D1AA5E63E0A303C6F20846A0AA";
        $config->submitbutton = "Save changes";

        $clslib = new plagiarism_copyleaks_adminform();
        $clslib->save($config);
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
        $this->activity = $this->getDataGenerator()->create_module($activitytype, ['course' => $this->course->id], [
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 12,
            'assignsubmission_file_maxsizebytes' => 10,
        ]);

        $this->enable_copyleaks_plugin_for_module($this->activity->cmid);

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
        $data["timecreated"] = time();
        $data["objectid"] = $itemid;

        $submissionhandler = new plagiarism_copyleaks_eventshandler($eventtype, $moduletype);
        $submissionhandler->handle_submissions($data);
        $submitter = new \plagiarism_copyleaks\task\plagiarism_copyleaks_sendsubmissions();
        $submitter->execute();
    }

    /**
     * Activate Copyleaks plugin by course module id.
     * @param string $cmid - course module id.
     */
    protected function enable_copyleaks_plugin_for_module($cmid) {
        plagiarism_copyleaks_moduleconfig::set_module_config($cmid, 1, 0, 0, 1);
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

            sleep(30);
            $elapsedtime = time() - $starttime;
        }

        $this->assertTrue(isset($result));
        $this->assertTrue(isset($result->similarityscore));
    }
}
