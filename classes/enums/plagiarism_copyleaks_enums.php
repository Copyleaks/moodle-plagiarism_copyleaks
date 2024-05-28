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
 * This class is used as an enum in order to know the status of course module duplication 
 */
class plagiarism_copyleaks_cm_duplication_status {
    /** @var int course module queued and awaiting duplication. */
    const QUEUED = 1;
    /** @var int duplication process is currently in progress */
    const PENDING = 2;
    /** @var int duplication process has been successfully completed. */
    const COMPLETE = 3;
}
