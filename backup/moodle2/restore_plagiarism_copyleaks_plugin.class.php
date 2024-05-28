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

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/enums/plagiarism_copyleaks_enums.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');


class restore_plagiarism_copyleaks_plugin extends restore_plagiarism_plugin{

  /**
   * Return the paths of the module data along with the function used for restoring that data.
   */
  protected function define_module_plugin_structure()
  {
    $paths = array();
    $paths[] = new restore_path_element('copyleaks_config', $this->get_pathfor('copyleaks_configs/copyleaks_config'));

    return $paths;
  }

  /**
   * Restore the Copyleaks configs for this module,
   * This will only be done only if the module is from the same site it was backed up from.
   */
  public function process_copyleaks_config($data)
  {
    global $DB;

    if ($this->task->is_samesite()) {
      $data = (object)$data;
      $data->name = $data->name;
      $data->cm = $this->task->get_moduleid();
      $data->value = $data->value;
      $data->config_hash = $data->cm . "_" . $data->name;

      $DB->insert_record('plagiarism_copyleaks_config', $data); //need to handle insertion errors 
    }
  }

  /**
   * After restoring a course module, If the module is eligible for Copyleaks's plagiarism detection, 
   * duplicate the course module data on Copyleaks servers.
   * This will only be done only if the module is from the same site it was backed up from.
   */
  public function after_restore_module()
  {
    global $DB;

    $newcmid = $this->task->get_moduleid();
    if (\plagiarism_copyleaks_moduleconfig::is_module_enabled($this->task->get_modulename(), $newcmid)) {
        $plan = $this->task->get_info();
        $courseid = $this->task->get_courseid();
        $originalcmid = $this->task->get_old_moduleid();
      
        if ($plan->type === "course") {
          $courseid = $this->task->get_courseid();
          $moduledata = array(
            'course_id' => $courseid,
            'original_cm_id' => $originalcmid,
            'new_cm_id' => $newcmid,
            'status' => plagiarism_copyleaks_cm_duplication_status::QUEUED,
          );
          $DB->insert_record('plagiarism_copyleaks_cm_copy', $moduledata); //need to handle insertion errors
        } else if ($plan->type === "activity") {
          $cm = get_coursemodule_from_id('', $newcmid);
          $cl = new plagiarism_copyleaks_comms();
          $coursemodules[] = array(
            'coursemoduleid' => $newcmid,
            'oldcoursemoduleid' => $originalcmid,
            'courseid' => $courseid,
            'createddate' => $cm->added,
          );
          $cl->duplicate_course_modules(array('coursemodules' => $coursemodules));
        }
      }
    }
  
}
