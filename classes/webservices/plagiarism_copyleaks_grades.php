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
 * Copyleaks grades
 * @package   plagiarism_copyleaks
 * @author    Shade Amasha <shadea@copyleaks.com>
 * @copyright 2021 Copyleaks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_webserviceexception.class.php');
require_once("$CFG->libdir/grade/grade_scale.php");


/**
 * plagiarism_copyleaks_grades external API class
 */
class plagiarism_copyleaks_grades extends external_api {


    /**
     * Returns the description of the get_multiple_scales_values() parameters.
     *
     * @return external_function_parameters
     */
    public static function get_multiple_scales_values_parameters() {
        return new external_function_parameters(
        [
        'scales' => new external_multiple_structure(
          new external_single_structure(
            [
              'id' => new external_value(PARAM_INT, 'the id of the scale'),
            ]
          )),
        ]
        );
    }

    /**
     * Get the values associated with multiple scales.
     *
     * @param array $scales Array of Scales with IDs
     * @return array Values for each scale.
     */
    public static function get_multiple_scales_values($scales) {
        $params = self::validate_parameters(
        self::get_multiple_scales_values_parameters(),
            ['scales' => $scales]
        );
        $context = context_system::instance();
        self::validate_context($context);

        $scalesvalues = [];
        foreach ($params['scales'] as $scale) {
            $scaleid = $scale['id'];
            // Retrieve the scale value from the database.
            $scale = grade_scale::fetch(['id' => $scaleid]);
            if ($scale) {
                $scalevalues = $scale->load_items();
                $formattedvalues = [];
                foreach ($scalevalues as $key => $value) {
                    // Add a key (make the first value 1).
                    $formattedvalues[] = [
                        'id' => $key + 1,
                        'name' => external_format_string($value, $context->id),
                    ];
                }
                $scalesvalues[] = [
                    'scaleid' => $scaleid,
                    'values' => $formattedvalues,
                ];
            }
        }
        return ['scales' => $scalesvalues];
    }

    /**
     * Returns description of get_multiple_scales_values() result value.
     *
     * @return external_multiple_structure
     */
    public static function get_multiple_scales_values_returns() {
        return new external_single_structure(
        [
        'scales' => new external_multiple_structure(
          new external_single_structure([
            'scaleid' => new external_value(PARAM_INT, 'Scale ID'),
            'values' => new external_multiple_structure(
              new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Scale value ID'),
                'name' => new external_value(PARAM_RAW, 'Scale value name'),
              ])),
          ])
        )]);
    }
}
