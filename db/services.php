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
    'classname'   => 'plagiarism_copyleaks_external',
    'methodname'  => 'send_notification',
    'classpath'   => 'plagiarism/copyleaks/externallib.php',
    'description' => 'Send notification to user',
    'type'        => 'write',
  ),
);

// Pre-built service.
$services = array(
  'Plagiarism Copyleaks Webservices' => array(
    'functions' => array(
      'plagiarism_copyleaks_send_notification',
      'mod_assign_save_grade',
      'core_grading_get_definitions',
      'core_grades_update_grades',
    ),
    'restrictedusers' => 1,
    'enabled' => 1,
    'shortname' => 'plagiarism_copyleaks_webservices',
  )
);
