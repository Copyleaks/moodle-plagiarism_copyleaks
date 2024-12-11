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
 * Copyleaks settings page
 * @package   plagiarism_copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @copyright 2021 Copyleaks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(dirname(__FILE__)) . '/../config.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_utils.class.php');

global $USER, $DB;
// Get url params.
$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('', $cmid);
$courseid  = $cm->course;
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_course::instance($courseid);
$roles = get_user_roles($context, $USER->id);
$errormessagestyle = 'color:red; display:flex; width:100%; justify-content:center;';

foreach ($roles as $role) {
    if ($role->shortname == 'student') {
        echo html_writer::div(get_string('clnopageaccess', 'plagiarism_copyleaks'), null, ['style' => $errormessagestyle]);
        return;
    }
}

echo html_writer::script(
    "var css = document.createElement('style'); " .
        "css.type = 'text/css'; " .
        "styles = ' body.cl-settings-page footer { display:none; }'; " .
        "styles += 'body.cl-settings-page .m-t-2 { display:none; }'; " .
        "styles += ' body.cl-settings-page #page-wrapper::after { min-height:unset; }'; " .
        "if (css.styleSheet) " .
        "css.styleSheet.cssText = styles; " .
        "else " .
        "css.appendChild(document.createTextNode(styles)); " .
        "document.getElementsByTagName('head')[0].appendChild(css); "
);

$cl = new plagiarism_copyleaks_comms();
$accesstoken = $cl->request_access_for_analytics($cmid, $USER->id);

$config = plagiarism_copyleaks_pluginconfig::admin_config();
$actionurl = "$config->plagiarism_copyleaks_apiurl/api/moodle/plugin/$config->plagiarism_copyleaks_key/analytics/$cmid/index";
$lang = plagiarism_copyleaks_utils::get_lang();

$coursestartdate = plagiarism_copyleaks_utils::get_course_start_date($courseid);

echo html_writer::tag(
    'iframe',
    null,
    [
        'title' => 'Copyleaks Analytics',
        'srcdoc' =>
        "<form target='_self'" .
            "method='POST'" .
            "style='display: none;'" .
            "action='$actionurl' >" .
            "<input name='token' value='$accesstoken'>" .
            "<input name='lang' value='$lang'>" .
            "<input name='courseId' value='$courseid'>" .
            "<input name='courseName' value='$course->fullname'>" .
            "<input name='startdate' value='$coursestartdate'>" .
            "</form>" .
            "<script type='text/javascript'>" .
            "window.document.forms[0].submit();" .
            "</script>",
        'style' => 'width: 100%; height: 100%; margin: 0px; padding: 0px; border: none;',
    ]
);

echo html_writer::script(
    "window.document.body.style.margin=0;"
);
