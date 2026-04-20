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
 * Copyleaks rescan webservice
 * @package   plagiarism_copyleaks
 * @author    Shade Amasha <shadea@copyleaks.com>
 * @copyright 2021 Copyleaks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/enums/plagiarism_copyleaks_enums.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_utils.class.php');

/**
 * plagiarism_copyleaks_rescan external API class
 */
class plagiarism_copyleaks_rescan extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function rescan_module_reports_on_settings_change_parameters() {
        return new external_function_parameters(
            [
                'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            ]
        );
    }

    /**
     * Rescan all eligible reports for a course module after scan settings change.
     * Sets success and rescannable error records to queued so the cron job resubmits them with the new settings.
     *
     * @param int $coursemoduleid Course module ID
     * @return array Warnings
     */
    public static function rescan_module_reports_on_settings_change($coursemoduleid) {
        global $DB;

        $params = self::validate_parameters(
            self::rescan_module_reports_on_settings_change_parameters(),
            ['coursemoduleid' => $coursemoduleid]
        );
        $coursemoduleid = $params['coursemoduleid'];

        $transaction = $DB->start_delegated_transaction();
        try {
            // Bulk update: set all success records to queued and clear their scores.
            $DB->execute(
                "UPDATE {plagiarism_copyleaks_files}
                 SET statuscode = 'queued',
                     similarityscore = NULL,
                     aiscore = NULL,
                     writingfeedbackissues = NULL,
                     errormsg = NULL,
                     errorcode = NULL
                 WHERE cm = ? AND statuscode = 'success'",
                [$coursemoduleid]
            );

            // Loop through error records and set rescannable ones to queued.
            $errorrecords = $DB->get_records_select(
                'plagiarism_copyleaks_files',
                "cm = ? AND statuscode = 'error'",
                [$coursemoduleid]
            );

            foreach ($errorrecords as $record) {
                if (plagiarism_copyleaks_utils::is_resubmittable_error($record->errorcode)) {
                    $record->statuscode = 'queued';
                    $record->errormsg = null;
                    $record->errorcode = null;
                    $DB->update_record('plagiarism_copyleaks_files', $record);
                }
            }

            $transaction->allow_commit();
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }

        return [
            'warnings' => [],
        ];
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function rescan_module_reports_on_settings_change_returns() {
        return new external_single_structure(
            [
                'warnings' => new external_warnings(),
            ]
        );
    }
}
