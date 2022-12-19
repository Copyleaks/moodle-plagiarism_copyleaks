<?php


namespace plagiarism_copyleaks\task;

use core\check\result;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');

/**
 * Copyleaks Plagiarism Plugin - Handle Resubmit Files
 */

class plagiarism_copyleaks_resubmittedreports extends \core\task\scheduled_task {
  /**
   * Get scheduler name, this will be shown to admins on schedulers dashboard.
   */
  public function get_name() {
    return get_string('clsendresubmissionsfiles', 'plagiarism_copyleaks');
  }

  public function execute() {
    global $CFG;
    require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_submissions.class.php');
    require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_comms.class.php');
    require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_assignmodule.class.php');

    $this->send_resubmitted_files();
  }

  private function send_resubmitted_files() {
    global $DB;

    $copyleakscomms = new \plagiarism_copyleaks_comms();
    $cursor = '';
    $canloadmoredata = true;

    while ($canloadmoredata) {
      $response =  $copyleakscomms->get_resubmit_reports_ids($cursor);
      if (!is_object($response) || !isset($response->resubmitted) || count($response->resubmitted) == 0) {
        break;
      }

      $cursor = $response->cursor;
      $resubmittedmodel = $response->resubmitted;
      $canloadmoredata = $response->canLoadMore;
      $oldIds = array_column($resubmittedmodel, 'oldScanId');

      $currentdbresults = [];

      // Get all the scans from db with the ids of the 'response' old ids
      $dbresult = $DB->get_recordset_list('plagiarism_copyleaks_files', 'externalid', $oldIds);
      if (!isset($dbresult)) {
        break;
      }

      // Getting the result by the consition the all the external ids must contains in $oldIds
      foreach ($dbresult as $result) {
        $currentdbresults[] = $result;
      }

      if (count($currentdbresults) == 0) {
        continue;
      }

      $timestamp = time();
      $succeedids = [];
      $idx = 0;

      // For each db result - Replace the new data
      foreach ($currentdbresults as $currentresult) {
        if ($currentresult->externalid == null) {
          continue;
        }

        // Get the copyleaks db entity with the old id
        $filtered = array_filter(
          $resubmittedmodel,
          function ($element) use ($currentresult) {
            if ($element->oldScanId == $currentresult->externalid) {
              return $element;
            }
          }
        );
        $curr =  $filtered[$idx++];

        if (isset($curr) && $curr != null) {
          $currentresult->externalid = $curr->newScanId;
          $currentresult->similarityscore = $curr->plagiarismScore;
          $currentresult->lastmodified = $timestamp;
          // Update in the DB
          if (!$DB->update_record('plagiarism_copyleaks_files',  $currentresult)) {
            \plagiarism_copyleaks_logs::add(
              "Update resubmitted failed (old scan id: " . $curr->oldScanId . ", new scan id: "
                . $curr->newScanId . ") - ",
              "UPDATE_RECORD_FAILED"
            );
          } else {
            array_push($succeedids,  $currentresult->externalid);
          }
        }
      }
      // Send request with ids who successfully changed in moodle db to deletion in the Google data store
      if (count($succeedids) > 0) {
        $copyleakscomms->delete_resubmitted_ids($succeedids);
      }
    }
  }
}
