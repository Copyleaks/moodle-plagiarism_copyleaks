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
 * Resubmit handler.
 * @package   plagiarism_copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @copyright 2021 Copyleaks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(dirname(__FILE__)) . '/../config.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_submissions.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/enums/plagiarism_copyleaks_enums.php');

require_login();

global $CFG, $USER;
$rescanmode = required_param('rescanmode', PARAM_INT);
$fileid = optional_param('fileid', null, PARAM_TEXT);
$cmid = optional_param('cmid', null, PARAM_TEXT);
$courseid = optional_param('courseid', null, PARAM_TEXT);
$route = optional_param('route', null, PARAM_TEXT);
$action = optional_param('action', null, PARAM_TEXT);
$workshopid = optional_param('id', null, PARAM_TEXT);
$sid = optional_param('sid', null, PARAM_TEXT);
$pluginparam = optional_param('plugin', null, PARAM_TEXT);
$returnaction = optional_param('returnaction', null, PARAM_TEXT);

validations($cmid, $rescanmode);

switch ($rescanmode) {
    case plagiarism_copyleaks_rescan_mode::RESCAN_ALL:
        plagiarism_copyleaks_submissions::change_failed_scans_to_queued();
        break;
    case plagiarism_copyleaks_rescan_mode::RESCAN_MODULE:
        require_login($cm->course, false, $cm);
        plagiarism_copyleaks_submissions::change_failed_scans_to_queued($cmid);
        break;
    case plagiarism_copyleaks_rescan_mode::RESCAN_SINGLE:
        rescan_single($fileid, $cmid, $courseid, $route, $action, $workshopid, $sid, $pluginparam, $returnaction, $cm);
        break;
    default:
        throw new moodle_exception('invalidparameter', 'error');
}

/**
 * Validations
 * @param int $cmid
 * @param bool $hasadminpermission
 * @param int $rescanmode
 */
function validations($cmid, $rescanmode) {
    if ($rescanmode != plagiarism_copyleaks_rescan_mode::RESCAN_ALL) {
        // TEACHER: Rescanning all failed scans in a module or single scan
        if (!$cmid) {
            throw new moodle_exception('missingparam', 'error', '', 'cmid');
        }

        $cm = get_coursemodule_from_id('', $cmid);

        if (!$cm) {
            throw new moodle_exception('invalidcoursemodule');
        }
        // Get module context (for teacher check, only if cmid is provided)
        $context = context_module::instance($cm->id);
        // Block unauthorized access **before switch**
        if (!has_capability('plagiarism/copyleaks:resubmitfailedscans', $context)) {
            throw new moodle_exception('nopermission', 'error');
        }
        return;
    }
    // Get system context (for admin check)
    $system_context = context_system::instance();
    $hasadminpermission = has_capability('plagiarism/copyleaks:adminresubmitfailedscans', $system_context);
    // Block unauthorized access **before switch**
    if ($rescanmode == plagiarism_copyleaks_rescan_mode::RESCAN_ALL && !$hasadminpermission) {
        throw new moodle_exception('nopermission', 'error');
    }
}

/**
 * Rescanning a specific failed scan
 * @param int $fileid
 * @param int $cmid
 * @param int $courseid
 * @param string $route
 * @param string $action
 * @param int $workshopid
 * @param int $sid
 * @param string $pluginparam
 * @param string $returnaction
 * @param object $cm
 */
function rescan_single($fileid, $cmid, $courseid, $route, $action, $workshopid, $sid, $pluginparam, $returnaction, $cm) {
    // TEACHER: Rescanning a specific failed scan
    if (!$fileid || !$cmid || !$courseid || !$route) {
        throw new moodle_exception('missingparam', 'error');
    }

    require_login($courseid, false, $cm);

    plagiarism_copyleaks_submissions::change_failed_scan_to_queued($fileid);
    global $CFG;
    $path = $CFG->wwwroot . $route;
    $querypos = strpos($path, '?');
    if ($action) {
        if ($querypos) {
            $path = $path . "&action=$action";
        } else {
            $path = $path . "?action=$action";
        }
    }

    if (
        $workshopid && $cm->modname == "workshop"
    ) {
        if ($querypos) {
            $path = $path . "&id=$workshopid";
        } else {
            $path = $path . "?id=$workshopid";
        }
    }

    if ($sid) {
        $path .= "&sid=$sid";
    }

    if ($returnaction) {
        $path .= "&returnaction=$returnaction";
    }

    if ($pluginparam) {
        $path .= "&plugin=$pluginparam";
    }

    redirect($path);
}
