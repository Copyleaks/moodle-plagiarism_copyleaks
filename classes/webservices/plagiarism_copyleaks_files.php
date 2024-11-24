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
 * Copyleaks files
 * @package   plagiarism_copyleaks
 * @author    Shade Amasha <shadea@copyleaks.com>
 * @copyright 2021 Copyleaks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_webserviceexception.class.php');

  /**
   * plagiarism_copyleaks_files external API class
   */
class plagiarism_copyleaks_files extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_file_info_parameters() {
        return new external_function_parameters(
          [
            'identifier' => new external_value(PARAM_TEXT, 'Identifier'),
          ]
        );
    }

    /**
     * Gets the file information.
     * @param string $identifier Identifier
     * @return array
     */
    public static function get_file_info($identifier) {
        // Validate parameters.
        $params = self::validate_parameters(self::get_file_info_parameters(), [
          'identifier' => $identifier,
        ]);

        $filestorage = get_file_storage();
        $file = $filestorage->get_file_by_hash($params['identifier']);

        if (!$file) {
            throw new plagiarism_copyleaks_webservice_exception('filenotfound');
        }

        return [
          'contextid'  => $file->get_contextid(),
          'component'  => $file->get_component(),
          'filearea'   => $file->get_filearea(),
          'itemid'     => $file->get_itemid(),
          'filepath'   => $file->get_filepath(),
          'filename'   => $file->get_filename(),
        ];
    }

    /**
     * Describes the return value for get_file_info
     * @return external_single_structure
     */
    public static function get_file_info_returns() {
        return new external_single_structure(
          [
            'contextid' => new external_value(PARAM_TEXT, 'The context ID of the file'),
            'component' => new external_value(PARAM_TEXT, 'The component of the file'),
            'filearea'  => new external_value(PARAM_TEXT, 'The file area'),
            'itemid'    => new external_value(PARAM_TEXT, 'The item ID'),
            'filepath'  => new external_value(PARAM_TEXT, 'The file path'),
            'filename'  => new external_value(PARAM_TEXT, 'The file name'),
          ]
        );
    }
}
