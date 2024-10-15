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
 * Copyleaks Plagiarism Plugin - Handle restore operations
 * @package   plagiarism_copyleaks
 * @copyright 2024 Copyleaks
 * @author    Shade Amasha <shadea@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/enums/plagiarism_copyleaks_enums.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
require_once($CFG->dirroot . '/course/lib.php');


class restore_plagiarism_copyleaks_plugin extends restore_plagiarism_plugin {

    /**
     * Return the paths of the module data along with the function used for restoring that data.
     */
    protected function define_module_plugin_structure() {
        $paths = array();
        $paths[] = new restore_path_element('copyleaks_config', $this->get_pathfor('copyleaks_configs/copyleaks_config'));

        return $paths;
    }

    /**
     * Restore the Copyleaks configs for this module,
     * This will only be done only if the module is from the same site it was backed up from.
     */
    public function process_copyleaks_config($data) {
        global $DB;

        if (!$this->task->is_samesite()) {
            return;
        }

        $data = (object)$data;
        $data->name = $data->name;
        $data->cm = $this->task->get_moduleid();
        $data->value = $data->value;
        $data->config_hash = $data->cm . "_" . $data->name;

        if (!$DB->insert_record('plagiarism_copyleaks_config', $data)) {
            \plagiarism_copyleaks_logs::add(
                "failed to insert new database record for : " .
                "plagiarism_copyleaks_config, Cannot create new config record for cmid: $data->cm " .
                "config name: $data->name",
                "INSERT_RECORD_FAILED"
            );
        }
    }

    /**
     * After restoring a course module, If the module is eligible for Copyleaks's plagiarism detection,
     * duplicate the course module data on Copyleaks servers.
     * This will only be done only if the module is from the same site it was backed up from.
     */
    public function after_restore_module() {
        global $DB;

        if (!$this->task->is_samesite()) {
            return;
        }

        $newcmid = $this->task->get_moduleid();
        if (!\plagiarism_copyleaks_moduleconfig::is_module_enabled($this->task->get_modulename(), $newcmid)) {
            return;
        }

        $plan = $this->task->get_info();
        $courseid = $this->task->get_courseid();
        $originalcmid = $this->task->get_old_moduleid();

        if ($plan->type === "course" || $plan->type === "activity") {
            $moduledata = array(
            'course_id' => $courseid,
            'original_cm_id' => $originalcmid,
            'new_cm_id' => $newcmid,
            'status' => plagiarism_copyleaks_cm_duplication_status::QUEUED,
            );

            if (!$DB->insert_record('plagiarism_copyleaks_cm_copy', $moduledata)) {
                \plagiarism_copyleaks_logs::add(
                "failed to insert new database record for : " .
                "plagiarism_copyleaks_cm_copy, Cannot create new cm duplication record for cmid $newcmid",
                "INSERT_RECORD_FAILED"
                );
            }
        }
    }

    /**
     * Creates a dummy path element in order to be able to execute code after restore.
     *
     * @return restore_path_element[]
     */
    public function define_course_plugin_structure() {

        // Dummy path element is needed in order for after_restore_course() to be called.
        return [new restore_path_element('copyleaks_course', $this->get_pathfor('/copyleakscourse'))];
    }

    /**
     * Dummy process method.
     *
     * @return void
     */
    public function process_copyleaks_course() {
    }

    /**
     *  After course restoration, upsert course data to Copyleaks and sync if Copyleaks is enabled and API is connected.
     */
    public function after_restore_course() {
        // Ensure task is from the same site.
        if (!$this->task->is_samesite()) {
            return;  // Exit early if the course restoration task is from a different site.
        }

        // Fetch the course ID and retrieve the course information.
        $courseid = $this->task->get_courseid();
        $course = get_course($courseid);

        // Check if Copyleaks is enabled for any course module; if not, exit early.
        if (!plagiarism_copyleaks_moduleconfig::is_copyleaks_enabled_for_any_module($courseid)) {
            return;
        }

        $synccourse = false;
        $startdate = isset($course->startdate) ? (new DateTime())->setTimestamp($course->startdate)->format('Y-m-d') : null;
        // Prepare the course data for upsert.
        $data = [
            "id" => $courseid,
            "name" => $course->fullname,
            "startdate" => $startdate
        ];

        /**
         * Sync course data (users, groups, groupings).
         * If the Copyleaks API connection is available.
         * And the couse has at last one assignment with copyleaks enabled.
         */
        if (
            plagiarism_copyleaks_dbutils::is_copyleaks_api_connected() &&
            plagiarism_copyleaks_moduleconfig::is_copyleaks_enabled_for_any_module($courseid, "assign")
        ) {
            $synccourse = true;
        }
        // Initialize Copyleaks communication and upsert course data.
        $cl = new \plagiarism_copyleaks_comms();
        $cl->upsert_course($data, $courseid, $synccourse);
    }
}
