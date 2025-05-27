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
 * Copyleaks services
 * @package   plagiarism_copyleaks
 * @author    Shade Amasha <shadea@copyleaks.com>
 * @copyright 2021 Copyleaks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// We defined the web service functions to install.
$functions = [
  'plagiarism_copyleaks_send_notification' => [
    'classname'   => 'plagiarism_copyleaks_notifications',
    'methodname'  => 'send_notification',
    'classpath'   => 'plagiarism/copyleaks/classes/webservices/plagiarism_copyleaks_notifications.php',
    'description' => 'Send notification to a specific user',
    'type'        => 'write',
  ],
  'plagiarism_copyleaks_update_report_webhook' => [
    'classname'   => 'plagiarism_copyleaks_webhooks',
    'methodname'  => 'update_report_webhook',
    'classpath'   => 'plagiarism/copyleaks/classes/webservices/plagiarism_copyleaks_webhooks.php',
    'description' => 'update originality report',
    'type'        => 'write',
  ],
  'plagiarism_copyleaks_update_web_service_connection' => [
    'classname'   => 'plagiarism_copyleaks_webhooks',
    'methodname'  => 'update_api_connection_webhook',
    'classpath'   => 'plagiarism/copyleaks/classes/webservices/plagiarism_copyleaks_webhooks.php',
    'description' => 'update web service plugin config',
    'type'        => 'write',
  ],
  'plagiarism_copyleaks_get_course_groups_info' => [
    'classname'   => 'plagiarism_copyleaks_course_groups',
    'methodname'  => 'get_course_groups_info',
    'classpath'   => 'plagiarism/copyleaks/classes/webservices/plagiarism_copyleaks_course_groups.php',
    'description' => 'Get course groups info',
    'type'        => 'read',
  ],
  'plagiarism_copyleaks_get_course_groupings_info' => [
    'classname'   => 'plagiarism_copyleaks_course_groups',
    'methodname'  => 'get_course_groupings_info',
    'classpath'   => 'plagiarism/copyleaks/classes/webservices/plagiarism_copyleaks_course_groups.php',
    'description' => 'Get course groupings info',
    'type'        => 'read',
  ],
  'plagiarism_copyleaks_get_file_info' => [
    'classname'   => 'plagiarism_copyleaks_files',
    'methodname'  => 'get_file_info',
    'classpath'   => 'plagiarism/copyleaks/classes/webservices/plagiarism_copyleaks_files.php',
    'description' => 'Retrieve file information',
    'type'        => 'read',
    'capabilities' => 'moodle/course:managefiles',
  ],
  'plagiarism_copyleaks_get_multiple_scales_values' => [
    'classname'   => 'plagiarism_copyleaks_grades',
    'methodname'  => 'get_multiple_scales_values',
    'classpath'   => 'plagiarism/copyleaks/classes/webservices/plagiarism_copyleaks_grades.php',
    'description' => 'Get multiple scales values',
    'type'        => 'read',
  ],
  'plagiarism_copyleaks_get_student_report_access_by_cmids' => [
    'classname'   => 'plagiarism_copyleaks_course_modules',
    'methodname'  => 'get_student_report_access_by_cmids',
    'classpath'   => 'plagiarism/copyleaks/classes/webservices/plagiarism_copyleaks_course_modules.php',
    'description' => 'Returns student plagiarism report access flags for multiple course modules by their IDs.',
    'type'        => 'read',
  ],
];

// Pre-built service.
$services = [
  'Plagiarism Copyleaks Webservices' => [
    'functions' => [
      'plagiarism_copyleaks_send_notification',
      'plagiarism_copyleaks_update_report_webhook',
      'plagiarism_copyleaks_update_web_service_connection',
      'plagiarism_copyleaks_get_course_groups_info',
      'plagiarism_copyleaks_get_course_groupings_info',
      'plagiarism_copyleaks_get_multiple_scales_values',
      'plagiarism_copyleaks_get_file_info',
      'plagiarism_copyleaks_get_student_report_access_by_cmids',
      'mod_assign_save_grade',
      'core_comment_add_comments',
      'core_comment_delete_comments',
      'core_comment_get_comments',
      'core_competency_get_scale_values',
      'core_enrol_get_enrolled_users',
    ],
    'restrictedusers' => 1,
    'enabled' => 1,
    'shortname' => 'plagiarism_copyleaks_webservices',
    'downloadfiles' => 1,
  ],
];
