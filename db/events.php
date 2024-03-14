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
 * Event handlers (subscriptions) are defined here
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = array(
    array(
        'eventname' => '\core\event\course_module_deleted',
        'callback'  => 'plagiarism_copyleaks_observer::core_event_course_module_deleted'
    ),
    array(
        'eventname' => '\assignsubmission_file\event\assessable_uploaded',
        'callback'  => 'plagiarism_copyleaks_observer::assignsubmission_file_event_assessable_uploaded'
    ),
    array(
        'eventname' => '\assignsubmission_onlinetext\event\assessable_uploaded',
        'callback'  => 'plagiarism_copyleaks_observer::assignsubmission_onlinetext_event_assessable_uploaded'
    ),
    array(
        'eventname' => '\mod_assign\event\assessable_submitted',
        'callback'  => 'plagiarism_copyleaks_observer::mod_assign_event_assessable_submitted'
    ),
    array(
        'eventname' => '\mod_workshop\event\assessable_uploaded',
        'callback'  => 'plagiarism_copyleaks_observer::mod_workshop_event_assessable_uploaded'
    ),
    array(
        'eventname' => '\mod_forum\event\assessable_uploaded',
        'callback'  => 'plagiarism_copyleaks_observer::mod_forum_event_assessable_uploaded'
    ),
    array(
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback'  => 'plagiarism_copyleaks_observer::mod_quiz_event_attempt_submitted'
    ),
    array(
        'eventname' => '\core\event\user_deleted',
        'callback'  => 'plagiarism_copyleaks_observer::core_event_user_deletion'
    ),
    array(
        'eventname' => '\core\event\comment_created',
        'callback'  => 'plagiarism_copyleaks_observer::mod_assign_comment_event_created'
    ),
    array(
        'eventname' => 'core\event\comment_deleted',
        'callback'  => 'plagiarism_copyleaks_observer::mod_assign_comment_event_deleted'
    ),
    array(
        'eventname' => 'mod_data\event\comment_created',
        'callback'  => 'plagiarism_copyleaks_observer::mod_data_comment_event_created'
    ),
    array(
        'eventname' => 'mod_assign\event\submission_graded',
        'callback'  => 'plagiarism_copyleaks_observer::mod_assign_graded_event'
    ),
    array(
        'eventname' => 'mod_quiz\event\attempt_regraded',
        'callback'  => 'plagiarism_copyleaks_observer::mod_quiz_graded_event'
    ),
    array(
        'eventname' => 'mod_quiz\event\question_manually_graded',
        'callback'  => 'plagiarism_copyleaks_observer::mod_quiz_question_manually_graded'
    ),
    array(
        'eventname' => 'mod_workshop\event\phase_switched',
        'callback'  => 'plagiarism_copyleaks_observer::mod_workshop_phase_switched'
    ),
    array(
        'eventname' => 'mod_workshop\event\assessment_evaluated',
        'callback'  => 'plagiarism_copyleaks_observer::mod_workshop_assessment_evaluated'
    ),
    array(
        'eventname' => 'mod_workshop\event\assessment_reevaluated',
        'callback'  => 'plagiarism_copyleaks_observer::mod_workshop_assessment_reevaluated'
    ),
    array(
        'eventname' => 'mod_workshop\event\submission_assessed',
        'callback'  => 'plagiarism_copyleaks_observer::mod_workshop_submission_assessed'
    ),
);
