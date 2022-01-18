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
 * Upgrade such as database scheme changes and other things that must happen when the plugin is being upgraded are defined here
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/lib.php');

/**
 * called by moodle when plugin version is updated
 * @param int $oldversion
 * @return bool
 */
function xmldb_plagiarism_copyleaks_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    if ($oldversion < 2021090901) {
        // Changing type of field similarityscore on table plagiarism_copyleaks_files to number.
        $table = new xmldb_table('plagiarism_copyleaks_files');
        $field = new xmldb_field('similarityscore', XMLDB_TYPE_NUMBER, '10', null, null, null, null, 'statuscode');

        // Launch change of type for field similarityscore.
        $dbman->change_field_type($table, $field);

        // Copyleaks savepoint reached.
        upgrade_plugin_savepoint(true, 2021090901, 'plagiarism', 'copyleaks');
    }

    return true;
}
