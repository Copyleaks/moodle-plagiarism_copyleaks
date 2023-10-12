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
 * Copyleaks Plagiarism Plugin - Handle Resubmit Files
 * @package   plagiarism_copyleaks
 * @copyright 2022 Copyleaks
 * @author    Gil Cohen <gilc@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');

class plagiarism_copyleaks_synusersdata {
    /**
     * Handle users upsert to Copyleaks
     */
    public static function sync_users_data() {
        global $DB;
        $startindex = 0;
        $canloadmore = true;
        $maxdataloadloops = PLAGIARISM_COPYLEAKS_CRON_MAX_DATA_LOOP;
        $maxitemsperloop = PLAGIARISM_COPYLEAKS_CRON_QUERY_LIMIT;
        $alreadyupdatedusers = array();
        $cl = new \plagiarism_copyleaks_comms();

        while ($canloadmore && (--$maxdataloadloops) > 0) {

            // Get users ids from copyleaks table.
            $files = $DB->get_records(
                'plagiarism_copyleaks_files',
                null,
                '',
                '*',
                $startindex,
                $maxitemsperloop
            );

            $userscount = count($files);

            // If there is no cm's break the loop.
            if ($userscount == 0) {
                break;
            }

            // If the amount of the users ids is lower then the max records amount - can't load more.
            if ($userscount < $maxitemsperloop) {
                $canloadmore = false;
            }

            $startindex += $userscount;

            $usersobjects = array();

            // For each userId we'll find its user data and add it to the request array.
            foreach ($files as $file) {
                $userdata = get_complete_user_data('id', $file->userid);
                if ($userdata) {
                    // Check if the course already upserted.
                    if ($alreadyupdatedusers[$file->userid]) {
                        continue;
                    } else {
                        $alreadyupdatedusers[$file->userid] = true;
                    }

                    if ($userdata) {
                        $usersobjects[] = array(
                            "MPPUserId" => $userdata->id,
                            "userName" => $userdata->firstname . " " . $userdata->lastname
                        );
                    }
                }
            }

            // Send the upsert request only if there is any courses.
            if (count($usersobjects) > 0) {
                $cl->save_users_data(array('users' => $usersobjects));
            }
        }
    }
}
