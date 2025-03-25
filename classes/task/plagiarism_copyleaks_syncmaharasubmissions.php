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
 * Copyleaks Plagiarism Plugin - Sync Mahara Submissions
 *
 * @package   plagiarism_copyleaks
 * @copyright 2025 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_copyleaks\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_pluginconfig.class.php');
global $CFG;

/**
 * Copyleaks Plagiarism Plugin - Sync Mahara Submissions
 */
class plagiarism_copyleaks_syncmaharasubmissions extends \core\task\scheduled_task {
    /**
     * Get scheduler name, this will be shown to admins on schedulers dashboard.
     */
    public function get_name() {
        return get_string('clsyncmaharasubmissions', 'plagiarism_copyleaks');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        // Check if Mahara is configured and connection to Copyleaks is working.
        if (!\plagiarism_copyleaks_pluginconfig::is_mahara_configured() || 
        !\plagiarism_copyleaks_comms::test_copyleaks_connection('scheduler_task', true)
        ) {
            return;
        }

        global $DB;        
    }
}
