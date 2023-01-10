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
 * Copyleaks repositories management page
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
    $cm = get_coursemodule_from_id(str_replace("mod_", "", $modulename), $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    // Request login.
    require_login($course, true, $cm);

    // Setup page meta data for course.
    $context = context_course::instance($cm->course);
    $PAGE->set_course($course);
    $PAGE->set_cm($cm);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_url('/moodle/plagiarism/copyleaks/plagiarism_copyleaks_repositories.php', array(
        'cmid' => $cmid,
        'modulename' => $modulename,
        'viewmode' => $viewmode
    ));
    $pagetitle = $course->fullname . ' - ' . $cm->name . ' - ' . get_string('clrepositoriespagetitle', 'plagiarism_copyleaks');
}

// Setup page meta data.
$PAGE->add_body_class('cl-repositories-page');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

if ($viewmode == 'moodle') {
    echo $OUTPUT->header();
}

$config = plagiarism_copyleaks_pluginconfig::admin_config();

if ($isadminview) {
    // Admin repositories settings.
    $cl = new plagiarism_copyleaks_comms();
    $accesstoken = $cl->request_access_for_repositories();
    $lang = $cl->get_lang();
    echo html_writer::tag(
        'iframe',
        null,
        array(
            'title' => 'Copyleaks admin repository settings',
            'srcdoc' =>
            "<form target='_self'" .
                "method='POST'" .
                "style='display: none;'" .
                "action='$config->plagiarism_copyleaks_apiurl/api/moodle/$config->plagiarism_copyleaks_key/repositories'>" .
                "<input name='token' value='$accesstoken'>" .
                "<input name='lang' value='$lang'>" .
                "</form>" .
                "<script type='text/javascript'>" .
                "window.document.forms[0].submit();" .
                "</script>",
            'style' =>
            $viewmode == 'moodle' ?
                'width: 100%; height: calc(100vh - 450px); margin: 0px; padding: 0px; border: none;' :
                'width: 100%; height: 100%; margin: 0px; padding: 0px; border: none;'
        )
    );
} else {
    // Copyleaks repositories course settings.
    $modulesettings = $DB->get_records_menu('plagiarism_copyleaks_config', array('cm' => $cmid), '', 'name,value');
    $isinstructor = plagiarism_copyleaks_assignmodule::is_instructor($context);
    $errormessagestyle = 'color:red; display:flex; width:100%; justify-content:center;';
    $clmoduleenabled = plagiarism_copyleaks_pluginconfig::is_plugin_configured('mod_' . $cm->modname);
    // Check if copyleaks plugin is disabled.
    if (empty($clmoduleenabled) || empty($modulesettings['plagiarism_copyleaks_enable'])) {
        echo html_writer::div(
            get_string('cldisabledformodule', 'plagiarism_copyleaks'),
            null,
            array('style' => $errormessagestyle)
        );
    } else {
        if (!$isinstructor) {
            // Incase students.
            echo html_writer::div(
                get_string('clnopageaccess', 'plagiarism_copyleaks'),
                null,
                array('style' => $errormessagestyle)
            );
        } else {
            $cl = new plagiarism_copyleaks_comms();
            $accesstoken = $cl->request_access_for_repositories($cmid);
            $lang = $cl->get_lang();
            echo html_writer::tag(
                'iframe',
                null,
                array(
                    'title' => 'Copyleaks repository settings',
                    'srcdoc' =>
                    "<form target='_self'" .
                        "method='POST'" .
                        "style='display: none;' action='$config->plagiarism_copyleaks_apiurl/api" .
                        "/moodle/$config->plagiarism_copyleaks_key/repositories/$cmid'>" .
                        "<input name='token' value='$accesstoken'>" .
                        "<input name='lang' value='$lang'>" .
                        "</form>" .
                        "<script type='text/javascript'>" .
                        "window.document.forms[0].submit();" .
                        "</script>",
                    'style' =>
                    $viewmode == 'moodle' ?
                        'width: 100%; height: calc(100vh - 450px); margin: 0px; padding: 0px; border: none;' :
                        'width: 100%; height: 100%; margin: 0px; padding: 0px; border: none;'
                )
            );
        }
    }
}

// Output footer.
if ($viewmode == 'moodle') {
    echo $OUTPUT->footer();
}

if ($viewmode == 'fullscreen') {
    echo html_writer::script(
        "window.document.body.style.margin=0;"
    );
}
