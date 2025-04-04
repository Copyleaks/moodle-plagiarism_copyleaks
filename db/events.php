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

$observers = [
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback'  => 'plagiarism_copyleaks_observer::core_event_course_module_deleted',
    ],
    [
        'eventname' => '\assignsubmission_file\event\assessable_uploaded',
        'callback'  => 'plagiarism_copyleaks_observer::assignsubmission_file_event_assessable_uploaded',
    ],
    [
        'eventname' => '\assignsubmission_onlinetext\event\assessable_uploaded',
        'callback'  => 'plagiarism_copyleaks_observer::assignsubmission_onlinetext_event_assessable_uploaded',
    ],
    [
        'eventname' => '\mod_assign\event\assessable_submitted',
        'callback'  => 'plagiarism_copyleaks_observer::mod_assign_event_assessable_submitted',
    ],
    [
        'eventname' => '\mod_workshop\event\assessable_uploaded',
        'callback'  => 'plagiarism_copyleaks_observer::mod_workshop_event_assessable_uploaded',
    ],
    [
        'eventname' => '\mod_forum\event\assessable_uploaded',
        'callback'  => 'plagiarism_copyleaks_observer::mod_forum_event_assessable_uploaded',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback'  => 'plagiarism_copyleaks_observer::mod_quiz_event_attempt_submitted',
    ],
    [
        'eventname' => '\core\event\user_deleted',
        'callback'  => 'plagiarism_copyleaks_observer::core_event_user_deletion',
    ],
    [
        'eventname' => '\mod_assign\event\submission_status_updated',
        'callback'  => 'plagiarism_copyleaks_observer::assign_submission_status_updated',
    ],
    [
        'eventname' => '\assignsubmission_file\event\submission_updated',
        'callback'  => 'plagiarism_copyleaks_observer::assign_submission_file_updated',
    ],
    [
        'eventname' => '\assignsubmission_onlinetext\event\submission_updated',
        'callback'  => 'plagiarism_copyleaks_observer::assign_submission_text_updated',
    ],
    [
        'eventname' => '\assignsubmission_comments\event\comment_created',
        'callback'  => 'plagiarism_copyleaks_observer::assign_submission_comment_created',
    ],
    [
        'eventname' => '\assignsubmission_comments\event\comment_deleted',
        'callback'  => 'plagiarism_copyleaks_observer::assign_submission_comment_deleted',
    ],
    [
        'eventname' => '\core\event\user_graded',
        'callback'  => 'plagiarism_copyleaks_observer::user_graded',
    ],
    [
        'eventname' => '\core\event\group_created',
        'callback'  => 'plagiarism_copyleaks_observer::group_created',
    ],
    [
        'eventname' => '\core\event\group_deleted',
        'callback'  => 'plagiarism_copyleaks_observer::group_deleted',
    ],
    [
        'eventname' => '\core\event\group_updated',
        'callback'  => 'plagiarism_copyleaks_observer::group_updated',
    ],
    [
        'eventname' => '\core\event\group_member_added',
        'callback'  => 'plagiarism_copyleaks_observer::group_member_added',
    ],
    [
        'eventname' => '\core\event\group_member_removed',
        'callback'  => 'plagiarism_copyleaks_observer::group_member_removed',
    ],
    [
        'eventname' => '\core\event\grouping_created',
        'callback'  => 'plagiarism_copyleaks_observer::grouping_created',
    ],
    [
        'eventname' => '\core\event\grouping_deleted',
        'callback'  => 'plagiarism_copyleaks_observer::grouping_deleted',
    ],
    [
        'eventname' => '\core\event\grouping_updated',
        'callback'  => 'plagiarism_copyleaks_observer::grouping_updated',
    ],
    [
        'eventname' => '\core\event\grouping_group_assigned',
        'callback'  => 'plagiarism_copyleaks_observer::grouping_group_assigned',
    ],
    [
        'eventname' => '\core\event\grouping_group_unassigned',
        'callback'  => 'plagiarism_copyleaks_observer::grouping_group_unassigned',
    ],
    [
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback'  => 'plagiarism_copyleaks_observer::user_enrolment_deleted',
    ],
    [
        'eventname' => '\core\event\role_assigned',
        'callback'  => 'plagiarism_copyleaks_observer::role_assigned',
    ],
    [
        'eventname' => '\core\event\role_unassigned',
        'callback'  => 'plagiarism_copyleaks_observer::role_unassigned',
    ],
];
