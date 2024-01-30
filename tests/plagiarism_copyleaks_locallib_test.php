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

use bovigo\vfs\vfsStream;
use bovigo\vfs\vfsStreamWrapper;
use bovigo\vfs\vfsStreamDirectory;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/copyleaks/lib.php');


class plagiarism_copyleaks_locallib_test extends advanced_testcase {

    public function test_plagiarism_copyleaks() {
        $this->resetAfterTest();
        // vfsStreamWrapper::register();
        // vfsStreamWrapper::setRoot(new vfsStreamDirectory('submission'));

        // Create a user and assign the student role.
        $user =  $this->create_user();

        $course = $this->getDataGenerator()->create_course();

        // Create an assignment.
        $assignment = $this->create_assignment_and_enroll_user($user->id, $course->id);

        // Simulate a file submission.
        $file_contents = 'This is the content of the file.';
        // $file_path = vfsStream::url('submission/file.txt');
        // file_put_contents($file_path, $file_contents);
        // $submission = new assign_submission_file($user->id, $assignment->id);

        // $c1 = $this->getDataGenerator()->create_course();
        // $submission->save($submission, null);
        $this->assertTrue(true);
    }

    private function create_user() {
        $user = $this->getDataGenerator()->create_user();
        return $user;
    }

    private function create_assignment_and_enroll_user($userid, $courseid) {

        $assignment = $this->getDataGenerator()->create_module('assign', ['course' => $courseid]);
        $this->getDataGenerator()->enrol_user($userid, $assignment->id);
        return $assignment;
    }

    private function create_submission_data($submission) {
    }
}


// class SampleTest extends PHPUnit\Framework\TestCase {

//     public function testSample() {
//         $this->assertTrue(true);
//     }
// }
/*
{
    
    id:"393"
    assignment:"231"
    userid:"14"
    timecreated:"1703072361"
    timemodified:"1703072625"
    status:"submitted"
    groupid:"0"
    attemptnumber:"0"
    latest:"1"
}

{
    
    lastmodified:1703072625
    onlinetext_editor:array(3)
    files_filemanager:381091525
    id:319
    userid:14
    action:"savesubmission"
    submitbutton:"Save changes"
    onlinetexttrust:0
    onlinetext:"<p dir="ltr" style="text-align:left;"></p><h2>Trump engaged in insurrection, court says</h2><p>The top Colorado court upheld the trial judge’s conclusions that the January 6 assault on the US Capitol was an insurrection and that Trump “engaged in” that insurrection.</p><div><div></div><div><div><div></div><div><div></div></div></div></div></div><p>These are key legal hurdles that the challengers needed to clear before Trump could be removed from any ballot, largely because the text of the 14th Amendment doesn’t actually define an “insurrection” or spell out what it means to “engage in” insurrection.</p><p>The justices also affirmed the decision that Trump’s January 6 speech at the Ellipse was not protected by the First Amendment. Trump has unsuccessfully pushed this argument in state and federal courts, which found that he incited violence when he told supporters to “walk down to the Capitol” and “fight like hell” to “take back our country.”</p><p>“President Trump incited and"
    onlinetextformat:"1"
}
 */
