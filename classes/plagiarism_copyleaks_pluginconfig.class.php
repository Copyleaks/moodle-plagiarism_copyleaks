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
 * plugin configurations helpers methods
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * plugin configurations helpers methods
 */
class plagiarism_copyleaks_pluginconfig {
    /**
     * Check module configuration settings for the copyleaks plagiarism plugin
     * @param string $modulename
     * @return bool if plugin is configured and enabled return true, otherwise false.
     */
    public static function is_plugin_configured($modulename) {
        $config = self::admin_config();

        if (
            empty($config->plagiarism_copyleaks_key) ||
            empty($config->plagiarism_copyleaks_apiurl) ||
            empty($config->plagiarism_copyleaks_secret)
        ) {
            // Plugin not configured.
            return false;
        }

        $moduleconfigname = 'plagiarism_copyleaks_' . $modulename;
        if (!isset($config->$moduleconfigname) || $config->$moduleconfigname !== '1') {
            // Plugin not enabled for this module.
            return false;
        }

        return true;
    }

    /**
     * Get the admin config settings for the plugin
     * @return mixed Copyleaks plugin admin configurations
     */
    public static function admin_config() {
        return get_config('plagiarism_copyleaks');
    }

    /**
     * get admin config saved database properties
     * @return array admin config properties for Copyleaks plugin
     */
    public static function admin_config_properties() {
        return array(
            "version",
            "enabled",
            "copyleaks_use",
            "plagiarism_copyleaks_apiurl",
            "plagiarism_copyleaks_key",
            "plagiarism_copyleaks_secret",
            "plagiarism_copyleaks_jwttoken",
            "plagiarism_copyleaks_mod_assign",
            "plagiarism_copyleaks_mod_forum",
            "plagiarism_copyleaks_mod_workshop",
            "plagiarism_copyleaks_mod_quiz",
            'plagiarism_copyleaks_studentdisclosure'
        );
    }

    /**
     * Set a config property value for the plugin admin settings.
     * @param stdClass $data
     * @param string $prop property name
     */
    public static function set_admin_config($data, $prop) {
        if (strpos($prop, 'copyleaks')) {
            $dbfield = $prop;
        } else {
            $dbfield = "plagiarism_copyleaks_" . $prop;
        }

        if (isset($data->$prop)) {
            set_config($dbfield, $data->$prop, 'plagiarism_copyleaks');
        }
    }
}
