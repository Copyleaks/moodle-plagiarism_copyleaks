
Warning: Module 'openssl' already loaded in Unknown on line 0

Warning: Module 'openssl' already loaded in Unknown on line 0
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
                // Mahara setup.
                if ($module == 'assign') {
                    // Check if maharaws is enabled.
                    $pm = \core_plugin_manager::instance();
                    $submissionplugins = $pm->get_enabled_plugins('assignsubmission');
                    if (!empty($submissionplugins) && key_exists('maharaws', $submissionplugins)) {
                        $mform->addElement('html', '<div id="copyleaks-mahara-settings" style="display: block;padding: 15px;border: 1px solid #dfdfdf;border-radius: 10px;margin-bottom:20px;">');

                        $mform->addElement('html', '<h5 style="margin-bottom: 25px;margin-top: 10px;font-weight: 400;">'.get_string('clsetmahara', 'plagiarism_copyleaks').'</h5>');

                        $mform->addElement('text', 'plagiarism_copyleaks_maharawsurl_' . $module,
                                get_string('clmaharawsurl', 'plagiarism_copyleaks'),
                                array('maxlength' => 255, 'size' => 50));
                        $mform->addHelpButton('plagiarism_copyleaks_maharawsurl_' . $module, 'clmaharawsurl', 'plagiarism_copyleaks');
                        $mform->setDefault('plagiarism_copyleaks_maharawsurl_' . $module, '');
                        $mform->setType('plagiarism_copyleaks_maharawsurl_' . $module, PARAM_URL);

                        $mform->addElement('text', 'plagiarism_copyleaks_maharawshtmllitekey_' . $module,
                                get_string('clmaharawshtmllitekey', 'plagiarism_copyleaks'),
                                array('maxlength' => 255, 'size' => 50));
                        $mform->addHelpButton('plagiarism_copyleaks_maharawshtmllitekey_' . $module, 'clmaharawshtmllitekey', 'plagiarism_copyleaks');
                        $mform->setDefault('plagiarism_copyleaks_maharawshtmllitekey_' . $module, '');
                        $mform->setType('plagiarism_copyleaks_maharawshtmllitekey_' . $module, PARAM_ALPHANUM);

                        $mform->addElement('text', 'plagiarism_copyleaks_maharawshtmllitesecret_' . $module,
                                get_string('clmaharawshtmllitesecret', 'plagiarism_copyleaks'),
                                array('maxlength' => 255, 'size' => 50));
                        $mform->addHelpButton('plagiarism_copyleaks_maharawshtmllitesecret_' . $module, 'clmaharawshtmllitesecret', 'plagiarism_copyleaks');
                        $mform->setDefault('plagiarism_copyleaks_maharawshtmllitesecret_' . $module, '');
                        $mform->setType('plagiarism_copyleaks_maharawshtmllitesecret_' . $module, PARAM_ALPHANUM);

                        $mform->addElement('text', 'plagiarism_copyleaks_maharawshtmllitetoken_' . $module,
                                get_string('clmaharawshtmllitetoken', 'plagiarism_copyleaks'),
                                array('maxlength' => 255, 'size' => 50));
                        $mform->addHelpButton('plagiarism_copyleaks_maharawshtmllitetoken_' . $module, 'clmaharawshtmllitetoken', 'plagiarism_copyleaks');
                        $mform->setDefault('plagiarism_copyleaks_maharawshtmllitetoken_' . $module, '');
                        $mform->setType('plagiarism_copyleaks_maharawshtmllitetoken_' . $module, PARAM_ALPHANUM);

                        $mform->addElement('html', '</div>'); // End wrapper
                        $mform->addElement('html', '
                        <script>
                            document.addEventListener("DOMContentLoaded", function () {
                                let checkbox = document.getElementById("id_plagiarism_copyleaks_mod_assign");
                                let settingsDiv = document.getElementById("copyleaks-mahara-settings");

                                function toggleSettings() {
                                    settingsDiv.style.display = checkbox.checked ? "block" : "none";
                                }

                                checkbox.addEventListener("change", toggleSettings);
                                toggleSettings(); // Initial state
                            });
                         </script>');
                    }
                }
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
