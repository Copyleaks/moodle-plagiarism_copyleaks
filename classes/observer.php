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
 * observer.php - Moodle events handlers for Copyleaks plagiairsm plugin
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/lib.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_eventshandler.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_moduleconfig.class.php');
require_once($CFG->dirroot . '/lib/grouplib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');


/**
 * Moodle events handlers for Copyleaks plagiairsm plugin
 */
class plagiarism_copyleaks_observer {
    /**
     * course module deleted event handler.
     * @param \core\event\course_module_deleted $event
     */
    public static function core_event_course_module_deleted(
        \core\event\course_module_deleted $event
    ) {
        global $DB;
        $data = $event->get_data();
        $cmid = $data['contextinstanceid'];

        // Delete Copyleaks module files.
        $DB->delete_records(
            'plagiarism_copyleaks_files',
            ['cm' => $cmid]
        );

        $ismoduleenabled = plagiarism_copyleaks_moduleconfig::is_module_enabled('assign', $cmid);

        // Delete Copyleaks module config.
        $DB->delete_records(
            'plagiarism_copyleaks_config',
            ['cm' => $cmid]
        );

        // Delete Copyleaks module queued requests.
        $DB->delete_records(
            'plagiarism_copyleaks_request',
            ['cmid' => $cmid]
        );
        if ($ismoduleenabled) {
            $cl = new \plagiarism_copyleaks_comms();
            $cl->delete_course_module($cmid);
        }
    }

    /**
     * on user deletion event handler.
     * @param \core\event\user_deleted $event
     */
    public static function core_event_user_deletion(\core\event\user_deleted $event) {
        $eventhandler = new plagiarism_copyleaks_eventshandler('user', 'deletion');
        $eventhandler->handle_user_deletion($event->get_data());
    }

    /**
     * assign submission file upload event handler.
     * @param \assignsubmission_file\event\assessable_uploaded $event
     */
    public static function assignsubmission_file_event_assessable_uploaded(
        \assignsubmission_file\event\assessable_uploaded $event
    ) {
        $eventhandler = new plagiarism_copyleaks_eventshandler('file_uploaded', 'assign');
        $eventhandler->handle_submissions($event->get_data());
    }

    /**
     * assign submission online text upload event handler.
     * @param \assignsubmission_onlinetext\event\assessable_uploaded $event
     */
    public static function assignsubmission_onlinetext_event_assessable_uploaded(
        \assignsubmission_onlinetext\event\assessable_uploaded $event
    ) {
        $eventhandler = new plagiarism_copyleaks_eventshandler('content_uploaded', 'assign');
        $eventhandler->handle_submissions($event->get_data());
    }

    /**
     * assign submission submitted event handler.
     * @param \mod_assign\event\assessable_submitted $event
     */
    public static function mod_assign_event_assessable_submitted(
        \mod_assign\event\assessable_submitted $event
    ) {
        $eventhandler = new plagiarism_copyleaks_eventshandler('assessable_submitted', 'assign');
        $eventhandler->handle_submissions($event->get_data());
    }

    /**
     * workshop submission module event handler.
     * @param \mod_workshop\event\assessable_uploaded $event
     */
    public static function mod_workshop_event_assessable_uploaded(
        \mod_workshop\event\assessable_uploaded $event
    ) {
        $eventhandler = new plagiarism_copyleaks_eventshandler('assessable_submitted', 'workshop');
        $eventhandler->handle_submissions($event->get_data());
    }

    /**
     * forum submission module event handler.
     * @param \mod_forum\event\assessable_uploaded $event
     */
    public static function mod_forum_event_assessable_uploaded(
        \mod_forum\event\assessable_uploaded $event
    ) {
        $eventhandler = new plagiarism_copyleaks_eventshandler('assessable_submitted', 'forum');
        $eventhandler->handle_submissions($event->get_data());
    }

    /**
     * quiz submission event handler.
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function mod_quiz_event_attempt_submitted(
        \mod_quiz\event\attempt_submitted $event
    ) {
        $eventdata = $event->get_data();
        $plugin = new plagiarism_copyleaks_eventshandler('quiz_submitted', 'quiz');
        $plugin->handle_submissions($eventdata);
    }

    /**
     * Handle assign submission deletion.
     * @param  \mod_assign\event\submission_status_updated $event Event
     * @return void
     */
    public static function assign_submission_status_updated(
        \mod_assign\event\submission_status_updated $event
    ) {
        global $DB;

        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        $eventdata = $event->get_data();
        $cmid = $eventdata["contextinstanceid"];

        // Check if the module is enabled.
        if (!plagiarism_copyleaks_moduleconfig::is_module_enabled('assign', $cmid)) {
            return;
        }

        // Get user id.
        $userid = $eventdata['relateduserid'];
        if ($userid == null) {
            $userid = $eventdata['userid'];
        }

        // Delete in assign.
        if ($eventdata['target'] == 'submission_status') {
            // The event is triggered when a submission is deleted and when the submission is passed to draft.
            $fs = get_file_storage();
            $submissionfiles = $fs->get_area_files(
                $eventdata["contextid"],
                "assignsubmission_file",
                'submission_files',
                $eventdata["objectid"]
            );

            // If the documents have been deleted in the mdl_files table, we also delete them on our side.
            if (empty($submissionfiles)) {
                $cl = new \plagiarism_copyleaks_comms();
                $submissionid = $eventdata["objectid"];
                $data = (array)[
                    'courseModuleId' => $cmid,
                    'moodleUserId' => $userid,
                ];
                $cl->delete_submission($data, $submissionid);
                $DB->delete_records('plagiarism_copyleaks_files', ['cm' => $cmid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Handle file deletion in assign submission
     * @param  \assignsubmission_file\event\submission_updated $event Event
     * @return void
     */
    public static function assign_submission_file_updated(
        \assignsubmission_file\event\submission_updated $event
    ) {
        global $DB;

        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        $eventdata = $event->get_data();
        $cmid = $eventdata["contextinstanceid"];
        $submissionid = $eventdata["other"]["submissionid"];

        // Check if the module is enabled.
        if (!plagiarism_copyleaks_moduleconfig::is_module_enabled('assign', $cmid)) {
            return;
        }

        // Get user id.
        $userid = $eventdata['relateduserid'];
        if ($userid == null) {
            $userid = $eventdata['userid'];
        }

        if ($eventdata['target'] == 'submission' && $eventdata['action'] == 'updated') {

            $clfiles = $DB->get_records(
                'plagiarism_copyleaks_files',
                ['cm' => $cmid, 'itemid' => $submissionid, 'submissiontype' => 'file']
            );
            $fs = get_file_storage();
            foreach ($clfiles as $clfile) {
                $file = $fs->get_file_by_hash($clfile->identifier);
                if ($file === false) {
                    $cl = new \plagiarism_copyleaks_comms();
                    $data = (array)[
                        'identifier' => $clfile->identifier,
                        'courseModuleId' => $clfile->cm,
                        'moodleUserId' => $clfile->userid,
                    ];
                    $cl->delete_report($data);
                    $DB->delete_records(
                        'plagiarism_copyleaks_files',
                        ['cm' => $cmid, 'userid' => $clfile->userid, 'identifier' => $clfile->identifier]
                    );
                }
            }
        }
    }

    /**
     * assign submission comment creation event handler.
     * @param \assignsubmission_comments\event\comment_created $event
     */
    public static function assign_submission_comment_created(
        \assignsubmission_comments\event\comment_created $event
    ) {
        global $DB;
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        $eventdata = $event->get_data();
        $cmid = $eventdata['contextinstanceid'];

        // Check if the module is enabled.
        if (!plagiarism_copyleaks_moduleconfig::is_module_enabled('assign', $cmid)) {
            return;
        }

        $cl = new \plagiarism_copyleaks_comms();
        $datetime = new DateTime('now', new DateTimeZone('UTC'));
        $commentcontent = $DB->get_record('comments', ['id' => $eventdata['objectid']], 'content');
        $commentdata = (array)[
            'commentId' => $eventdata['objectid'],
            'courseModuleId' => $cmid,
            'moodleUserId' => $eventdata['userid'],
            'content' => $commentcontent->content,
            'createdAt' => ($datetime->setTimestamp($eventdata['timecreated']))->format('Y-m-d H:i:s'),
        ];

        $submissionid = $eventdata['other']['itemid'];

        $cl->add_assign_submission_comment($commentdata, $submissionid);
    }

    /**
     * assign submission comment deletion event handler.
     * @param \assignsubmission_comments\event\comment_deleted $event
     */
    public static function assign_submission_comment_deleted(
        \assignsubmission_comments\event\comment_deleted $event
    ) {
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        $eventdata = $event->get_data();
        $cmid = $eventdata['contextinstanceid'];

        // Check if the module is enabled.
        if (!plagiarism_copyleaks_moduleconfig::is_module_enabled('assign', $cmid)) {
            return;
        }

        $cl = new \plagiarism_copyleaks_comms();
        $commentdata = (array)[
            'commentId' => $eventdata['objectid'],
            'courseModuleId' => $cmid,
            'moodleUserId' => $eventdata['userid'],
        ];

        $submissionid = $eventdata['other']['itemid'];

        $cl->delete_assign_submission_comment($commentdata, $submissionid);
    }

    /**
     * user graded event handler.
     * @param \core\event\user_graded $event
     */
    public static function user_graded(\core\event\user_graded $event) {
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        $eventdata = $event->get_data();

        if (!$item = grade_item::fetch(['id' => $eventdata['other']['itemid']])) {
            return;
        }

        if (
            $item->itemtype == 'mod' && $item->itemmodule == 'assign'
        ) {

            $cl = new \plagiarism_copyleaks_comms();

            // Exists in grade_grades table.
            if (!grade_grade::fetch(['id' => $eventdata['objectid']])) {
                return;
            }

            $cmid = get_coursemodule_from_instance($item->itemmodule, $item->iteminstance, $eventdata['courseid'])->id;

            // Check if the module is enabled.
            if (!plagiarism_copyleaks_moduleconfig::is_module_enabled('assign', $cmid)) {
                return;
            }

            $userid     = ($eventdata['relateduserid']) ? $eventdata['relateduserid'] : $eventdata['userid'];

            $finalgrade = $eventdata['other']['finalgrade'];
            if (is_string($finalgrade) && strpos($finalgrade, "0.") !== false) {
                $finalgrade = 0;
            }

            // If the grade type is "1" (points),
            // Format the final grade to two decimal places to ensure consistency in stored values.
            if ($item->gradetype == "1") {
                $finalgrade = number_format((float)$finalgrade, 2, '.', '');
            }

            $course = get_course($eventdata['courseid']);

            $data = (array)[
                'courseModuleId' => $cmid,
                'moodleUserId' => $userid,
                'finalGrade' => $finalgrade,
                'courseName' => $course->fullname,
            ];

            $cl->upsert_assign_grade($data);
        }
    }

    /**
     * Group created event handler.
     * @param \core\event\group_created $event
     */
    public static function group_created(\core\event\group_created $event) {
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        $eventdata = $event->get_data();
        $courseid = $eventdata['courseid'];

        // Check if Copyleaks is enabled for any assignment in a course.
        if (!plagiarism_copyleaks_moduleconfig::is_copyleaks_enabled_for_any_module($courseid, "assign")) {
            return;
        }

        $cl = new \plagiarism_copyleaks_comms();
        $groupname = groups_get_group_name($eventdata['objectid']);
        $data = (array)[
            'groupId' => $eventdata['objectid'],
            'groupName' => $groupname,
        ];

        $cl->create_group($data, $courseid);
    }

    /**
     * Group deleted event handler.
     * @param \core\event\group_deleted $event
     */
    public static function group_deleted(\core\event\group_deleted $event) {
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }
        $eventdata = $event->get_data();
        $courseid = $eventdata['courseid'];

        // Check if Copyleaks is enabled for any assignment in a course.
        if (!plagiarism_copyleaks_moduleconfig::is_copyleaks_enabled_for_any_module($courseid, "assign")) {
            return;
        }

        $cl = new \plagiarism_copyleaks_comms();
        $data = (array)[
            'groupId' => $eventdata['objectid'],
        ];
        $cl->delete_group($data, $courseid);
    }


    /**
     * Group updated event handler.
     * @param \core\event\group_updated $event
     */
    public static function group_updated(\core\event\group_updated $event) {
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        $eventdata = $event->get_data();
        $courseid = $eventdata['courseid'];

        // Check if Copyleaks is enabled for any assignment in a course.
        if (!plagiarism_copyleaks_moduleconfig::is_copyleaks_enabled_for_any_module($courseid, "assign")) {
            return;
        }

        $cl = new \plagiarism_copyleaks_comms();
        $groupname = groups_get_group_name($eventdata['objectid']);
        $data = (array)[
            'groupId' => $eventdata['objectid'],
            'groupName' => $groupname,
        ];

        $cl->update_group($data, $courseid);
    }

    /**
     * Group member added event handler.
     * @param \core\event\group_member_added $event
     */
    public static function group_member_added(\core\event\group_member_added $event) {
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        $eventdata = $event->get_data();
        $courseid = $eventdata['courseid'];

        // Check if Copyleaks is enabled for any assignment in a course.
        if (!plagiarism_copyleaks_moduleconfig::is_copyleaks_enabled_for_any_module($courseid, "assign")) {
            return;
        }

        $cl = new \plagiarism_copyleaks_comms();
        $user = get_complete_user_data('id', $eventdata['relateduserid']);
        $groupname = groups_get_group_name($eventdata['objectid']);
        $groupdata = (array)[
            'groupId' => $eventdata['objectid'],
            'groupName' => $groupname,
        ];

        $userdata = (array)[
            'mppUserId' => $eventdata['relateduserid'],
            'userName' => $user->firstname . " " . $user->lastname,
            'userEmail' => $user->email,
        ];

        $data = (array)[
            'group' => $groupdata,
            'user' => $userdata,
        ];

        $cl->add_group_member($data, $courseid);
    }

    /**
     * Group member removed event handler.
     * @param \core\event\group_member_removed $event
     */
    public static function group_member_removed(\core\event\group_member_removed $event) {
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        $eventdata = $event->get_data();
        $courseid = $eventdata['courseid'];

        // Check if Copyleaks is enabled for any assignment in a course.
        if (!plagiarism_copyleaks_moduleconfig::is_copyleaks_enabled_for_any_module($courseid, "assign")) {
            return;
        }

        $cl = new \plagiarism_copyleaks_comms();
        $groupdata = (array)[
            'groupId' => $eventdata['objectid'],
        ];

        $userdata = (array)[
            'mppUserId' => $eventdata['relateduserid'],
        ];

        $data = (array)[
            'group' => $groupdata,
            'user' => $userdata,
        ];

        $cl->remove_group_member($data, $courseid);
    }

    /**
     * Grouping created event handler.
     * @param \core\event\grouping_created $event
     */
    public static function grouping_created(\core\event\grouping_created $event) {
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        $eventdata = $event->get_data();
        $courseid = $eventdata['courseid'];

        // Check if Copyleaks is enabled for any assignment in a course.
        if (!plagiarism_copyleaks_moduleconfig::is_copyleaks_enabled_for_any_module($courseid, "assign")) {
            return;
        }

        $cl = new \plagiarism_copyleaks_comms();
        $groupingname = groups_get_grouping_name($eventdata['objectid']);
        $data = (array)[
            'groupingId' => $eventdata['objectid'],
            'groupingName' => $groupingname,
        ];

        $cl->create_grouping($data, $courseid);
    }

    /**
     * Grouping deleted event handler.
     * @param \core\event\grouping_deleted $event
     */
    public static function grouping_deleted(\core\event\grouping_deleted $event) {
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        $eventdata = $event->get_data();
        $courseid = $eventdata['courseid'];

        // Check if Copyleaks is enabled for any assignment in a course.
        if (!plagiarism_copyleaks_moduleconfig::is_copyleaks_enabled_for_any_module($courseid, "assign")) {
            return;
        }

        $cl = new \plagiarism_copyleaks_comms();
        $data = (array)[
            'groupingId' => $eventdata['objectid'],
        ];

        $cl->delete_grouping($data, $courseid);
    }

    /**
     * Grouping updated event handler.
     * @param \core\event\grouping_updated $event
     */
    public static function grouping_updated(\core\event\grouping_updated $event) {
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        $eventdata = $event->get_data();
        $courseid = $eventdata['courseid'];

        // Check if Copyleaks is enabled for any assignment in a course.
        if (!plagiarism_copyleaks_moduleconfig::is_copyleaks_enabled_for_any_module($courseid, "assign")) {
            return;
        }

        $cl = new \plagiarism_copyleaks_comms();
        $groupingname = groups_get_grouping_name($eventdata['objectid']);
        $data = (array)[
            'groupingId' => $eventdata['objectid'],
            'groupingName' => $groupingname,
        ];

        $cl->update_grouping($data, $courseid);
    }

    /**
     * Grouping group assigned event handler.
     * @param \core\event\grouping_group_assigned $event
     */
    public static function grouping_group_assigned(\core\event\grouping_group_assigned $event) {
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        $eventdata = $event->get_data();
        $courseid = $eventdata['courseid'];

        // Check if Copyleaks is enabled for any assignment in a course.
        if (!plagiarism_copyleaks_moduleconfig::is_copyleaks_enabled_for_any_module($courseid, "assign")) {
            return;
        }

        // Check if the groupid is empty.
        if (empty($eventdata['other']['groupid'])) {
            return;
        }

        $cl = new \plagiarism_copyleaks_comms();
        $groupingname = groups_get_grouping_name($eventdata['objectid']);
        $groupingdata = (array)[
            'groupingId' => $eventdata['objectid'],
            'groupingName' => $groupingname,
        ];

        $group = groups_get_group($eventdata['other']['groupid']);
        $groupdata = (array)[
            'groupId' => $eventdata['other']['groupid'],
            'groupName' => $group->name,
        ];

        $data = (array)[
            'grouping' => $groupingdata,
            'group' => $groupdata,
        ];

        $cl->add_grouping_group($data, $courseid);
    }

    /**
     * Grouping group unassigned event handler.
     * @param \core\event\grouping_group_unassigned $event
     */
    public static function grouping_group_unassigned(\core\event\grouping_group_unassigned $event) {
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        $eventdata = $event->get_data();
        $courseid = $eventdata['courseid'];

        // Check if Copyleaks is enabled for any assignment in a course.
        if (!plagiarism_copyleaks_moduleconfig::is_copyleaks_enabled_for_any_module($courseid, "assign")) {
            return;
        }

        $cl = new \plagiarism_copyleaks_comms();
        $groupingdata = (array)[
            'groupingId' => $eventdata['objectid'],
        ];

        $groupdata = (array)[
            'groupId' => $eventdata['other']['groupid'],
        ];

        $data = (array)[
            'grouping' => $groupingdata,
            'group' => $groupdata,
        ];

        $cl->remove_grouping_group($data, $courseid);
    }

    /**
     * User enrolment deleted event handler.
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        // Get event data.
        $eventdata = $event->get_data();
        $courseid = $eventdata['courseid'];

        // If the courseid is 0, then the event is not related to a course.
        if ($courseid == 0) {
            return;
        }

        // Check if Copyleaks is enabled for any assignment in a course.
        if (!plagiarism_copyleaks_moduleconfig::is_copyleaks_enabled_for_any_module($courseid, "assign")) {
            return;
        }

        // Id is the id of the user that was unenroled.
        $data = [
            'id' => $eventdata['relateduserid'],
        ];

        $cl = new \plagiarism_copyleaks_comms();
        $cl->unenrol_user($data, $courseid);
    }


    /**
     * Role assigned event handler.
     * Handles the role assigned event to enroll users and assign roles.
     * @param \core\event\role_assigned $event
     */
    public static function role_assigned(\core\event\role_assigned $event) {
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        // Get event data.
        $eventdata = $event->get_data();
        $courseid = $eventdata['courseid'];

        // If the courseid is 0, then the event is not related to a course.
        if ($courseid == 0) {
            return;
        }

        // Check if Copyleaks is enabled for any assignment in a course.
        if (!plagiarism_copyleaks_moduleconfig::is_copyleaks_enabled_for_any_module($courseid, "assign")) {
            return;
        }

        $user = get_complete_user_data('id', $eventdata['relateduserid']);
        $roleid = $eventdata['objectid'];  // The ID of the assigned role.

        // Retrieve all roles.
        $roles = get_all_roles();

        // Check if the role with the specific roleid exists.
        if (!isset($roles[$roleid])) {
            // Role not found.
            return;
        }

        $role = (object)[
            'shortName' => $roles[$roleid]->shortname,
        ];

        // Id: the ID of the user that was enroled into the course and assigned the role.
        $data = [
            'id' => $eventdata['relateduserid'],
            'fullName' => $user->firstname . ' ' . $user->lastname,
            'email' => $user->email,
            'roles' => [$role],
        ];

        $cl = new \plagiarism_copyleaks_comms();

        // Use enrol_user to handle role assignments: update role if user is enrolled, otherwise enroll and assign the role.
        $cl->enrol_user($data, $courseid);
    }

    /**
     * role unassigned event handler.
     * @param \core\event\role_unassigned $event
     */
    public static function role_unassigned(\core\event\role_unassigned $event) {
        // Check if Copyleaks API is connected.
        if (!plagiarism_copyleaks_dbutils::is_copyleaks_api_connected()) {
            return;
        }

        // Get event data.
        $eventdata = $event->get_data();
        $courseid = $eventdata['courseid'];

        // If the courseid is 0, then the event is not related to a course.
        if ($courseid == 0) {
            return;
        }

        // Check if Copyleaks is enabled for any assignment in a course.
        if (!plagiarism_copyleaks_moduleconfig::is_copyleaks_enabled_for_any_module($courseid, "assign")) {
            return;
        }

        $roleid = $eventdata['objectid'];  // The ID of the assigned role.

        // Retrieve all roles.
        $roles = get_all_roles();

        // Check if the role with the specific roleid exists.
        if (!isset($roles[$roleid])) {
            // Role not found.
            return;
        }

        $role = (object)[
            'shortName' => $roles[$roleid]->shortname,
        ];

        // Id: the ID of the user that was enroled into the course and assigned the role.
        $data = [
            'id' => $eventdata['relateduserid'],
            'roles' => [$role],
        ];

        $cl = new \plagiarism_copyleaks_comms();
        $cl->unassign_role($data, $courseid);
    }
}
