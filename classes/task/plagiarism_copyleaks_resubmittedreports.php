<?php


namespace plagiarism_copyleaks\task;

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
      if (!isset($response)) {
        break;
      }

      if (isset($response->$cursor)) {
        $cursor = $response->cursor;
      }
      if (!isset($response->resubmitted) || count($response->resubmitted) == 0) {
        break;
      }
      $resubmittedmodel = $response->resubmitted;
      $canloadmoredata = $response->canLoadMore;
      $oldIds = array_column($resubmittedmodel, 'oldScanId');

      // Extract all the old scan ids from the response
      // for ($i = 0; $i < count($resubmittedmodel); $i++) {
      //   $oldIds[$i] = $resubmittedmodel[$i]->oldScanId;
      // }

      // Get all the scans from db with the ids of the 'response' old ids
      $currentdbresults = [];

      for ($i = 0; $i < count($oldIds); $i++) {
        $dbresult = $DB->get_record(
          'plagiarism_copyleaks_files',
          array('externalid' => $oldIds[$i])
        );

        if (!isset($dbresult)) {
          continue;
        }
        $currentdbresults[$i] = $dbresult;
      }

      if ($currentdbresults == null) {
        continue;
      }

      $timestamp = time();
      $succeedids = [];

      // For each db result - Replace the new data
      for ($k = 0; $k < count($currentdbresults); $k++) {
        if ($currentdbresults[$k]->externalid == null) {
          continue;
        }

        for ($i = 0; $i < count($resubmittedmodel); $i++) {
          if ($currentdbresults[$k]->externalid == $resubmittedmodel[$k]->newScanId) {
            $currentdbresults[$k]->externalid = $resubmittedmodel[$k]->newScanId;
            $currentdbresults[$k]->similarityscore = $resubmittedmodel[$k]->similarityscore;
            $currentdbresults[$k]->lastmodified = $timestamp;

            // Update in the DB
            if (!$DB->update_record('plagiarism_copyleaks_files',  $currentdbresults[$k])) {
              \plagiarism_copyleaks_logs::add(
                "Update record failed (CM: " . $resubmittedmodel[0]->originalityReport->courseModuleId . ", User: "
                  . $currentdbresults[0]->userid . ") - ",
                "UPDATE_RECORD_FAILED"
              );
            } else {
              array_push($succeedids,  $currentdbresults[$k]->externalid);
            }
          }
        }
      }
    }
    // Send request with ids who successfully changed in moodle db to deletion in the Google data store
    $copyleakscomms->delete_resubmitted_ids($succeedids);
  }
}
