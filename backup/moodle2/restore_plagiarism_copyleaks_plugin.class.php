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

// Get helper methods.
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_moduleconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/enums/plagiarism_copyleaks_enums.php');

class restore_plagiarism_copyleaks_plugin extends restore_plagiarism_plugin
{


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
      //plagiarism_copyleaks_allowstudentaccess
      $DB->insert_record('plagiarism_copyleaks_config', $data);
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

    $plan = $this->task->get_info();
    if ($this->task->is_samesite()) {
      $coursemoduleid = $this->task->get_moduleid();
      if (plagiarism_copyleaks_moduleconfig::is_module_enabled($this->task->get_modulename(), $coursemoduleid)) {
        $oldcoursemoduleid = $this->task->get_old_moduleid();
        $courseid = $this->task->get_courseid();
        // cached course modinfo (use it or use  get_coursemodule_from_id "uses 2 db querys")
        $cm = get_fast_modinfo($courseid)->get_cm($oldcoursemoduleid);

        $moduledata = array(
          'coursemoduleid' => $coursemoduleid,
          'oldcoursemoduleId' => $oldcoursemoduleid,
          'courseid' => $courseid,
          'createddate' => $cm->added,
        );

        if ($plan->type === "course") {
          if ($DB->record_exists('plagiarism_copyleaks_bgtasks', array('courseid' => $courseid))) {
            $record = $DB->get_record('plagiarism_copyleaks_bgtasks', array('courseid' => $courseid));
            $payload = json_decode($record->payload, true);

            $payload[] = $moduledata;

            $record->payload = json_encode($payload);
            $record->executiontime = strtotime('+ 2 minutes');
            $DB->update_record('plagiarism_copyleaks_bgtasks', $record);
          } else {
            // Record does not exist, create a new one
            $payload = json_encode([$moduledata]);
            $newbgtaskrecord = array(
              'task' => plagiarism_copyleaks_background_tasks::DUPLICATE_COURSES_DATA,
              'payload' => $payload,
              'executiontime' => strtotime('+ 2 minutes'),
              'courseid' => $courseid,
            );
            $DB->insert_record('plagiarism_copyleaks_bgtasks', $newbgtaskrecord);
          }

          $configdata = array(
            'name' => 'plagiarism_copyleaks_pendingduplication',
            'cm' => $coursemoduleid,
            'value' => '1',
            'config_hash' => $coursemoduleid . "_plagiarism_copyleaks_pendingduplication",
          );
          $DB->insert_record('plagiarism_copyleaks_config', $configdata);
        } else if ($plan->type === "activity") {
          $cl = new plagiarism_copyleaks_comms();
          $coursemodules[] = $moduledata;
          $cl->duplicate_course_module(array('coursemodules' => $coursemodules));
        }
      }
    }
  }
}
