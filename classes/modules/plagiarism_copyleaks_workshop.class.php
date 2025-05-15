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
 * Copyleaks workshop module helper
 * @package   plagiarism_copyleaks
 * @copyright 2025 Copyleaks
 * @author    Shade Amasha <shadea@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Copyleaks workshop module helper
 */
class plagiarism_copyleaks_workshop {
    /**
     * check if current module user is instructor
     * @param mixed $context
     * @return boolean is instructor?
     */
    public static function is_instructor($context) {
    return has_capability('mod/workshop:viewallsubmissions', $context) &&
      has_capability('mod/workshop:viewallassessments', $context);
    }
}