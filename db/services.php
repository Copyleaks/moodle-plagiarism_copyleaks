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


// We defined the web service functions to install.
$functions = array(
  'plagiarism_copyleaks_send_notification' => array(
    'classname'   => 'plagiarism_copyleaks_notifications',
    'methodname'  => 'send_notification',
    'classpath'   => 'plagiarism/copyleaks/classes/webservices/plagiarism_copyleaks_notifications.php',
    'description' => 'Send notification to a specific user',
    'type'        => 'write',
  ),
  'plagiarism_copyleaks_update_report_webhook' => array(
    'classname'   => 'plagiarism_copyleaks_webhooks',
    'methodname'  => 'update_report_webhook',
    'classpath'   => 'plagiarism/copyleaks/classes/webservices/plagiarism_copyleaks_webhooks.php',
    'description' => 'update originality report',
    'type'        => 'write',
  ),
  'plagiarism_copyleaks_update_failed_scan_to_queued_webhook' => array(
    'classname'   => 'plagiarism_copyleaks_webhooks',
    'methodname'  => 'update_failed_scan_to_queued_webhook',
    'classpath'   => 'plagiarism/copyleaks/classes/webservices/plagiarism_copyleaks_webhooks.php',
    'description' => 'update failed scan status to queued',
    'type'        => 'write',
  ),
);

// Pre-built service.
$services = array(
  'Plagiarism Copyleaks Webservices' => array(
    'functions' => array(
      'plagiarism_copyleaks_send_notification',
      'plagiarism_copyleaks_update_report_webhook',
      'plagiarism_copyleaks_update_failed_scan_to_queued_webhook',
      'mod_assign_save_grade',
      'core_comment_add_comments',
      'core_group_get_group_members',
      'core_competency_get_scale_values',
    ),
    'restrictedusers' => 1,
    'enabled' => 1,
    'shortname' => 'plagiarism_copyleaks_webservices',
  )
);
