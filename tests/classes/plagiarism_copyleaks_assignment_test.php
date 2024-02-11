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

class plagiarism_copyleaks_assignment_test extends plagiarism_copyleaks_base_test_lib {

    public function setUp(): void {
        $this->construct_copyleaks_parent_test();
    }

    public function test_assignment_submission() {
        // Arrange.
        $this->resetAfterTest();
        $this->assertTrue(plagiarism_copyleaks_moduleconfig::is_module_enabled('assign', $this->activity->cmid));
        $this->assertTrue(plagiarism_copyleaks_dbutils::is_user_eula_uptodate($this->user->id));

        // Act.
        $submissiondata = $this->submit_to_assignment();
        $this->queue_submission_to_copyleaks($submissiondata['pathnamehash'], $submissiondata['itemid'], 'file_uploaded', 'assign');

        $this->assertNotNull(plagiarism_copyleaks_test_lib::get_copyleaks_file($submissiondata['pathnamehash']));
        plagiarism_copyleaks_test_lib::execute_send_submission_task();

        // Assert.
        $this->assert_copyleaks_result($submissiondata['pathnamehash']);
    }

    /**
     * Ensure that a teacher with the editothersubmission capability can submit on behalf of a student.
     */
    public function test_teacher_submit_behalf_student() {
        $this->resetAfterTest();
        $this->assertTrue(plagiarism_copyleaks_moduleconfig::is_module_enabled('assign', $this->activity->cmid));
        $this->assertTrue(plagiarism_copyleaks_dbutils::is_user_eula_uptodate($this->user->id));

        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('mod/assign:editothersubmission', CAP_ALLOW, $roleid, $this->context->id);
        role_assign($roleid, $teacher->id, $this->context->id);

        $this->setUser($teacher);

        // Act.
        $submissiondata = $this->submit_to_assignment();
        $this->queue_submission_to_copyleaks($submissiondata['pathnamehash'], $submissiondata['itemid'], 'file_uploaded', 'assign');

        $this->assertNotNull(plagiarism_copyleaks_test_lib::get_copyleaks_file($submissiondata['pathnamehash']));
        plagiarism_copyleaks_test_lib::execute_send_submission_task();

        // Assert.
        $this->assert_copyleaks_result($submissiondata['pathnamehash']);
    }
}
