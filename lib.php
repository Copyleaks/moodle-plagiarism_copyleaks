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
 * Contains Plagiarism plugin specific functions called by Modules.
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Get global class.
global $CFG;
require_once($CFG->dirroot . '/plagiarism/lib.php');

// Get helper methods.
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_pluginconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_moduleconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/enums/plagiarism_copyleaks_enums.php');

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_assignmodule.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_utils.class.php');

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_authexception.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_exception.class.php');

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_submissiondisplay.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');
/**
 * Contains Plagiarism plugin specific functions called by Modules.
 */
class plagiarism_plugin_copyleaks extends plagiarism_plugin {
    /**
     * hook to allow plagiarism specific information to be displayed beside a submission
     * @param array $linkarray contains all relevant information for the plugin to generate a link
     * @return string displayed output
     */
    public function get_links($linkarray) {
        return plagiarism_copyleaks_submissiondisplay::output($linkarray);
    }

    /**
     * hook to save plagiarism specific settings on a module settings page
     * @param stdClass $data form data
     */
    public function save_form_elements($data) {
        global $DB;
        // Check if plugin is configured and enabled.
        if (
            empty($data->modulename) ||
            !plagiarism_copyleaks_pluginconfig::is_plugin_configured('mod_' . $data->modulename)
        ) {
            return;
        }

        // If the record exists with status:ERROR, delete it.
        if (plagiarism_copyleaks_dbutils::is_cm_duplicated_error($data->coursemodule)) {
            $DB->delete_records('plagiarism_copyleaks_cm_copy', array('new_cm_id' => $data->coursemodule));
        }

        if ($data->plagiarism_copyleaks_enable) {

            if (plagiarism_copyleaks_dbutils::is_cm_duplicated_queued($data->coursemodule)) {
                return;
            }

            $course = get_course($data->course);
            $duedate = plagiarism_copyleaks_utils::get_course_module_duedate($data->coursemodule);
            $coursestartdate = plagiarism_copyleaks_utils::get_course_start_date($data->course);
            $updatedata = array(
                'tempCourseModuleId' => isset($data->plagiarism_copyleaks_tempcmid) ? $data->plagiarism_copyleaks_tempcmid : null,
                'courseModuleId' => $data->coursemodule,
                'name' => $data->name,
                'moduleName' => $data->modulename,
                'courseId' => $data->course,
                'courseName' => $course->fullname,
                'dueDate' => $duedate,
                'courseStartDate' => $coursestartdate
            );

            try {
                $cl = new plagiarism_copyleaks_comms();
                $cl->upsert_course_module($updatedata);
            } catch (plagiarism_copyleaks_exception $ex) {
                $errormessage = get_string('clfailtosavedata', 'plagiarism_copyleaks');
                plagiarism_copyleaks_logs::add($errormessage . ': ' . $ex->getMessage(), 'API_ERROR');
                throw new moodle_exception($errormessage);
            } catch (plagiarism_copyleaks_auth_exception $ex) {
                throw new moodle_exception(get_string('clinvalidkeyorsecret', 'plagiarism_copyleaks'));
            }
        }

        plagiarism_copyleaks_moduleconfig::set_module_config(
            $data->coursemodule,
            $data->plagiarism_copyleaks_enable,
            isset($data->plagiarism_copyleaks_draftsubmit) ? $data->plagiarism_copyleaks_draftsubmit : 0,
            isset($data->plagiarism_copyleaks_reportgen) ? $data->plagiarism_copyleaks_reportgen : 0,
            $data->plagiarism_copyleaks_allowstudentaccess
        );

    }

    /**
     * If plugin is enabled then Show the Copyleaks settings form.
     *
     * TODO: This code needs to be moved for 4.3 as the method will be completely removed from core.
     * See https://tracker.moodle.org/browse/MDL-67526
     *
     * @param MoodleQuickForm $mform
     * @param stdClass $context
     * @param string $modulename
     */
    public function get_form_elements_module($mform, $context, $modulename = "") {
        global $DB, $CFG, $OUTPUT;

        // This is a bit of a hack and untidy way to ensure the form elements aren't displayed,
        // Twice. This won't be needed once this method goes away.
        static $settingsdisplayed;
        if ($settingsdisplayed) {
            return;
        }

        if (has_capability('plagiarism/copyleaks:enable', $context)) {

            // Return no form if the plugin isn't configured or not enabled.
            if (empty($modulename) || !plagiarism_copyleaks_pluginconfig::is_plugin_configured($modulename)) {
                return;
            }

            // Copyleaks Settings.
            $mform->addElement(
                'header',
                'plagiarism_copyleaks_defaultsettings',
                get_string('clscoursesettings', 'plagiarism_copyleaks')
            );

            $cmid = optional_param('update', null, PARAM_INT);
            $addparam = optional_param('add', null, PARAM_TEXT);
            $isnewactivity = isset($addparam) && $addparam != "0";

            if (!$isnewactivity && plagiarism_copyleaks_dbutils::is_cm_duplicated_queued($cmid)) {
                $pendingduplication = html_writer::tag(
                    'div',
                    $OUTPUT->pix_icon(
                        'copyleaks-spinner',
                        null,
                        'plagiarism_copyleaks',
                        array('class' => 'cls-icon-no-margin')
                    ) .
                        html_writer::tag(
                            'div',
                            get_string('clpendingduplication', 'plagiarism_copyleaks'),
                        ),
                    array('class' => 'copyleaks-text-gray cls-gap-eight-container')
                );
                $mform->addElement('html', $pendingduplication . '<br>');
            } else {

                if (!$isnewactivity && plagiarism_copyleaks_dbutils::is_cm_duplicated_error($cmid)) {
                    $cmduplicationerror = plagiarism_copyleaks_dbutils::get_cm_duplicated_error_message($cmid);
                    $duplicationerror = html_writer::tag(
                        'div',
                        $OUTPUT->pix_icon(
                            'copyleaks-error',
                            null,
                            'plagiarism_copyleaks',
                            array('class' => 'cls-icon-no-margin')
                        ) .
                            html_writer::tag(
                                'div',
                                get_string('clfailedduplication', 'plagiarism_copyleaks') . ": " . $cmduplicationerror,
                            ),
                        array('class' => 'copyleaks-text-warn cls-gap-eight-container')
                    );
                    $mform->addElement('html',  $duplicationerror);
                }

                // Database settings.
                $mform->addElement(
                    'advcheckbox',
                    'plagiarism_copyleaks_enable',
                    get_string('clenable', 'plagiarism_copyleaks')
                );

                // Add draft submission properties only if exists.
                if ($mform->elementExists('submissiondrafts')) {
                    $mform->addElement(
                        'advcheckbox',
                        'plagiarism_copyleaks_draftsubmit',
                        get_string("cldraftsubmit", "plagiarism_copyleaks")
                    );
                    $mform->addHelpButton(
                        'plagiarism_copyleaks_draftsubmit',
                        'cldraftsubmit',
                        'plagiarism_copyleaks'
                    );
                    $mform->disabledIf(
                        'plagiarism_copyleaks_draftsubmit',
                        'submissiondrafts',
                        'eq',
                        0
                    );
                }

                // Add due date properties only if exists.
                if ($mform->elementExists('duedate')) {
                    $genoptions = array(
                        0 => get_string('clgenereportimmediately', 'plagiarism_copyleaks'),
                        1 => get_string('clgenereportonduedate', 'plagiarism_copyleaks')
                    );
                    $mform->addElement(
                        'select',
                        'plagiarism_copyleaks_reportgen',
                        get_string("clreportgenspeed", "plagiarism_copyleaks"),
                        $genoptions
                    );
                }

                $mform->addElement(
                    'advcheckbox',
                    'plagiarism_copyleaks_allowstudentaccess',
                    get_string('clallowstudentaccess', 'plagiarism_copyleaks')
                );

                $savedvalues = $DB->get_records_menu('plagiarism_copyleaks_config', array('cm' => $cmid), '', 'name,value');
                if (count($savedvalues) > 0) {
                    // Add check for a new Course Module (for lower versions).
                    $mform->setDefault(
                        'plagiarism_copyleaks_enable',
                        isset($savedvalues['plagiarism_copyleaks_enable']) ? $savedvalues['plagiarism_copyleaks_enable'] : 0
                    );

                    $draftsubmit = isset($savedvalues['plagiarism_copyleaks_draftsubmit']) ?
                        $savedvalues['plagiarism_copyleaks_draftsubmit'] : 0;

                    $mform->setDefault('plagiarism_copyleaks_draftsubmit', $draftsubmit);
                    if (isset($savedvalues['plagiarism_copyleaks_reportgen'])) {
                        $mform->setDefault('plagiarism_copyleaks_reportgen', $savedvalues['plagiarism_copyleaks_reportgen']);
                    }
                    if (isset($savedvalues['plagiarism_copyleaks_allowstudentaccess'])) {
                        $mform->setDefault(
                            'plagiarism_copyleaks_allowstudentaccess',
                            $savedvalues['plagiarism_copyleaks_allowstudentaccess']
                        );
                    }
                } else {
                    $mform->setDefault('plagiarism_copyleaks_enable', false);
                    $mform->setDefault('plagiarism_copyleaks_draftsubmit', 0);
                    $mform->setDefault('plagiarism_copyleaks_reportgen', 0);
                    $mform->setDefault('plagiarism_copyleaks_allowstudentaccess', 0);
                }

                $settingslinkparams = "?";
                $courseid = optional_param('course', 0, PARAM_INT);

                if ($isnewactivity) {
                    $cmid = plagiarism_copyleaks_utils::get_copyleaks_temp_course_module_id("$courseid");
                    $mform->addElement(
                        'hidden',
                        'plagiarism_copyleaks_tempcmid',
                        "$cmid"

                    );
                    // Need to set type for Moodle's older version.
                    $mform->setType('plagiarism_copyleaks_tempcmid', PARAM_INT);
                    $settingslinkparams = $settingslinkparams . "isnewactivity=$isnewactivity&courseid=$courseid&";
                }

                $settingslinkparams = $settingslinkparams . "cmid=$cmid&modulename=$modulename";

                $btn = plagiarism_copyleaks_utils::get_copyleaks_settings_button_link($settingslinkparams, false, $cmid);
                $mform->addElement('html', $btn);

                $cm = get_coursemodule_from_id('', $cmid);
                $isanalyticsdisabled = $isnewactivity || !plagiarism_copyleaks_moduleconfig::is_module_enabled($cm->modname, $cmid);
                $btn = plagiarism_copyleaks_utils::get_copyleaks_analytics_button_link($cmid,  $isanalyticsdisabled);
                $mform->addElement('html', $btn);

            }

            $settingsdisplayed = true;
        }
    }

    /**
     * hook to allow a disclosure to be printed notifying users what will happen with their submission
     * @param int $cmid - course module id
     * @return string
     */
    public function print_disclosure($cmid) {
        global $DB, $USER;

        // Get course module.
        $cm = get_coursemodule_from_id('', $cmid);

        // Get course module copyleaks settings.
        $clmodulesettings = $DB->get_records_menu(
            'plagiarism_copyleaks_config',
            array('cm' => $cmid),
            '',
            'name,value'
        );

        // Check if Copyleaks plugin is enabled for this module.
        $moduleclenabled = plagiarism_copyleaks_pluginconfig::is_plugin_configured('mod_' . $cm->modname);
        if (empty($clmodulesettings['plagiarism_copyleaks_enable']) || empty($moduleclenabled)) {
            return "";
        }

        $config = plagiarism_copyleaks_pluginconfig::admin_config();

        $isuseragreed = plagiarism_copyleaks_dbutils::is_user_eula_uptodate($USER->id);

        if (!$isuseragreed) {
            if (isset($config->plagiarism_copyleaks_studentdisclosure)) {
                $clstudentdisclosure = $config->plagiarism_copyleaks_studentdisclosure;
            } else {
                $clstudentdisclosure = get_string('clstudentdisclosuredefault', 'plagiarism_copyleaks');
            }
        } else {
            $clstudentdisclosure = get_string('clstudentdagreedtoeula', 'plagiarism_copyleaks');
        }

        $contents = format_text($clstudentdisclosure, FORMAT_MOODLE, array("noclean" => true));
        if (!$isuseragreed) {
            $checkbox = "<input type='checkbox' id='cls_student_disclosure'>" .
                "<label for='cls_student_disclosure' class='copyleaks-student-disclosure-checkbox'>$contents</label>";
            $output = html_writer::tag('div', $checkbox, array('class' => 'copyleaks-student-disclosure '));
            $output .= html_writer::tag(
                'script',
                "(function disableInput() {" .
                    "setTimeout(() => {" .
                    "var checkbox = document.getElementById('cls_student_disclosure');" .
                    "var btn = document.getElementById('id_submitbutton');" .
                    "btn.disabled = true;" .
                    "var intrval = setInterval(() => {" .
                    "if(checkbox.checked){" .
                    "btn.disabled = false;" .
                    "}else{" .
                    "btn.disabled = true;" .
                    "}" .
                    "}, 1000)" .
                    "}, 500);" .
                    "}());",
                null
            );
        } else {
            $output = html_writer::tag('div', $contents, array('class' => 'copyleaks-student-disclosure'));
        }

        return $output;
    }

    /**
     * hook to allow status of submitted files to be updated - called on grading/report pages.
     * @param object $course - full Course object
     * @param object $cm - full cm object
     */
    public function update_status($course, $cm) {
        // Called at top of submissions/grading pages - allows printing of admin style links or updating status.
    }
}

/**
 * This enables a plugin to insert a chunk of html at the start of the html document.
 * Typical use cases include some sort of alert notification, but in many cases the Notifications may be a better fit.
 * It MUST return a string containing a well formed chunk of html, or at minimum an empty string.
 */
function plagiarism_copyleaks_before_standard_top_of_body_html() {
    return "";
}

/**
 * Add the Copyleaks settings form to an add/edit activity page.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 * @return type
 */
/**
 * @var mixed $course
 */
function plagiarism_copyleaks_coursemodule_standard_elements($formwrapper, $mform) {
    $copyleaksplugin = new plagiarism_plugin_copyleaks();
    $course = $formwrapper->get_course();
    $context = context_course::instance($course->id);
    $modulename = $formwrapper->get_current()->modulename;

    $copyleaksplugin->get_form_elements_module(
        $mform,
        $context,
        isset($modulename) ? 'mod_' . $modulename : ''
    );
}

/**
 * Handle saving data from the Copyleaks settings form.
 *
 * @param stdClass $data
 * @param stdClass $course
 */
function plagiarism_copyleaks_coursemodule_edit_post_actions($data, $course) {
    $copyleaksplugin = new plagiarism_plugin_copyleaks();

    $copyleaksplugin->save_form_elements($data);

    return $data;
}
