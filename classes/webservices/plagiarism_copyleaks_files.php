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
        global $DB;
        // Validate parameters.
        $params = self::validate_parameters(self::get_file_info_parameters(), [
            'identifier' => $identifier,
        ]);

        $identifier = $params['identifier'];

        $filestorage = get_file_storage();
        $file = $filestorage->get_file_by_hash($identifier);

        if (!$file) {
            $submission = $DB->get_record(
                'plagiarism_copyleaks_files',
                [
                    'identifier' => $identifier,
                ]
            );

            if (!$submission) {
                throw new plagiarism_copyleaks_webservice_exception('filenotfound');
            }

            $coursemodule = get_coursemodule_from_id('', $submission->cm);
            if (empty($coursemodule)) {
                throw new plagiarism_copyleaks_webservice_exception('Course Module wasnt found for this record.');
            }

            $userid = $submission->userid;

            if ($submission->submissiontype == 'text_content') {
                $moduledata = $DB->get_record($coursemodule->modname, ['id' => $coursemodule->instance]);

                if ($coursemodule->modname == 'workshop') {
                    $workshopsubmission = $DB->get_record(
                        'workshop_submissions',
                        ['id' => $submission->itemid],
                        'content'
                    );
                    $submittedtextcontent = $workshopsubmission->content;
                } else if ($coursemodule->modname == 'assign') {
                    $submissionref = $DB->get_record(
                        'assign_submission',
                        [
                            'id' => $submission->itemid,
                            'userid' => ($moduledata->teamsubmission) ? 0 : $submission->userid,
                            'assignment' => $coursemodule->instance,
                        ],
                        'id'
                    );

                    $txtsubmissionref = $DB->get_record(
                        'assignsubmission_onlinetext',
                        ['submission' => $submissionref->id],
                        'onlinetext'
                    );
                    $submittedtextcontent = $txtsubmissionref->onlinetext;
                } else {
                    throw new plagiarism_copyleaks_webservice_exception('Content not found for the submission.');
                }

                $filename = 'online_text_'
                    . $userid . "_"
                    . $coursemodule->id . "_"
                    . $coursemodule->instance . '.txt';

                $submittedtextcontent = html_to_text($submittedtextcontent);
            } else if ($submission->submissiontype == 'forum_post') {
                $forumpost = $DB->get_record_select(
                    'forum_posts',
                    " userid = ? AND id = ? ",
                    [$userid, $submission->itemid]
                );
                if ($forumpost) {
                    $filename = 'forumpost_'
                        . $userid . "_"
                        . $coursemodule->id . "_"
                        . $coursemodule->instance . "_"
                        . $submission->itemid . '.txt';

                    $submittedtextcontent = html_to_text(strip_tags($forumpost->message));
                } else {
                    throw new plagiarism_copyleaks_webservice_exception('Content not found for the submission.');
                }
            } else if ($submission->submissiontype == 'quiz_answer') {

                require_once($CFG->dirroot . '/mod/quiz/locallib.php');
                $quizattempt = \quiz_attempt::create($submission->itemid);
                foreach ($quizattempt->get_slots() as $slot) {
                    $questionattempt = $quizattempt->get_question_attempt($slot);
                    if ($submission->identifier == sha1($questionattempt->get_response_summary())) {
                        $submittedtextcontent = $questionattempt->get_response_summary();
                        break;
                    }
                }

                if (!empty($submittedtextcontent)) {
                    $submittedtextcontent = strip_tags($submittedtextcontent);
                    $filename = 'quizanswer_'
                        . $userid . "_"
                        . $coursemodule->id . "_"
                        . $coursemodule->instance . "_"
                        . $submission->itemid . '.txt';
                } else {
                    throw new plagiarism_copyleaks_webservice_exception('Content not found for the submission.');
                }
            }

            return [
                'textcontent' => $submittedtextcontent,
                'contextid'  => null,
                'component'  => null,
                'filearea'   => null,
                'itemid'     => null,
                'filepath'   => null,
                'filename'   => $filename,
            ];
        }

        return [
            'textcontent' => null,
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
                'textcontent' => new external_value(PARAM_TEXT, 'The Text content of the submission in case of text submission'),
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
