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

    if ($oldversion < 2022072100) {
        // Get saved db settings.
        $saveddefaultvalue = $DB->get_records_menu(
            'plagiarism_copyleaks_config',
            array('cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID),
            '',
            'name,value'
        );

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
        $saveddefaultvalue = $DB->get_records_menu(
            'plagiarism_copyleaks_config',
            array('cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID),
            '',
            'name,value'
        );

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
        $saveddefaultvalue = $DB->get_records_menu(
            'plagiarism_copyleaks_config',
            array('cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID),
            '',
            'name,value'
        );

        // Update saved default copyleaks settings.
        $fieldname = 'plagiarism_copyleaks_disablestudentinternalaccess';
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

        upgrade_plugin_savepoint(true, 2022110900, 'plagiarism', 'copyleaks');
    }

    if ($oldversion < 2022122400) {
        $table = new xmldb_table('plagiarism_copyleaks_users');

        // Adding fields to table plagiarism_copyleaks_users.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', !XMLDB_UNSIGNED, XMLDB_NOTNULL, !XMLDB_SEQUENCE, null);
        $table->add_field('user_eula_accepted', XMLDB_TYPE_INTEGER, '1', !XMLDB_UNSIGNED, !XMLDB_NOTNULL, !XMLDB_SEQUENCE, 0);

        // Adding keys and indexes to table plagiarism_copyleaks_users.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('userid', XMLDB_INDEX_UNIQUE, array('userid'));

        // Conditionally launch create table for plagiarism_copyleaks_users.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2022122400, 'plagiarism', 'copyleaks');
    }

    if ($oldversion < 2022122802) {
        // Get saved db settings.
        $saveddefaultvalue = $DB->get_records_menu(
            'plagiarism_copyleaks_config',
            array('cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID),
            '',
            'name,value'
        );

        // Update saved default copyleaks settings.
        $fieldname = 'plagiarism_copyleaks_showstudentresultsinfo';
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

        upgrade_plugin_savepoint(true, 2022122802, 'plagiarism', 'copyleaks');
    }

    if ($oldversion < 2023041917) {
        $table = new xmldb_table('plagiarism_copyleaks_request');

        // Adding fields to table plagiarism_copyleaks_users.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('verb', XMLDB_TYPE_TEXT, '255', null, XMLDB_NOTNULL, null, 'POST');
        $table->add_field('created_date', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, '0');
        $table->add_field('endpoint', XMLDB_TYPE_TEXT, '255', null, XMLDB_NOTNULL);
        $table->add_field('total_retry_attempts', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->add_field('data', XMLDB_TYPE_TEXT, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('priority', XMLDB_TYPE_INTEGER, '1');
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL);
        $table->add_field('fail_message', XMLDB_TYPE_TEXT, '255');
        $table->add_field('require_auth', XMLDB_TYPE_NUMBER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL);

        // Adding keys and indexes to table plagiarism_copyleaks_users.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('created_date', XMLDB_INDEX_NOTUNIQUE, array('created_date'));
        $table->add_index('cmid', XMLDB_INDEX_NOTUNIQUE, array('cmid'));

        // Conditionally launch create table for plagiarism_copyleaks_users.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2023041901, 'plagiarism', 'copyleaks');
    }

    if ($oldversion < 2018051700) {
        // Delete a column from the table.
        $table = new xmldb_table('plagiarism_copyleaks_users');
        $field = new xmldb_field('user_eula_accepted');
        $dbman->drop_field($table,  $field);

        // Add new eula table
        $table = new xmldb_table('plagiarism_copyleaks_eula');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ci_user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('version', XMLDB_TYPE_TEXT, '10', null, XMLDB_NOTNULL, null, '');
        $table->add_field('is_synced', XMLDB_TYPE_NUMBER, '1', XMLDB_UNSIGNED);
        $table->add_field('date', XMLDB_TYPE_DATETIME, '30', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');

        $table->add_key('id', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('ci_user_id', XMLDB_INDEX_NOTUNIQUE, array('ci_user_id'));
        $table->add_index('is_synced', XMLDB_INDEX_NOTUNIQUE, array('is_synced'));
        $table->add_index('date', XMLDB_INDEX_NOTUNIQUE, array('date'));



        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }


        // Insert to Config table a value of eula data.
        $saveddefaultvalue = $DB->get_records_menu(
            'plagiarism_copyleaks_config',
            array('cm' => PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID),
        );
        $savedfield = new stdClass();
        $savedfield->cm = PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID;
        $savedfield->name = 'latest_eula_version';
        $savedfield->value = '0';

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


        upgrade_plugin_savepoint(true, 2018051700, 'plagiarism', 'copyleaks');
    }

    return true;
}
