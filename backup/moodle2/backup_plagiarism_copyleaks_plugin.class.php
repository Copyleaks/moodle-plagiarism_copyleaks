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

defined('MOODLE_INTERNAL') || die();

class backup_plagiarism_copyleaks_plugin extends backup_plagiarism_plugin {

  protected function define_module_plugin_structure() {
    $plugin = $this->get_plugin_element();

    $pluginelement = new backup_nested_element($this->get_recommended_name());
    $plugin->add_child($pluginelement);

    // Add module config elements.
    $copyleaksconfigs = new backup_nested_element('copyleaks_configs');
    $copyleaksconfig = new backup_nested_element('copyleaks_config', array('id'), array('name', 'value'));
    $pluginelement->add_child($copyleaksconfigs);
    $copyleaksconfigs->add_child($copyleaksconfig);
    $copyleaksconfig->set_source_table('plagiarism_copyleaks_config', array('cm' => backup::VAR_PARENTID));
    
    return $plugin;
}

}
