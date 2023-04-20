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

class plagiarism_copyleaks_reportstatus {
    const SCORED = 1;
    const ERROR = 2;
    const PENDING = 3;
}

class plagiarism_copyleaks_priority {
    const LOW = 0;
    const MIDIUM = 1;
    const HIGH = 2;
}

class plagiarism_copyleaks_request_status {
    const FAILED = 0;
    const SUCCEEDED = 2;
}
