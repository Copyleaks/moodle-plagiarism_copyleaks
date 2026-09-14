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
 * Copyleaks Plagiarism Plugin - Pull scan results delivered through the Copyleaks report-delivery queue
 * @package   plagiarism_copyleaks
 * @copyright 2026 Copyleaks
 * @author    Shade Amasha <shadea@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_copyleaks\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');

/**
 * Copyleaks Plagiarism Plugin - Pull scan results delivered through the Copyleaks report-delivery queue
 */
class plagiarism_copyleaks_pullreports extends \core\task\scheduled_task {
    /**
     * get scheduler name, this will be shown to admins on schedulers dashboard
     */
    public function get_name() {
        return get_string('clpullreports', 'plagiarism_copyleaks');
    }

    /**
     * execute the task
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
        require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_submissions.class.php');
        $this->pull_reports();
    }

    /**
     * Pull every scan result waiting for this integration and apply it.
     *
     * Runs every minute, unconditionally: results are delivered without the plugin asking for them (the
     * server ignores the instance list on this route for this plugin version), and the poll itself keeps
     * the integration's delivery subscription alive. A response lost on the way back leaves the row
     * pending; plagiarism_copyleaks_recoverreports fetches it again.
     */
    private function pull_reports() {
        try {
            $copyleakscomms = new \plagiarism_copyleaks_comms();
            $scaninstances = $copyleakscomms->get_plagiarism_scans_instances([]);

            // Null when the plugin key/secret are not configured; not an array when an older server answers
            // with an empty body. Neither is worth an error log every minute.
            if (!is_array($scaninstances)) {
                return;
            }

            foreach ($scaninstances as $clscaninstance) {
                \plagiarism_copyleaks_submissions::update_report(
                    $clscaninstance->courseModuleId,
                    $clscaninstance->moodleUserId,
                    $clscaninstance->identitfier,
                    $clscaninstance->scanId,
                    $clscaninstance->status,
                    $clscaninstance->plagiarismScore,
                    $clscaninstance->aiScore,
                    $clscaninstance->writingFeedbackIssues,
                    $clscaninstance->isCheatingDetected,
                    $clscaninstance->errorMessage,
                    $clscaninstance->errorCode,
                );
            }
        } catch (\Throwable $e) {
            \plagiarism_copyleaks_logs::add(
                "Pull reports failed - " . $e->getMessage(),
                "API_ERROR"
            );
        }
    }
}
