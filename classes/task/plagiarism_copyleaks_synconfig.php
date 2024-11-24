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

namespace plagiarism_copyleaks\task;

use stdClass;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/enums/plagiarism_copyleaks_enums.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');

/**
 * Copyleaks Plagiarism Plugin - Handle sync configs.
 */
class plagiarism_copyleaks_synconfig extends \core\task\scheduled_task {
    /**
     * Get scheduler name, this will be shown to admins on schedulers dashboard.
     */
    public function get_name() {
        return get_string('clsyncconfigtask', 'plagiarism_copyleaks');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');

        $this->handle_configs_sync();
    }

    /**
     * Handle sync config to plugin integration and course modules.
     */
    private function handle_configs_sync() {
        $copyleakscomms = new \plagiarism_copyleaks_comms();
        $canloadmoredata = true;

        if (!\plagiarism_copyleaks_comms::test_copyleaks_connection('scheduler_task')) {
            return;
        }

        while ($canloadmoredata) {

            $succeedids = [];
            $response = $copyleakscomms->get_unsynced_configs();
            if (!is_object($response) || !isset($response->data) || count($response->data) == 0) {
                break;
            }

            $configs = $response->data;
            $canloadmoredata = $response->canLoadMore;

            foreach ($configs as $config) {
                if (is_bool($config->enableAIResultViewForStudent)) {
                    $aiviewconfig = $this->get_config_by_name_and_cm(
                        PLAGIARISM_COPYLEAKS_ENABLE_AI_VIEW_FOR_STUDENT,
                        $config->courseModuleId
                    );
                    $aiviewconfig->value = $config->enableAIResultViewForStudent;
                    $this->upsert_config_to_table($aiviewconfig, PLAGIARISM_COPYLEAKS_ENABLE_AI_VIEW_FOR_STUDENT);
                } else {
                    // Deletion refers only to the integration config in case the config is null.
                    $this->delete_config_to_table($config, PLAGIARISM_COPYLEAKS_ENABLE_AI_VIEW_FOR_STUDENT);
                }

                if (is_bool($config->enablePlagiarismResultViewForStudent)) {
                    $plagviewconfig = $this->get_config_by_name_and_cm(
                        PLAGIARISM_COPYLEAKS_ENABLE_PLAGIARISM_VIEW_FOR_STUDENT,
                        $config->courseModuleId
                    );
                    $plagviewconfig->value = $config->enablePlagiarismResultViewForStudent;
                    $this->upsert_config_to_table($plagviewconfig, PLAGIARISM_COPYLEAKS_ENABLE_PLAGIARISM_VIEW_FOR_STUDENT);
                } else {
                    $this->delete_config_to_table($config, PLAGIARISM_COPYLEAKS_ENABLE_PLAGIARISM_VIEW_FOR_STUDENT);
                }

                if (is_bool($config->enableWritingAssistantResultViewForStudent)) {
                    $wfviewconfig = $this->get_config_by_name_and_cm(
                        PLAGIARISM_COPYLEAKS_ENABLE_WF_VIEW_FOR_STUDENT,
                        $config->courseModuleId
                    );
                    $wfviewconfig->value = $config->enableWritingAssistantResultViewForStudent;
                    $this->upsert_config_to_table($wfviewconfig, PLAGIARISM_COPYLEAKS_ENABLE_WF_VIEW_FOR_STUDENT);
                } else {
                    $this->delete_config_to_table($config, PLAGIARISM_COPYLEAKS_ENABLE_WF_VIEW_FOR_STUDENT);
                }

                array_push($succeedids,  $config->courseModuleId);
            }

            if (count($succeedids) > 0) {
                $copyleakscomms->delete_synced_config_by_keys($succeedids);
            }
        }
    }

    /**
     * Get config by name an course module.
     */
    private function get_config_by_name_and_cm($name, $cm) {
        global $DB;
        $config = $DB->get_record('plagiarism_copyleaks_config', ['name' => $name, 'cm' => $cm]);

        if ($config == null) {
            $config = new stdClass();
            $config->cm = $cm;
            $config->name = $name;
            $config->config_hash = $cm . "_" . $name;
            $config->isnew = true;
        } else {
            $config->isnew = false;
        }

        return $config;
    }

    /**
     * Upsert config to table.
     */
    private function upsert_config_to_table($config, $name) {
        global $DB;

        if (!$config->isnew) {
            if (!$DB->update_record('plagiarism_copyleaks_config', $config)) {
                $this->write_log($config->courseModuleId,  $name);
            }
        } else {
            if (!$DB->insert_record('plagiarism_copyleaks_config', $config)) {
                $this->write_log($config->courseModuleId,  $name);
            }
        }
    }

    /**
     * In Case the integration config is not locked at the settings then we'll delete it.
     */
    private function delete_config_to_table($config, $name) {
        global $DB;
        if ($config->courseModuleId != PLAGIARISM_COPYLEAKS_DEFAULT_MODULE_CMID) {
            return;
        }

        if (!$DB->delete_records('plagiarism_copyleaks_config', ['cm' => $config->courseModuleId, 'name' => $name])) {
            $this->write_log($config->courseModuleId, $name, true);
        }
    }


    /**
     * Write log.
     */
    private function write_log($cmid, $configname, $isdelete = false) {
        \plagiarism_copyleaks_logs::add(
            "Failed to update synced config: $configname, of cmid: $cmid",
            $isdelete ? "UPDATE_RECORD_FAILED" : "DELETE_RECORD_FAILED"
        );
    }
}
