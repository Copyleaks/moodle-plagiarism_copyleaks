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
function xmldb_plagiarism_copyleaks_upgrade($oldversion)
{
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

    if ($oldversion < 2022072100) {
        // Get saved db settings.
        $saveddefaultvalue = $DB->get_records_menu('plagiarism_copyleaks_config', array('cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID), '', 'name,value');

        // Update saved default copyleaks settings.
        $fieldname = 'plagiarism_copyleaks_enable';
        $savedfield = new stdClass();
        $savedfield->cm = PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID;
        $savedfield->name = $fieldname;
        $savedfield->value = 0;
        if (!isset($saveddefaultvalue[$fieldname])) {
            $savedfield->config_hash = $savedfield->cm . "_" . $savedfield->name;
            if (!$DB->insert_record('plagiarism_copyleaks_config', $savedfield)) {
                throw new moodle_exception(get_string('clinserterror', 'plagiarism_copyleaks'));
            }
        } else {
            $savedfield->id = $DB->get_field(
                'plagiarism_copyleaks_config',
                'id',
                (array(
                    'cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID,
                    'name' => $fieldname
                ))
            );
            if (!$DB->update_record('plagiarism_copyleaks_config', $savedfield)) {
                throw new moodle_exception(get_string('clupdateerror', 'plagiarism_copyleaks'));
            }
        }

        upgrade_plugin_savepoint(true, 2022072100, 'plagiarism', 'copyleaks');
    }

    if ($oldversion < 2022103100) {
        // Get saved db settings.
        $saveddefaultvalue = $DB->get_records_menu('plagiarism_copyleaks_config', array('cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID), '', 'name,value');

        // Update saved default copyleaks settings.
        $fieldname = 'plagiarism_copyleaks_checkforparaphrase';
        $savedfield = new stdClass();
        $savedfield->cm = PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID;
        $savedfield->name = $fieldname;
        $savedfield->value = 1;
        if (!isset($saveddefaultvalue[$fieldname])) {
            $savedfield->config_hash = $savedfield->cm . "_" . $savedfield->name;
            if (!$DB->insert_record('plagiarism_copyleaks_config', $savedfield)) {
                throw new moodle_exception(get_string('clinserterror', 'plagiarism_copyleaks'));
            }
        } else {
            $savedfield->id = $DB->get_field(
                'plagiarism_copyleaks_config',
                'id',
                (array(
                    'cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID,
                    'name' => $fieldname
                ))
            );
            if (!$DB->update_record('plagiarism_copyleaks_config', $savedfield)) {
                throw new moodle_exception(get_string('clupdateerror', 'plagiarism_copyleaks'));
            }
        }

        upgrade_plugin_savepoint(true, 2022103100, 'plagiarism', 'copyleaks');
    }

    if ($oldversion < 2022110900) {
        // Get saved db settings.
        $saveddefaultvalue = $DB->get_records_menu('plagiarism_copyleaks_config', array('cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID), '', 'name,value');

        // Update saved default copyleaks settings.
        $fieldname = 'plagiarism_copyleaks_disablestudentinternalaccess';
        $savedfield = new stdClass();
        $savedfield->cm = PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID;
        $savedfield->name = $fieldname;
        $savedfield->value = 1;
        if (!isset($saveddefaultvalue[$fieldname])) {
            $savedfield->config_hash = $savedfield->cm . "_" . $savedfield->name;
            if (!$DB->insert_record('plagiarism_copyleaks_config', $savedfield)) {
                throw new moodle_exception(get_string('clinserterror', 'plagiarism_copyleaks'));
            }
        } else {
            $savedfield->id = $DB->get_field(
                'plagiarism_copyleaks_config',
                'id',
                (array(
                    'cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID,
                    'name' => $fieldname
                ))
            );
            if (!$DB->update_record('plagiarism_copyleaks_config', $savedfield)) {
                throw new moodle_exception(get_string('clupdateerror', 'plagiarism_copyleaks'));
            }
        }

        upgrade_plugin_savepoint(true, 2022110900, 'plagiarism', 'copyleaks');
    }

    return true;
}
