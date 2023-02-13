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

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_pluginconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_assignmodule.class.php');

// Get url params.
$cmid = optional_param('cmid', null, PARAM_INT);
$modulename = optional_param('modulename', null, PARAM_TEXT);
$viewmode = optional_param('view', 'moodle', PARAM_TEXT);

$isadminview = false;
if (!isset($cmid) || !isset($modulename)) {
    $isadminview = true;
}

if ($isadminview) {
    require_once($CFG->libdir . '/adminlib.php');
    // Request login.
    require_login();
    admin_externalpage_setup('plagiarismcopyleaks');
    $context = context_system::instance();
    require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");

    // Setup page meta data for admin.
    $PAGE->set_url('/moodle/plagiarism/copyleaks/plagiarism_copyleaks_repositories.php', array(
        'viewmode' => $viewmode
    ));
    $pagetitle = get_string('cldefaultrepositoriespagetitle', 'plagiarism_copyleaks');
} else {
    // Get instance modules.
    $cm = get_coursemodule_from_id('', $cmid);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    // Request login.
    require_login($course, true, $cm);
    // Setup page meta data.

    $context = context_course::instance($cm->course);
    $PAGE->set_course($course);
    $PAGE->set_cm($cm);
    $PAGE->set_pagelayout('incourse');
    $PAGE->add_body_class('cl-report-page');

    $roles = get_user_roles($context, $USER->id);
    foreach ($roles as $role) {
        if ($role->shortname == 'student') {
            return;
        }
    }
    $PAGE->set_url('/moodle/plagiarism/copyleaks/plagiarism_copyleaks_settings.php', array(
        'cmid' => $cmid,
        'modulename' => $modulename
    ));
}

global $USER;
$userid = $USER->id;

if ($viewmode == 'course') {
    echo $OUTPUT->header();
}

// Copyleaks course settings.
$modulesettings = $DB->get_records_menu('plagiarism_copyleaks_config', array('cm' => $cmid), '', 'name,value');

$isinstructor = plagiarism_copyleaks_assignmodule::is_instructor($context);

$errormessagestyle = 'color:red; display:flex; width:100%; justify-content:center;';

$clmoduleenabled = plagiarism_copyleaks_pluginconfig::is_plugin_configured('mod_' . $cm->modname);

// Check if copyleaks plugin is disabled.
if (!$isadminview && (empty($clmoduleenabled) || empty($modulesettings['plagiarism_copyleaks_enable']))) {
    echo html_writer::div(get_string('cldisabledformodule', 'plagiarism_copyleaks'), null, array('style' => $errormessagestyle));
} else {
    // Incase students not allowed to see the plagiairsm score.
    if (!$isinstructor && empty($modulesettings['plagiarism_copyleaks_allowstudentaccess'])) {
        echo html_writer::div(get_string('clnopageaccess', 'plagiarism_copyleaks'), null, array('style' => $errormessagestyle));
    } else {
        if (!$isadminview) {
            $moduledata = $DB->get_record($cm->modname, array('id' => $cm->instance));

            $owners = array($userid);

            if ($cm->modname == 'assign' && $moduledata->teamsubmission) {
                require_once($CFG->dirroot . '/mod/assign/locallib.php');
                $assignment = new assign($context, $cm, null);
                if ($group = $assignment->get_submission_group($userid)) {
                    $users = groups_get_members($group->id);
                    $owners = array_keys($users);
                }
            }
        }

        // Proceed to displaying the report.
        if ($isinstructor || in_array($USER->id, $owners)) {

            // Get admin config.
            $config = plagiarism_copyleaks_pluginconfig::admin_config();

            // Add Page style via javascript.
            echo html_writer::script(
                "var css = document.createElement('style'); " .
                    "css.type = 'text/css'; " .
                    "styles = ' body.cl-report-page footer { display:none; }'; " .
                    "styles += 'body.cl-report-page .m-t-2 { display:none; }'; " .
                    "styles += ' body.cl-report-page #page-wrapper::after { min-height:unset; }'; " .
                    "if (css.styleSheet) " .
                    "css.styleSheet.cssText = styles; " .
                    "else " .
                    "css.appendChild(document.createTextNode(styles)); " .
                    "document.getElementsByTagName('head')[0].appendChild(css); "
            );

            $cl = new plagiarism_copyleaks_comms();
            $breadcrumbs = $cl->set_navbar_breadcrumbs($cm, $course);
            $role = 0;
            if ($isadminview) {
                $role = 1;
            } else if ($isinstructor) {
                $role = 2;
            }
            $accesstoken = $cl->request_access_for_settings($role, $breadcrumbs, $cm->modname, $cm->name);

            $lang = $cl->get_lang();
            if (!isset($cmid)) {
                $cmid = 0;
            }
            echo html_writer::tag(
                'iframe',
                null,
                array(
                    'title' => 'Copyleaks Settings',
                    'srcdoc' =>
                    "<form target='_self'" .
                        "method='POST'" .
                        "style='display: none;'" .
                        "action='$config->plagiarism_copyleaks_apiurl/api/moodle/$config->plagiarism_copyleaks_key" . "/settings/ " . " $cmid' >" .
                        "<input name='token' value='$accesstoken'>" .
                        "<input name='lang' value='$lang'>" .
                        "<input name='accessRole' value='$role'>" .
                        "</form>" .
                        "<script type='text/javascript'>" .
                        "window.document.forms[0].submit();" .
                        "</script>",
                    'style' =>
                    $viewmode == 'course' ?
                        'width: 100%; height: calc(100vh - 87px); margin: 0px; padding: 0px; border: none;' :
                        'width: 100%; height: 100%; margin: 0px; padding: 0px; border: none;'
                )
            );
        } else {
            echo html_writer::div(get_string('clnopageaccess', 'plagiarism_copyleaks'), null, array('style' => $errormessagestyle));
        }
    }
}



echo html_writer::script(
    "window.document.body.style.margin=0;"
);
