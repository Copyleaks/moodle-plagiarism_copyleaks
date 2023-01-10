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
 * Copyleaks assign module helper
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Copyleaks assign module helper */
class plagiarism_copyleaks_assignmodule {
    /**
     * check if current module user is instructor
     * @param mixed $context
     * @return boolean is instructor?
     */
    public static function is_instructor($context) {
        return has_capability(self::get_instructor_capability(), $context);
    }

    /**
     * get instructor capability
     */
    private static function get_instructor_capability() {
        return 'mod/assign:grade';
    }

    /**
     * get submittion author by item id
     * @param string $itemid
     * @return string user id
     */
    public static function get_author($itemid) {
        global $DB;

        if ($submission = $DB->get_record('assign_submission', array('id' => $itemid), 'userid')) {
            return $submission->userid;
        } else {
            return 0;
        }
    }
}
