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

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_assignmodule.class.php');

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
        // Check if plugin is configured and enabled.
        if (empty($data->modulename) || !plagiarism_copyleaks_pluginconfig::is_plugin_configured('mod_' . $data->modulename)) {
            return;
        }

        // Save settings to Copyleaks API.
        try {
            // Get copyleaks api course module settings.
            $cl = new plagiarism_copyleaks_comms();
            $copyleakssettings = $cl->get_course_module_settings($data->coursemodule);
            if (isset($copyleakssettings)) {
                // Map to copyleaks api model.
                $clfilters = $copyleakssettings->filters;
                $clexternalsources = $copyleakssettings->externalSources;
                $clsearch = $copyleakssettings->search;
                $clinternalsources = $copyleakssettings->internalSources;
                $matchtypes = $copyleakssettings->matchTypes;
                $config = $copyleakssettings->config;

                $clfilters->references = $data->plagiarism_copyleaks_ignorereferences === '1';
                $clfilters->quotes = $data->plagiarism_copyleaks_ignorequotes === '1';
                $clfilters->titles = $data->plagiarism_copyleaks_ignoretitles === '1';
                $clfilters->tableOfContent = $data->plagiarism_copyleaks_ignoreretableofcontents === '1';
                $clfilters->code->comments = $data->plagiarism_copyleaks_ignoreresourcecodecomments === '1';
                $clexternalsources->internet->enabled = $data->plagiarism_copyleaks_scaninternet === '1';
                $clexternalsources->safeSearch = $data->plagiarism_copyleaks_enablesafesearch === '1';
                $clsearch->cheatDetection = $data->plagiarism_copyleaks_enablecheatdetection === '1';
                $matchtypes->relatedMeaningCheck = $data->plagiarism_copyleaks_checkforparaphrase === '1';
                $config->disableStudentInternalAccess = $data->plagiarism_copyleaks_disablestudentinternalaccess === '1';

                $scaninternaldatabase = $data->plagiarism_copyleaks_scaninternaldatabase === '1';
                if (isset($clinternalsources) && isset($clinternalsources->databases)) {
                    foreach ($clinternalsources->databases as $database) {
                        if (isset($database) && ($database->id == "INTERNAL_DATA_BASE" ||
                            $database->id == DEFAULT_DATABASE_COPYLEAKSDB_ID)) {
                            $database->includeOthersScans = $scaninternaldatabase;
                            $database->index = $scaninternaldatabase;
                            $database->includeUserScans = $scaninternaldatabase;
                            break;
                        }
                    }
                }

                // Save to Copyleaks API.
                $cl->save_course_module_settings($data->coursemodule, $data->modulename, $data->name, $copyleakssettings);
                $showstudentresultsinfo = plagiarism_copyleaks_moduleconfig::is_allow_student_results_info();

                plagiarism_copyleaks_moduleconfig::set_module_config(
                    $data->plagiarism_copyleaks_ignorereferences,
                    $data->plagiarism_copyleaks_ignorequotes,
                    $data->plagiarism_copyleaks_ignoretitles,
                    $data->plagiarism_copyleaks_ignoreretableofcontents,
                    $data->plagiarism_copyleaks_ignoreresourcecodecomments,
                    $data->plagiarism_copyleaks_scaninternet,
                    $data->plagiarism_copyleaks_scaninternaldatabase,
                    $data->plagiarism_copyleaks_enablesafesearch,
                    $data->plagiarism_copyleaks_enablecheatdetection,
                    $data->plagiarism_copyleaks_checkforparaphrase,
                    $data->plagiarism_copyleaks_disablestudentinternalaccess,
                    $showstudentresultsinfo,
                    $data->coursemodule,
                    $data->plagiarism_copyleaks_enable,
                    isset($data->plagiarism_copyleaks_draftsubmit) ? $data->plagiarism_copyleaks_draftsubmit : 0,
                    isset($data->plagiarism_copyleaks_reportgen) ? $data->plagiarism_copyleaks_reportgen : 0,
                    $data->plagiarism_copyleaks_allowstudentaccess
                );
            }
        } catch (plagiarism_copyleaks_exception $ex) {
            $errormessage = get_string('clfailtosavedata', 'plagiarism_copyleaks');
            plagiarism_copyleaks_logs::add($errormessage . ': ' . $ex->getMessage(), 'API_ERROR');
            throw new moodle_exception($errormessage);
        } catch (plagiarism_copyleaks_auth_exception $ex) {
            throw new moodle_exception(get_string('clinvalidkeyorsecret', 'plagiarism_copyleaks'));
        }
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
        global $DB, $CFG;
        // This is a bit of a hack and untidy way to ensure the form elements aren't displayed,
        // twice. This won't be needed once this method goes away.
        // TODO: Remove once this method goes away.
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
                'plagiarism_copyleaks_disablestudentinternalaccess',
                get_string('cldisablestudentinternalaccess', 'plagiarism_copyleaks')
            );

            $mform->addElement(
                'advcheckbox',
                'plagiarism_copyleaks_allowstudentaccess',
                get_string('clallowstudentaccess', 'plagiarism_copyleaks')
            );

            // Copyleaks API settings.
            $mform->addElement(
                'advcheckbox',
                'plagiarism_copyleaks_ignorereferences',
                get_string('clignorereferences', 'plagiarism_copyleaks')
            );
            $mform->addElement(
                'advcheckbox',
                'plagiarism_copyleaks_ignorequotes',
                get_string('clignorequotes', 'plagiarism_copyleaks')
            );
            $mform->addElement(
                'advcheckbox',
                'plagiarism_copyleaks_ignoretitles',
                get_string('clignoretitles', 'plagiarism_copyleaks')
            );
            $mform->addElement(
                'advcheckbox',
                'plagiarism_copyleaks_ignoreretableofcontents',
                get_string('clignoreretableofcontents', 'plagiarism_copyleaks')
            );
            $mform->addElement(
                'advcheckbox',
                'plagiarism_copyleaks_ignoreresourcecodecomments',
                get_string('clignoreresourcecodecomments', 'plagiarism_copyleaks')
            );
            $mform->addElement(
                'advcheckbox',
                'plagiarism_copyleaks_scaninternet',
                get_string('clscaninternet', 'plagiarism_copyleaks')
            );
            $mform->addElement(
                'advcheckbox',
                'plagiarism_copyleaks_scaninternaldatabase',
                get_string('clscaninternaldatabase', 'plagiarism_copyleaks')
            );
            $mform->addElement(
                'advcheckbox',
                'plagiarism_copyleaks_enablesafesearch',
                get_string('clenablesafesearch', 'plagiarism_copyleaks')
            );
            $mform->addElement(
                'advcheckbox',
                'plagiarism_copyleaks_checkforparaphrase',
                get_string('clcheckforparaphrase', 'plagiarism_copyleaks')
            );
            $mform->addElement(
                'advcheckbox',
                'plagiarism_copyleaks_enablecheatdetection',
                get_string('clenablecheatdetection', 'plagiarism_copyleaks')
            );

            $cmid = optional_param('update', null, PARAM_INT);
            $savedvalues = $DB->get_records_menu('plagiarism_copyleaks_config', array('cm' => $cmid), '', 'name,value');
            if (count($savedvalues) > 0) {
                $mform->setDefault('plagiarism_copyleaks_enable', $savedvalues['plagiarism_copyleaks_enable']);

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

            $cmconfig = plagiarism_copyleaks_moduleconfig::get_module_config($cmid);
            $mform->setDefault(
                "plagiarism_copyleaks_disablestudentinternalaccess",
                $cmconfig["plagiarism_copyleaks_disablestudentinternalaccess"]
            );
            $mform->setDefault(
                "plagiarism_copyleaks_ignorereferences",
                $cmconfig["plagiarism_copyleaks_ignorereferences"]
            );
            $mform->setDefault(
                "plagiarism_copyleaks_ignorequotes",
                $cmconfig["plagiarism_copyleaks_ignorequotes"]
            );
            $mform->setDefault(
                "plagiarism_copyleaks_ignoretitles",
                $cmconfig["plagiarism_copyleaks_ignoretitles"]
            );
            $mform->setDefault(
                "plagiarism_copyleaks_ignoreretableofcontents",
                $cmconfig["plagiarism_copyleaks_ignoreretableofcontents"]
            );
            $mform->setDefault(
                "plagiarism_copyleaks_ignoreresourcecodecomments",
                $cmconfig["plagiarism_copyleaks_ignoreresourcecodecomments"]
            );
            $mform->setDefault(
                "plagiarism_copyleaks_scaninternet",
                $cmconfig["plagiarism_copyleaks_scaninternet"]
            );
            $mform->setDefault(
                "plagiarism_copyleaks_scaninternaldatabase",
                $cmconfig["plagiarism_copyleaks_scaninternaldatabase"]
            );
            $mform->setDefault(
                "plagiarism_copyleaks_enablesafesearch",
                $cmconfig["plagiarism_copyleaks_enablesafesearch"]
            );
            $mform->setDefault(
                "plagiarism_copyleaks_enablecheatdetection",
                $cmconfig["plagiarism_copyleaks_enablecheatdetection"]
            );
            $mform->setDefault(
                "plagiarism_copyleaks_checkforparaphrase",
                $cmconfig["plagiarism_copyleaks_checkforparaphrase"]
            );

            if (isset($cmid) && isset($modulename)) {
                $mform->addElement(
                    'html',
                    "<div class='form-group row'>" .
                        "<div class='col-md-3'></div>" .
                        "<div class='col-md-9'>" .
                        html_writer::link(
                            "$CFG->wwwroot/plagiarism/copyleaks/plagiarism_copyleaks_repositories.php?" .
                                "cmid=$cmid&modulename=$modulename",
                            get_string('cleditrepositories', 'plagiarism_copyleaks'),
                            array('title' => get_string('cleditrepositories', 'plagiarism_copyleaks'))
                        )
                        . "</div>" .
                        "</div>"
                );
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

        $isuseragreed = $DB->record_exists('plagiarism_copyleaks_users', array('userid' => $USER->id));
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
