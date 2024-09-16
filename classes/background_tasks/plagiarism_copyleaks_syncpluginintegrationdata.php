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
 * Copyleaks Plagiarism Plugin sync plugin integration data
 * @package   plagiarism_copyleaks
 * @copyright 2024 Copyleaks
 * @author     Shade Amasha <shadea@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();



class plagiarism_copyleaks_syncpluginintegrationdata {

  public static function sync_data() {
    $cl = new \plagiarism_copyleaks_comms();

    $domain = (new moodle_url('/'))->out(false);
    $domain = rtrim($domain, '/');
    $pluginversion = get_config('plagiarism_copyleaks', 'version');

    $data = (array)[
      'domain' => $domain,
      'pluginVersion' => $pluginversion
    ];

    $cl->save_plugin_integration_data($data);
  }
}
