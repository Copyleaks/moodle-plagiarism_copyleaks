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
 * lib.php - Contains Plagiarism plugin specific functions called by Modules.
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Constants.
define('PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID', 0);
$clsupportedsubmissiontypes = array('file', 'text_content', 'forum_post', 'quiz_answer');
define('PLAGIARISM_COPYLEAKS_SUPPORTED_SUBMISSION_TYPES', $clsupportedsubmissiontypes);


// Max file size 25mb.
define('PLAGIARISM_COPYLEAKS_MAX_FILE_UPLOAD_SIZE', 52428800);
define('PLAGIARISM_COPYLEAKS_CRON_QUERY_LIMIT', 100);
define('PLAGIARISM_COPYLEAKS_CRON_MAX_DATA_LOOP', 256);
define('PLAGIARISM_COPYLEAKS_MAX_FILENAME_LENGTH', 180);
define('PLAGIARISM_COPYLEAKS_LOGS_PREFIX', 'log_');



// Copyleaks support file types.
$copyleaksacceptedfiles = array(
    // Textual.
    '.html', '.txt', '.csv', '.rtf', '.xml', '.htm',
    // Non-Textual.
    '.pdf', '.docx', '.doc', '.pptx', '.ppt', '.odt',
    '.chm', '.epub', '.odp', '.ppsx', '.pages', '.xlsx',
    '.xls', '.LaTeX',
    // Source code.
    '.py', '.go', '.cs', '.c', '.h', '.idc', '.cpp', '.hpp',
    '.cpp', '.hpp', '.cc', '.hh', '.java', '.js', '.swift',
    '.rb', '.pl', '.php', '.sh', '.m', '.scala',
    // OCR.
    '.gif', '.png', '.bmp', '.jpg', '.jpeg'
);
define('PLAGIARISM_COPYLEAKS_ACCEPTED_FILES', $copyleaksacceptedfiles);
define('DEFAULT_DATABASE_COPYLEAKSDB_ID', 'DEFAULT_DATABASE_COPYLEAKSDB_ID');

// Copyleaks retry array in seconds.
define('PLAGIARISM_COPYLEAKS_RETRY', array(0, 2.5, 3, 5, 10));
define('PLAGIARISM_COPYLEAKS_MAX_RETRY', 5);
define('PLAGIARISM_COPYLEAKS_MAX_AUTO_RETRY', 10);
define('PLAGIARISM_COPYLEAKS_DEFUALT_EULA_VERSION', '2023032700');
define('PLAGIARISM_COPYLEAKS_EULA_FIELD_NAME', 'plagiarism_copyleaks_latesteulaversion ');
define('PLAGIARISM_COPYLEAKS_SCAN_AI_FIELD_NAME', 'plagiarism_copyleaks_enable_ai_detection');
define('PLAGIARISM_COPYLEAKS_SCAN_PLAGIARISM_FIELD_NAME', 'plagiarism_copyleaks_enable_plagiarism_detection');
define('PLAGIARISM_COPYLEAKS_DETECT_GRAMMAR_FIELD_NAME', 'plagiarism_copyleaks_enable_grammar_detection');
define('PLAGIARISM_COPYLEAKS_DETECT_WF_ISSUES_FIELD_NAME', 'plagiarism_copyleaks_enable_wf_issues_detection');

define('PLAGIARISM_COPYLEAKS_ENABLE_AI_VIEW_FOR_STUDENT', 'enable_ai_result_view_for_student');
define('PLAGIARISM_COPYLEAKS_ENABLE_PLAGIARISM_VIEW_FOR_STUDENT', 'enable_plagiairsm_result_view_for_student');
define('PLAGIARISM_COPYLEAKS_ENABLE_WF_VIEW_FOR_STUDENT', 'enable_writing_assisstant_result_view_for_student');
