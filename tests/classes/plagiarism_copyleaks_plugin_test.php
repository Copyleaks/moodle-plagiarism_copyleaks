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
require_once($CFG->dirroot . '/plagiarism/copyleaks/tests/classes/plagiarism_copyleaks_base_test_lib.php');

class plagiarism_copyleaks_plugin_test extends plagiarism_copyleaks_base_test_lib {
    private $activitytype = 'assign';

    public function setUp(): void {
    }

    public function test_plugin_dectivated_when_submit() {
        $this->resetAfterTest();
        $this->construct_copyleaks_parent_test($this->activitytype, true, false);

        $this->assertFalse(plagiarism_copyleaks_pluginconfig::is_plugin_configured($this->activitytype));
        $this->assertFalse(plagiarism_copyleaks_moduleconfig::is_module_enabled($this->activitytype, $this->activity->cmid));

        // Act.
        $submissiondata = $this->submit_to_assignment();
        $this->queue_submission_to_copyleaks($submissiondata['pathnamehash'], $submissiondata['itemid'], 'file_uploaded', $this->activitytype);

        $this->assertFalse(plagiarism_copyleaks_test_lib::get_copyleaks_file($submissiondata['pathnamehash']));
    }

    public function test_plugin_dectivated_for_activity_when_submit() {
        $this->resetAfterTest();
        $this->construct_copyleaks_parent_test($this->activitytype);

        $this->create_activity_and_enroll_user($this->activitytype, false);
        $this->assertTrue(plagiarism_copyleaks_pluginconfig::is_plugin_configured('mod_' . $this->activitytype));
        $this->assertFalse(plagiarism_copyleaks_moduleconfig::is_module_enabled($this->activitytype, $this->activity->cmid));

        // Act.
        $submissiondata = $this->submit_to_assignment();
        $this->queue_submission_to_copyleaks($submissiondata['pathnamehash'], $submissiondata['itemid'], 'file_uploaded', $this->activitytype);

        $this->assertFalse(plagiarism_copyleaks_test_lib::get_copyleaks_file($submissiondata['pathnamehash']));
    }

    public function test_eula_not_accepted_and_submit() {
        $this->resetAfterTest();
        $this->construct_copyleaks_parent_test($this->activitytype, false);

        $this->assertTrue(plagiarism_copyleaks_pluginconfig::is_plugin_configured('mod_' . $this->activitytype));
        $this->assertTrue(plagiarism_copyleaks_moduleconfig::is_module_enabled($this->activitytype, $this->activity->cmid));
        $this->assertFalse(plagiarism_copyleaks_dbutils::is_user_eula_uptodate($this->user->id));

        $submissiondata = $this->submit_to_assignment();
        $this->queue_submission_to_copyleaks($submissiondata['pathnamehash'], $submissiondata['itemid'], 'file_uploaded', $this->activitytype);

        // The submission handler should update the eula version and submit the file.
        $this->assertTrue(plagiarism_copyleaks_dbutils::is_user_eula_uptodate($this->user->id));
        $this->assertNotNull(plagiarism_copyleaks_test_lib::get_copyleaks_file($submissiondata['pathnamehash']));
        plagiarism_copyleaks_test_lib::execute_send_submission_task();

        $this->assert_copyleaks_result($submissiondata['pathnamehash']);
    }
}
