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
 * Copyleaks notifications
 * @package   plagiarism_copyleaks
 * @author    Shade Amasha <shadea@copyleaks.com>
 * @copyright 2021 Copyleaks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/lib/grouplib.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_webserviceexception.class.php');

class plagiarism_copyleaks_course_groups extends external_api {

  /**
   * Returns description of method parameters
   * @return external_function_parameters
   */
  public static function get_course_groups_info_parameters() {
    return new external_function_parameters(
      array(
        'courseid' => new external_value(PARAM_TEXT, 'Course ID'),
      )
    );
  }

  /**
   * Gets the groups/members of a course
   * @param string $courseid Course ID
   * @return array
   */
  public static function get_course_groups_info($courseid) {
    // Validate parameters
    $params = self::validate_parameters(self::get_course_groups_info_parameters(), array(
      'courseid' => $courseid
    ));

    // Fetch the groups of the course.
    $groups = groups_get_course_data($params['courseid'])->groups;

    $groupdata = [];
    foreach ($groups as $group) {
      $groupid = $group->id;
      $groupname = $group->name;

      // Fetch the members of the group.
      $members = groups_get_groups_members([$groupid]);

      // Collect the member ids in an array.
      $membersids = array_column($members, 'id');

      // Map the group and its members.
      $groupdata[] = [
        'groupid' => $groupid,
        'groupname' => $groupname,
        'members' => $membersids 
      ];
    }

    return array('groups' => $groupdata);
  }

  /**                                                                                                                                  
   * Describes the return value for get_course_groups_info
   * @return external_single_structure
   */
  public static function get_course_groups_info_returns() {
    return new external_single_structure(
      array(
        'groups' => new external_multiple_structure(
          new external_single_structure(
            array(
              'groupid' => new external_value(PARAM_TEXT, 'Group ID'),
              'groupname' => new external_value(PARAM_TEXT, 'Group Name'),
              'members' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Member ID'),
                'Members of the group'
              ),
            )
          ),
          'Groups of the course',
          VALUE_OPTIONAL
        ),
      )
    );
  }
}
