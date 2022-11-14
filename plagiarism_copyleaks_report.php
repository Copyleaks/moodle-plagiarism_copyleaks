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
$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$identifier = required_param('identifier', PARAM_TEXT);
$modulename = required_param('modulename', PARAM_TEXT);
$viewmode = optional_param('view', 'course', PARAM_TEXT);

// Get instance modules.
$cm = get_coursemodule_from_id($modulename, $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

// Request login.
require_login($course, true, $cm);

// Setup page meta data.
$context = context_course::instance($cm->course);
$PAGE->set_course($course);
$PAGE->set_cm($cm);
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('cl-report-page');
$PAGE->set_url('/moodle/plagiarism/copyleaks/plagiarism_copyleaks_report.php', array(
    'cmid' => $cmid,
    'userid' => $userid,
    'identifier' => $identifier,
    'modulename' => $modulename
));

// Setup page title and header.
$user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
$fs = get_file_storage();
$file = $fs->get_file_by_hash($identifier);
if ($file) {
    $filename = $file->get_filename();
    $pagetitle = get_string('clreportpagetitle', 'plagiarism_copyleaks') . ' - ' . fullname($user) . ' - ' . $filename;
} else {
    $pagetitle = get_string('clreportpagetitle', 'plagiarism_copyleaks') . ' - ' . fullname($user);
}
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

if ($viewmode == 'course') {
    echo $OUTPUT->header();
}

// Copyleaks course settings.
$modulesettings = $DB->get_records_menu('plagiarism_copyleaks_config', array('cm' => $cmid), '', 'name,value');

$isinstructor = plagiarism_copyleaks_assignmodule::is_instructor($context);

$errormessagestyle = 'color:red; display:flex; width:100%; justify-content:center;';

$clmoduleenabled = plagiarism_copyleaks_pluginconfig::is_plugin_configured('mod_' . $cm->modname);

// Check if copyleaks plugin is disabled.
if (empty($clmoduleenabled) || empty($modulesettings['plagiarism_copyleaks_enable'])) {
    echo html_writer::div(get_string('cldisabledformodule', 'plagiarism_copyleaks'), null, array('style' => $errormessagestyle));
} else {
    // Incase students not allowed to see the plagiairsm score.
    if (!$isinstructor && empty($modulesettings['plagiarism_copyleaks_allowstudentaccess'])) {
        echo html_writer::div(get_string('clnopageaccess', 'plagiarism_copyleaks'), null, array('style' => $errormessagestyle));
    } else {
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

        // Proceed to displaying the report.
        if ($isinstructor || in_array($USER->id, $owners)) {

            // Get admin config.
            $config = plagiarism_copyleaks_pluginconfig::admin_config();

            // Get submission db ref.
            $plagiarismfiles = $DB->get_record(
                'plagiarism_copyleaks_files',
                array(
                    'cm' => $cmid,
                    'userid' => $userid,
                    'identifier' => $identifier
                ),
                '*',
                MUST_EXIST
            );

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

            if ($viewmode == 'course') {
                echo html_writer::link(
                    "$CFG->wwwroot/plagiarism/copyleaks/plagiarism_copyleaks_report.php" .
                        "?cmid=$cmid&userid=$userid&identifier=$identifier&modulename=$modulename&view=fullscreen",
                    get_string('clopenfullscreen', 'plagiarism_copyleaks'),
                    array('title' => get_string('clopenfullscreen', 'plagiarism_copyleaks'))
                );
            }

            $cl = new plagiarism_copyleaks_comms();
            $scanaccesstoken = $cl->request_access_for_report($plagiarismfiles->externalid, $isinstructor);
            $lang = $cl->get_lang();
            echo html_writer::tag(
                'iframe',
                null,
                array(
                    'title' => 'Copyleaks Report',
                    'srcdoc' =>
                    "<form target='_self'" .
                        "method='POST'" .
                        "style='display: none;'" .
                        "action='$config->plagiarism_copyleaks_apiurl/api/moodle/$config->plagiarism_copyleaks_key" .
                        "/report/$plagiarismfiles->externalid'>" .
                        "<input name='token' value='$scanaccesstoken'>" .
                        "<input name='lang' value='$lang'>" .
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

// Output footer.
if ($viewmode == 'course') {
    echo $OUTPUT->footer();
}

if ($viewmode == 'fullscreen') {
    echo html_writer::script(
        "window.document.body.style.margin=0;"
    );
}
