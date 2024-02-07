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
// define('CLI_SCRIPT', false);

global $CFG;
require_once($CFG->dirroot . '/plagiarism/copyleaks/lib.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/tests/generators/lib.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_dbutils.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/tests/classes/copyleaks_base_test_lib.php');

class copyleaks_quiz_test extends copyleaks_base_test_lib {

    public function setUp(): void {
        $this->construct_copyleaks_parent_test('quiz');
    }

    public function test_quiz_text_submission() {
        // Arrange.
        $this->resetAfterTest();
        $this->assertTrue(plagiarism_copyleaks_moduleconfig::is_module_enabled('quiz', $this->activity->cmid));
        $this->assertTrue(plagiarism_copyleaks_dbutils::is_user_eula_uptodate($this->user->id));

        // Act.
        $submissiondata = $this->submit_text_answer_to_quiz();
        plagiarism_copyleaks_test_lib::execute_send_submission_task();

        // Assert.
        $this->assertNotNull(plagiarism_copyleaks_test_lib::get_copyleaks_file($submissiondata['pathnamehash']));
        $this->assert_copyleaks_result($submissiondata['pathnamehash']);
    }
}
