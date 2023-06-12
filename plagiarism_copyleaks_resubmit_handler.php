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
 * Copyleaks report page
 * @package   plagiarism_copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @copyright 2021 Copyleaks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(dirname(dirname(__FILE__)) . '/../config.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_submissions.class.php');

global $CFG, $USER;
$fileid = optional_param('fileid', null, PARAM_TEXT);
$cmid = optional_param('cmid', null, PARAM_TEXT);
$courseid = optional_param('courseid', null, PARAM_TEXT);
$route = optional_param('route', null, PARAM_TEXT);
$action = optional_param('action', null, PARAM_TEXT);
$workshopid = optional_param('id', null, PARAM_TEXT);

$cm = get_coursemodule_from_id('', $cmid, $courseid);
require_login($courseid, true, $cm);

$context = context_course::instance($courseid);
$roles = get_user_roles($context, $USER->id);

foreach ($roles as $role) {
    if ($role->shortname == 'student') {
        echo html_writer::div(get_string('clnopageaccess', 'plagiarism_copyleaks'), null, array('style' => $errormessagestyle));
        return;
    }
}

plagiarism_copyleaks_submissions::change_failed_scan_to_queued($fileid);

$path = $CFG->wwwroot . $route;
$querypos = strpos($path, '?');
if ($action) {
    if ($querypos) {
        $path = $path . "&action=$action";
    } else {
        $path = $path . "?action=$action";
    }
}

if ($workshopid && $cm->modname == "workshop") {
    if ($querypos) {
        $path = $path . "&id=$workshopid";
    } else {
        $path = $path . "?id=$workshopid";
    }
}

redirect($path);
