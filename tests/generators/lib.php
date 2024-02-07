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
 * @author    Gil Coehn <gilc@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_calendar\local\event\container;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/forms/plagiarism_copyleaks_adminform.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_moduleconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_dbutils.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_eventshandler.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
// require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/task/plagiarism_copyleaks_sendsubmissions.class.php');
// Get global class.

class plagiarism_copyleaks_test_lib extends advanced_testcase {

    /**
     * Sets copyleaks admin configuration for the test environment.
     */
    public static function set_admin_configuration($configallactivities) {
        $config = new stdClass();
        $config->plagiarism_copyleaks_mod_assign = $configallactivities;
        $config->plagiarism_copyleaks_mod_forum = $configallactivities;
        $config->plagiarism_copyleaks_mod_quiz = $configallactivities;
        $config->plagiarism_copyleaks_mod_workshop = $configallactivities;
        $config->plagiarism_copyleaks_studentdisclosure = "By submitting your files...";
        $config->mform_isexpanded_id_plagiarism_copyleaks_accountconfigheader = "1";
        $config->plagiarism_copyleaks_apiurl = "https://gil.eu.ngrok.io";
        $config->plagiarism_copyleaks_key = "63ded63d-6ee4-4073-8cec-116c7ba60d2e";
        $config->plagiarism_copyleaks_secret = "CEE6C81185F8FC3EFC71C0513DF650ABCFB973D1AA5E63E0A303C6F20846A0AA";
        $config->submitbutton = "Save changes";

        $clslib = new plagiarism_copyleaks_adminform();
        $clslib->save($config);
    }

    public static function execute_send_submission_task() {
        $submitter = new \plagiarism_copyleaks\task\plagiarism_copyleaks_sendsubmissions();
        $submitter->execute();
    }

    /**
     * Activate Copyleaks plugin by course module id.
     * @param string $cmid - course module id.
     */
    public static function enable_copyleaks_plugin_for_module($cmid) {
        plagiarism_copyleaks_moduleconfig::set_module_config($cmid, 1, 0, 0, 1);
    }


    public static function get_copyleaks_file($identifier) {
        global $DB;
        return $DB->get_record(
            'plagiarism_copyleaks_files',
            array(
                'identifier' => $identifier
            ),
            '*'
        );
    }
}
