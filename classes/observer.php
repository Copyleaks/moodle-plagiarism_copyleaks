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
require_once($CFG->dirroot . '/lib/grouplib.php');

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
            array('cm' => $cmid)
        );

        // Delete Copyleaks module config.
        $DB->delete_records(
            'plagiarism_copyleaks_config',
            array('cm' => $cmid)
        );

        // Delete Copyleaks module queued requests.
        $DB->delete_records(
            'plagiarism_copyleaks_request',
            array('cmid' => $cmid)
        );
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
     * Handle assign submission deletion.
     * @param  \mod_assign\event\submission_status_updated $event Event
     * @return void
     */
    public static function assign_submission_status_updated(
        \mod_assign\event\submission_status_updated $event
    ) {
        global $DB;
        $eventdata = $event->get_data();
        $cmid = $eventdata["contextinstanceid"];

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

        $eventdata = $event->get_data();
        $cmid = $eventdata["contextinstanceid"];
        $submissionid = $eventdata["other"]["submissionid"];

        if (!plagiarism_copyleaks_moduleconfig::is_module_enabled('assign', $cmid)) {
            return;
        }

        // Get user id.
        $userid = $eventdata['relateduserid'];
        if ($userid == null) {
            $userid = $eventdata['userid'];
        }

        if ($eventdata['target'] == 'submission' && $eventdata['action'] == 'updated') {

            $clfiles = $DB->get_records('plagiarism_copyleaks_files', ['cm' => $cmid, 'itemid' => $submissionid, 'submissiontype' => 'file']);
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
                    $DB->delete_records('plagiarism_copyleaks_files', ['cm' => $cmid, 'userid' => $clfile->userid, 'identifier' => $clfile->identifier]);
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
        $cl = new \plagiarism_copyleaks_comms();
        $datetime = new DateTime();
        $eventdata = $event->get_data();
        $commentcontent = $DB->get_record('comments', ['id' => $eventdata['objectid']], 'content');
        $commentdata = (array)[
            'commentId' => $eventdata['objectid'],
            'courseModuleId' => $eventdata['contextinstanceid'],
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
        $eventdata = $event->get_data();
        $cl = new \plagiarism_copyleaks_comms();
        $commentdata = (array)[
            'commentId' => $eventdata['objectid'],
            'courseModuleId' => $eventdata['contextinstanceid'],
            'moodleUserId' => $eventdata['userid'],
        ];

        $submissionid = $eventdata['other']['itemid'];

        $cl->delete_assign_submission_comment($commentdata, $submissionid);
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
     * user graded event handler.
     * @param \core\event\user_graded $event
     */
    public static function user_graded(
        \core\event\user_graded $event
    ) {
        // if (!\plagiarism_copyleaks_moduleconfig::is_module_enabled($this->task->get_modulename(), $newcmid)) {
        //     return;
        // }
        global $DB;
        $eventdata = $event->get_data();

        if (!$item = $DB->get_record('grade_items', ['id' => $eventdata['other']['itemid']])) {
            return;
        }

        if (
            $item->itemtype == 'mod'
        ) {

            $cl = new \plagiarism_copyleaks_comms();

            if (!$DB->get_record('grade_grades', ['id' => $eventdata['objectid']])) {
                return;
            }

            $cmid = get_coursemodule_from_instance($item->itemmodule, $item->iteminstance, $eventdata['courseid'])->id;
            $userid     = ($eventdata['relateduserid']) ? $eventdata['relateduserid'] : $eventdata['userid'];
            $finalgrade = $eventdata['other']['finalgrade'];

            $data = (array)[
                'courseModuleId' => $cmid,
                'moodleUserId' => $userid,
                'finalGrade' => $finalgrade,
            ];

            $cl->upsert_assign_grade($data);
        }
    }

    /**
     * Group created event handler.
     * @param \core\event\group_created $event
     */
    public static function group_created(\core\event\group_created $event) {
        $eventdata = $event->get_data();
        $cl = new \plagiarism_copyleaks_comms();
        $groupname = groups_get_group_name($eventdata['objectid']);
        $courseId = $eventdata['courseid'];
        $data = (array)[
            'groupId' => $eventdata['objectid'],
            'groupName' => $groupname,
        ];

        $cl->create_group($data, $courseId);
    }

    /**
     * Group deleted event handler.
     * @param \core\event\group_deleted $event
     */
    public static function group_deleted(\core\event\group_deleted $event) {
        $eventdata = $event->get_data();
        $cl = new \plagiarism_copyleaks_comms();
        $courseId = $eventdata['courseid'];
        $data = (array)[
            'groupId' => $eventdata['objectid'],
        ];
        $cl->delete_group($data, $courseId);
    }


    /**
     * Group updated event handler.
     * @param \core\event\group_updated $event
     */
    public static function group_updated(\core\event\group_updated $event) {
        $eventdata = $event->get_data();
        $cl = new \plagiarism_copyleaks_comms();
        $groupname = groups_get_group_name($eventdata['objectid']);
        $courseId = $eventdata['courseid'];
        $data = (array)[
            'groupId' => $eventdata['objectid'],
            'groupName' => $groupname,
        ];

        $cl->update_group($data, $courseId);
    }

    /**
     * Group member added event handler.
     * @param \core\event\group_member_added $event
     */
    public static function group_member_added(\core\event\group_member_added $event) {
        global $DB;
        $eventdata = $event->get_data();
        $cl = new \plagiarism_copyleaks_comms();
        $user = $DB->get_record('user', array('id' => $eventdata['relateduserid']), '*', MUST_EXIST);
        $groupname = groups_get_group_name($eventdata['objectid']);
        $courseId = $eventdata['courseid'];
        $groupdata = (array)[
            'groupId' => $eventdata['objectid'],
            'groupName' => $groupname,
        ];

        $userdata = (array)[
            'mppUserId' => $eventdata['relateduserid'],
            'userName' => fullname($user),
        ];

        $data = (array)[
            'group' => $groupdata,
            'user' => $userdata
        ];

        $cl->add_group_member($data, $courseId);
    }

    /**
     * Group member removed event handler.
     * @param \core\event\group_member_removed $event
     */
    public static function group_member_removed(\core\event\group_member_removed $event) {
        $eventdata = $event->get_data();
        $cl = new \plagiarism_copyleaks_comms();
        $courseId = $eventdata['courseid'];
        $groupdata = (array)[
            'groupId' => $eventdata['objectid'],
        ];

        $userdata = (array)[
            'mppUserId' => $eventdata['relateduserid'],
        ];

        $data = (array)[
            'group' => $groupdata,
            'user' => $userdata
        ];

        $cl->remove_group_member($data, $courseId);
    }
}
