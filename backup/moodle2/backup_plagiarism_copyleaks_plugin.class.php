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
 *
 * Copyleaks Plagiarism Plugin - Handle backup operations
 * @package   plagiarism_copyleaks
 * @copyright 2024 Copyleaks
 * @author    Shade Amasha <shadea@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class backup_plagiarism_copyleaks_plugin
 *
 * Extends the backup_plagiarism_plugin class to define the structure for backing up
 * and restoring the Copyleaks plagiarism plugin configuration for modules in Moodle.
 *
 * This class includes the module-specific configuration elements and integrates them
 * into the backup and restore process via nested elements.
 */
class backup_plagiarism_copyleaks_plugin extends backup_plagiarism_plugin {
    /**
     * Defines the plugin structure for backup and restore.
     *
     * This method creates a nested structure for the Copyleaks plugin configuration,
     * including all associated module configuration settings. It uses the backup_nested_element
     * class to represent the hierarchy and sets the source table for the configuration data.
     *
     * @return backup_plugin_element The plugin element with its nested structure.
     */
    protected function define_module_plugin_structure() {
        $plugin = $this->get_plugin_element();

        $pluginelement = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginelement);

        // Add module config elements.
        $copyleaksconfigs = new backup_nested_element('copyleaks_configs');
        $copyleaksconfig = new backup_nested_element('copyleaks_config', ['id'], ['name', 'value']);
        $pluginelement->add_child($copyleaksconfigs);
        $copyleaksconfigs->add_child($copyleaksconfig);
        $copyleaksconfig->set_source_table('plagiarism_copyleaks_config', ['cm' => backup::VAR_PARENTID]);

        return $plugin;
    }
}
