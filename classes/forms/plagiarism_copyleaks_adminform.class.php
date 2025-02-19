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
 * copyleaks_setupform.class.php - Plugin setup form for plagiarism_copyleaks component
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/plagiarism/copyleaks/lib.php');
require_once($CFG->dirroot . '/lib/formslib.php');

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_pluginconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_moduleconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_authexception.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_exception.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_ratelimitexception.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_undermaintenanceexception.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_utils.class.php');

/**
 * Copyleaks admin setup form
 */
class plagiarism_copyleaks_adminform extends moodleform {
    /**
     * Define the form
     * */
    public function definition() {
        global $CFG;
        $mform = &$this->_form;

        // Plugin Configurations.
        $mform->addElement(
            'header',
            'plagiarism_copyleaks_adminconfigheader',
            get_string('cladminconfig', 'plagiarism_copyleaks', null, true)
        );
        $mform->addElement(
            'html',
            get_string('clpluginintro', 'plagiarism_copyleaks')
        );

        // Get all modules that support plagiarism plugin.
        $plagiarismmodules = array_keys(core_component::get_plugin_list('mod'));
        $supportedmodules = ['assign', 'forum', 'workshop', 'quiz'];
        foreach ($plagiarismmodules as $module) {
            // For now we only support assignments.
            if (in_array($module, $supportedmodules) && plugin_supports('mod', $module, FEATURE_PLAGIARISM)) {
                array_push($supportedmodules, $module);
                $mform->addElement(
                    'advcheckbox',
                    'plagiarism_copyleaks_mod_' . $module,
                    get_string('clenablemodulefor', 'plagiarism_copyleaks', ucfirst($module == 'assign' ? 'Assignment' : $module))
                );
            }
        }

        $mform->addElement(
            'textarea',
            'plagiarism_copyleaks_studentdisclosure',
            get_string('clstudentdisclosure', 'plagiarism_copyleaks')
        );
        $mform->addHelpButton(
            'plagiarism_copyleaks_studentdisclosure',
            'clstudentdisclosure',
            'plagiarism_copyleaks'
        );

        $elem = <<<HTML
        <style>
            .slider-container {
                position: relative;
                width: 320px;
                margin-top: 10px;
            }
        
            .range-slider {
                position: relative;
                width: 100%;
                height: 8px;
                background: linear-gradient(to right, red 0%, red 30%, yellow 30%, yellow 70%, green 70%, green 100%);
                border-radius: 5px;
            }
        
            input[type="range"] {
                position: absolute;
                width: 100%;
                height: 8px;
                -webkit-appearance: none;
                background: transparent;
                pointer-events: all;
                top: 1px;
            }
        
            input[type="range"]::-webkit-slider-thumb {
                -webkit-appearance: none;
                width: 15px;
                height: 15px;
                background: white;
                border: 3px solid black;
                border-radius: 50%;
                cursor: pointer;
                pointer-events: all;
                position: relative;
                z-index: 3;
            }
        </style>
        
        <div class="slider-container">
            <div class="range-slider" id="sliderRange"></div>
        
            <!-- Use the correct Moodle field names so values are submitted -->
            <input type="range" min="0" max="100" value="30" id="slider1" name="val1">
            <input type="range" min="0" max="100" value="70" id="slider2" name="val2">
        
            <div style="margin-top: 10px;">
                <p>First Threshold: <span id="range1">0 - 30</span></p>
                <p>Second Threshold: <span id="range2">31 - 70</span></p>
                <p>Third Threshold: <span id="range3">71 - 100</span></p>
            </div>
        </div>
        
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                var slider1 = document.getElementById("slider1");
                var slider2 = document.getElementById("slider2");
                var sliderRange = document.getElementById("sliderRange");
        
                var range1 = document.getElementById("range1");
                var range2 = document.getElementById("range2");
                var range3 = document.getElementById("range3");
        
                function updateSlider() {
                    var val1 = parseInt(slider1.value);
                    var val2 = parseInt(slider2.value);
        
                    if (val1 >= val2) {
                        slider1.value = val2 - 1;
                        val1 = val2 - 1;
                    }
        
                    var percent1 = (val1 / 100) * 100;
                    var percent2 = (val2 / 100) * 100;
        
                    // Update threshold text
                    range1.textContent = "0 - " + val1;
                    range2.textContent = (val1 + 1) + " - " + val2;
                    range3.textContent = (val2 + 1) + " - 100";
        
                    // Update gradient background
                    sliderRange.style.background = "linear-gradient(to right, " +
                        "red 0%, " +
                        "red " + percent1 + "%, " +
                        "yellow " + percent1 + "%, " +
                        "yellow " + percent2 + "%, " +
                        "green " + percent2 + "%, " +
                        "green 100%)";
                }
        
                slider1.addEventListener("input", updateSlider);
                slider2.addEventListener("input", updateSlider);
        
                updateSlider();
            });
        </script>
        HTML;

        // Add the section with the label and HTML
        $mform->addElement(
            'static',
            'plagiarism_copyleaks_threshhold',
            get_string('clsthreshhold', 'plagiarism_copyleaks'),
            $elem // The HTML content
        );


        $mform->addElement('hidden', 'val1', '');
        $mform->setType('val1', PARAM_RAW);

        $mform->addElement('hidden', 'val2', '');
        $mform->setType('val2', PARAM_RAW);

        // Copyleaks Account Configurations.
        $mform->addElement(
            'header',
            'plagiarism_copyleaks_accountconfigheader',
            get_string('claccountconfig', 'plagiarism_copyleaks')
        );
        $mform->setExpanded('plagiarism_copyleaks_accountconfigheader');
        // Thos settings will be save on Moodle database.
        $mform->addElement(
            'text',
            'plagiarism_copyleaks_apiurl',
            get_string('clapiurl', 'plagiarism_copyleaks')
        );
        $mform->setType('plagiarism_copyleaks_apiurl', PARAM_TEXT);
        $mform->addElement(
            'text',
            'plagiarism_copyleaks_key',
            get_string('claccountkey', 'plagiarism_copyleaks')
        );
        $mform->setType('plagiarism_copyleaks_key', PARAM_TEXT);
        $mform->addElement(
            'passwordunmask',
            'plagiarism_copyleaks_secret',
            get_string('claccountsecret', 'plagiarism_copyleaks')
        );

        if (\plagiarism_copyleaks_comms::test_copyleaks_connection('admin_settings_page', true)) {
            $btn = plagiarism_copyleaks_utils::get_copyleaks_settings_button_link(null, true);
            $mform->addElement('html', $btn);
        }

        $this->add_action_buttons();
    }

    /**
     * form custom validations
     * @param mixed $data
     * @param mixed $files
     */
    public function validation($data, $files) {
        $newconfigsecret = $data["plagiarism_copyleaks_secret"];
        $newconfigkey = $data["plagiarism_copyleaks_key"];
        $newapiurl = $data["plagiarism_copyleaks_apiurl"];

        $config = plagiarism_copyleaks_pluginconfig::admin_config();
        if (
            isset($config->plagiarism_copyleaks_secret) &&
            isset($config->plagiarism_copyleaks_key) &&
            isset($config->plagiarism_copyleaks_apiurl)
        ) {
            $secret = $config->plagiarism_copyleaks_secret;
            $key = $config->plagiarism_copyleaks_key;
            $apiurl = $config->plagiarism_copyleaks_apiurl;

            if ($secret != $newconfigsecret || $key != $newconfigkey || $apiurl != $newapiurl) {
                try {
                    $cljwttoken = plagiarism_copyleaks_comms::login_to_copyleaks($newapiurl, $newconfigkey, $newconfigsecret, true);
                    if (isset($cljwttoken)) {
                        return [];
                    } else {
                        return (array)[
                            "plagiarism_copyleaks_secret" => get_string('clinvalidkeyorsecret', 'plagiarism_copyleaks'),
                        ];
                    }
                } catch (plagiarism_copyleaks_exception $ex) {
                    switch ($ex->getCode()) {
                        case 404:
                            return (array)[
                                "plagiarism_copyleaks_secret" => get_string('clinvalidkeyorsecret', 'plagiarism_copyleaks'),
                            ];
                            break;
                        case 0:
                            return (array)[
                                "plagiarism_copyleaks_apiurl" => $ex->getMessage(),
                            ];
                            break;
                        default:
                            throw $ex;
                            break;
                    }
                } catch (plagiarism_copyleaks_auth_exception $ex) {
                    return (array)[
                        "plagiarism_copyleaks_secret" => get_string('clinvalidkeyorsecret', 'plagiarism_copyleaks'),
                    ];
                }
            }
        } else {
            if (!isset($newconfigsecret) || !isset($newconfigkey) || empty($newconfigkey) || empty($newconfigsecret)) {
                return (array)[
                    "plagiarism_copyleaks_secret" => get_string('clinvalidkeyorsecret', 'plagiarism_copyleaks'),
                ];
            }
        }
        return [];
    }

    /**
     * Init the form data form both DB and Copyleaks API
     */
    public function init_form_data() {
        $cache = cache::make('core', 'config');
        $cache->delete('plagiarism_copyleaks');

        // Get moodle admin config.
        $plagiarismsettings = (array) plagiarism_copyleaks_pluginconfig::admin_config();

        if (
            !isset($plagiarismsettings['plagiarism_copyleaks_apiurl']) ||
            empty($plagiarismsettings['plagiarism_copyleaks_apiurl'])
        ) {
            $plagiarismsettings['plagiarism_copyleaks_apiurl'] = plagiarism_copyleaks_comms::copyleaks_api_url();
        }

        $cldbdefaultconfig = plagiarism_copyleaks_moduleconfig::get_modules_default_config();

        if (!isset($plagiarismsettings["plagiarism_copyleaks_studentdisclosure"])) {
            $plagiarismsettings["plagiarism_copyleaks_studentdisclosure"] =
                get_string('clstudentdisclosuredefault', 'plagiarism_copyleaks');
        }

        $this->set_data($plagiarismsettings);
    }

    /**
     * Display the form to admins
     */
    public function display() {
        ob_start();
        parent::display();
        $form = ob_get_contents();
        ob_end_clean();
        return $form;
    }

    /**
     * Save form data
     * @param stdClass $data
     */
    public function save(stdClass $data) {
        global $DB, $CFG;

        // Save admin settings.
        $configproperties = plagiarism_copyleaks_pluginconfig::admin_config_properties();
        foreach ($configproperties as $property) {
            plagiarism_copyleaks_pluginconfig::set_admin_config($data, $property);
        }

        // Check if plugin is enabled.
        $plagiarismmodules = array_keys(core_component::get_plugin_list('mod'));
        $pluginenabled = 0;
        foreach ($plagiarismmodules as $module) {
            if (plugin_supports('mod', $module, FEATURE_PLAGIARISM)) {
                $property = "plagiarism_copyleaks_mod_" . $module;
                $ismoduleenabled = (!empty($data->$property)) ? $data->$property : 0;
                if ($ismoduleenabled) {
                    $pluginenabled = 1;
                }
            }
        }

        // Set if Copyleaks plugin is enabled.
        set_config('enabled', $pluginenabled, 'plagiarism_copyleaks');
        if ($CFG->branch < 39) {
            set_config('copyleaks_use', $pluginenabled, 'plagiarism');
        }

        $cache = cache::make('core', 'config');
        $cache->delete('plagiarism_copyleaks');
        if (\plagiarism_copyleaks_comms::test_copyleaks_connection('admin_settings_page', true)) {
            $config = plagiarism_copyleaks_pluginconfig::admin_config();
            $domain = (new moodle_url('/'))->out(false);
            $domain = rtrim($domain, '/');
            $pluginversion = get_config('plagiarism_copyleaks', 'version');
            $plugindata = (array)[
                'domain' => $domain,
                'pluginVersion' => $pluginversion,
            ];
            plagiarism_copyleaks_dbutils::queue_copyleaks_integration_data_sync_request(
                $plugindata,
                $config->plagiarism_copyleaks_key
            );
        }
    }
}
