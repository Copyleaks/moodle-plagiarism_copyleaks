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
 * settings.php - allows the admin to configure the plugin
 * @package   plagiarism_copyleaks
 * @author    Gil Cohen <gilc@copyleaks.com>
 * @copyright 2021 Copyleaks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/copyleaks/lib.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/tests/generators/lib.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_dbutils.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/tests/classes/copyleaks_base_test_lib.php');

class copyleaks_plugin_test extends copyleaks_base_test_lib {

    public function setUp(): void {
        $this->construct_copyleaks_parent_test('assign', true, false);
    }


    public function test_plugin_dectivated_when_submit() {
        $this->resetAfterTest();

        $this->assertFalse(plagiarism_copyleaks_pluginconfig::is_plugin_configured('assign'));
        $this->assertFalse(plagiarism_copyleaks_moduleconfig::is_module_enabled('assign', $this->activity->cmid));

        // Act.
        $submissiondata = $this->insert_file_record();
        $this->queue_submission_to_copyleaks($submissiondata['pathnamehash'], $submissiondata['itemid'], 'file_uploaded', 'assign');

        $this->assertFalse(plagiarism_copyleaks_test_lib::get_copyleaks_file($submissiondata['pathnamehash']));
    }

    public function test_plugin_dectivated_for_activity_when_submit() {
        $this->resetAfterTest();
        $this->construct_copyleaks_parent_test('assign', true, true);

        $newactivity = $this->getDataGenerator()->create_module('assign', array('course' => $this->course->id));
        $this->activity = $newactivity;
        $this->assertTrue(plagiarism_copyleaks_pluginconfig::is_plugin_configured('assign'));
        $this->assertFalse(plagiarism_copyleaks_moduleconfig::is_module_enabled('assign', $this->activity->cmid));

        // Act.
        $submissiondata = $this->insert_file_record();
        $this->queue_submission_to_copyleaks($submissiondata['pathnamehash'], $submissiondata['itemid'], 'file_uploaded', 'assign');

        $this->assertFalse(plagiarism_copyleaks_test_lib::get_copyleaks_file($submissiondata['pathnamehash']));
    }
}
