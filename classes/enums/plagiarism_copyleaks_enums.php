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
 * @author    Gil Cohen <gilc@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * This class is used as an enum in order to know what status the submissions are.
 */
class plagiarism_copyleaks_reportstatus {
    /** @var int scored report */
    const SCORED = 1;
    /** @var int report got error */
    const ERROR = 2;
    /** @var int report is still pending */
    const PENDING = 3;
    /** @var int report is scheduled */
    const SCHEDULED = 4;
}

/**
 * This class is used as an enum in order to know the queued requests's priority.
 */
class plagiarism_copyleaks_priority {
    /** @var int low proirity */
    const LOW = 0;
    /** @var int medium proirity */
    const MIDIUM = 1;
    /** @var int high proirity */
    const HIGH = 2;
}

/**
 * This class is used as an enum in order to know the queued requests's reuslt.
 */
class plagiarism_copyleaks_request_status {
    /** @var int request was failed to complete */
    const FAILED = 0;
    /** @var int request successfuly completed */
    const SUCCEEDED = 2;
}

/**
 * This class is used as an enum in order to know what background should run only once.
 */
class plagiarism_copyleaks_background_tasks {
    /** @var int an indicator to run the sync_users_data background task */
    const SYNC_USERS_DATA = 1;
    /** @var int an indicator to run the sync_courses_data background task */
    const SYNC_COURSES_DATA = 2;
}

/**
 * This class is used as an enum in order to know time left from a date.
 */
class plagiarism_copyleaks_times {
    /** @var int respresents a minute */
    const SOON = 0;
    /** @var int respresents a minute */
    const MINUTES = 1;
    /** @var int respresents am hour */
    const HOURES = 2;
    /** @var int respresents a day */
    const DAYS = 3;
    /** @var int respresents a month */
    const MONTHS = 4;
}

/**
 * This class is used as an enum in order to know the grade types..
 */
class plagiarism_copyleaks_grade_types {
    /** @var int Represents a grade type of 'None' (no grading). */
    const NONE = 0;
    /** @var int Represents a grade type of 'Scale' (uses a grading scale). */
    const SCALE = 1;
    /** @var int Represents a grade type of 'Point' (uses points for grading). */
    const POINT = 2;
}

/**
 * This class is used as an enum in order to know the grade methods.
 */
class plagiarism_copyleaks_grade_methods {
    /** @var int Represents a grading method of 'None' (no grading method). */
    const NONE = 0;
    /** @var int Represents a grading method of 'Simple Direct Grading'. */
    const SIMPLE_DIRECT_GRADING = 1;
    /** @var int Represents a grading method of 'Marking Guide'. */
    const MARKING_GUIDE = 2;
    /** @var int Represents a grading method of 'Rubric'. */
    const RUBRIC = 3;
}

/**
 * This class is used as an enum in order to know the additional attempts methods.
 */
class plagiarism_copyleaks_additional_attempts_method {
    /** @var int Represents a method of 'Never' (no additional attempts allowed). */
    const NEVER = 0;
    /** @var int Represents a method of 'Manually' (additional attempts allowed manually). */
    const MANUAL = 1;
    /** @var int Represents a method of 'automatically Until Pass' (additional attempts allowed automatically until pass). */
    const UNTIL_PASS = 3;
}
/**
 * This class is used as an enum in order to know the status of course module duplication.
 */
class plagiarism_copyleaks_cm_duplication_status {
    /** @var int course module queued and awaiting duplication. */
    const QUEUED = 1;
    /** @var int duplication process failed */
    const ERROR = 2;
}

/**
 * This class is used as an enum in order to define error codes and their meanings.
 */
class plagiarism_copyleaks_errorcode {
    /** @var int Exceeded credits limit . occurs when a scan requires more than 200 credits.*/
    const EXCEEDED_CREDITS_LIMIT = -2;
    /** @var int Bad request. One or several required parameters are missing or incorrect. */
    const BAD_REQUEST = 1;
    /** @var int Invalid login credentials. */
    const INVALID_CREDENTIALS = 2;
    /** @var int To use your account, you need to confirm the email address. */
    const EMAIL_CONFIRMATION_REQUIRED = 3;
    /** @var int This user is disabled. Contact support for help. */
    const USER_DISABLED = 4;
    /** @var int Failed to download the requested URL. */
    const URL_DOWNLOAD_FAILED = 5;
    /** @var int Cannot complete the scan request because the file is too large. */
    const FILE_TOO_LARGE = 6;
    /** @var int Failed reading the submitted text. */
    const TEXT_READING_FAILED = 7;
    /** @var int The image quality is too low to scan. */
    const IMAGE_QUALITY_TOO_LOW = 8;
    /** @var int Temporarily unavailable. Please try again later. */
    const TEMPORARILY_UNAVAILABLE = 9;
    /** @var int This file type is not supported. */
    const UNSUPPORTED_FILE_TYPE = 10;
    /** @var int Not enough text to scan. The minimum text length is 30 characters and at least 6 words. */
    const INSUFFICIENT_TEXT = 11;
    /** @var int This document is too long. */
    const DOCUMENT_TOO_LONG = 12;
    /** @var int You don't have enough credits to complete the request. */
    const INSUFFICIENT_CREDITS = 13;
    /** @var int The submitted file is invalid. */
    const INVALID_FILE = 14;
    /** @var int The submitted URL is invalid. */
    const INVALID_URL = 15;
    /** @var int Internal server error. */
    const INTERNAL_SERVER_ERROR = 16;
    /** @var int You have no credits. You need to purchase credits in order to complete the request. */
    const NO_CREDITS_AVAILABLE = 17;
    /** @var int Copyshield widget is not showing on your webpage. */
    const COPYSHIELD_WIDGET_NOT_VISIBLE = 18;
    /** @var int Headers are too long. */
    const HEADERS_TOO_LONG = 19;
    /** @var int Only MIME multipart content type is allowed. */
    const INVALID_CONTENT_TYPE = 20;
    /** @var int You can upload one file at a time. */
    const SINGLE_FILE_UPLOAD_ONLY = 21;
    /** @var int Unable to determine file size. */
    const UNABLE_TO_DETERMINE_FILE_SIZE = 22;
    /** @var int Bad filename. */
    const BAD_FILENAME = 24;
    /** @var int Undefined language. */
    const UNDEFINED_LANGUAGE = 25;
    /** @var int The request cannot be completed because the process is still running. */
    const PROCESS_RUNNING = 26;
    /** @var int Unknown process id. */
    const UNKNOWN_PROCESS_ID = 27;
    /** @var int Missing header value. */
    const MISSING_HEADER_VALUE = 30;
    /** @var int Bad parameter. */
    const BAD_PARAMETER = 31;
    /** @var int Too many failed login attempts. Please try again later. */
    const TOO_MANY_FAILED_LOGINS = 32;
    /** @var int HTTP header key is too long. */
    const HEADER_KEY_TOO_LONG = 33;
    /** @var int Authorization has been denied for this request. */
    const AUTHORIZATION_DENIED = 37;
    /** @var int Internal plugin error. can be rescanned.*/
    const INTERNAL_PLUGIN_ERROR_RESCANNABLE = 38;
    /** @var int Internal plugin error. can't be rescanned.*/
    const INTERNAL_PLUGIN_ERROR_NOT_RESCANNABLE = 39;
}

/**
 * This class is used as an enum to define different rescan modes for plagiarism checks.
 */
class plagiarism_copyleaks_rescan_mode {
    /** @var int Rescan all failed scans (Admin only). */
    const RESCAN_ALL = 0;

    /** @var int Rescan a specific failed scan (Teacher only). */
    const RESCAN_SINGLE = 1;

    /** @var int Rescan all failed scans in a specific module/activity (Teacher only). */
    const RESCAN_MODULE = 2;
}
