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
}
