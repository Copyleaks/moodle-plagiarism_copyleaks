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
 * submission display helpers methods
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_pluginconfig.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_assignmodule.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');

/**
 * submission display helpers methods
 */
class plagiarism_copyleaks_submissiondisplay {
    /**
     * build the displayed output for submission
     * @param array  $submissionref contains all relevant information for the plugin to generate a link
     * @return string displayed output
     */
    public static function output($submissionref) {
        global $OUTPUT, $DB, $USER, $CFG, $COURSE, $PAGE;

        if (!empty($submissionref["file"])) {
            $file = $submissionref["file"];
            $area = $file->get_filearea();
            // Dont show file area of type feedback_files OR introattachment.
            if (in_array($area, array("feedback_files", "introattachment"))) {
                return;
            }
        }

        /* If this is a quiz, retrieve the cmid */
        $quizcomponent = (!empty($submissionref['component'])) ? $submissionref['component'] : "";
        if (empty($submissionref['cmid']) && !empty($submissionref['area']) && $quizcomponent == "qtype_essay") {
            $quizquestions = question_engine::load_questions_usage_by_activity($submissionref['area']);

            // Try to get cm using the questions owning context.
            $context = $quizquestions->get_owning_context();
            if ($context->contextlevel == CONTEXT_MODULE) {
                $submissionref['cmid'] = $context->instanceid;
            }
        }

        $output = "";

        // Get the course module.
        static $coursemodule;
        if (empty($coursemodule)) {
            $coursemodule = get_coursemodule_from_id(
                '',
                $submissionref["cmid"]
            );
        }

        // Get Copyleaks module config.
        static $clmodulesettings;
        if (empty($clmodulesettings)) {
            $clmodulesettings = $DB->get_records_menu(
                'plagiarism_copyleaks_config',
                array('cm' => $submissionref["cmid"]),
                '',
                'name,value'
            );
        }

        // Get Copyleaks plugin admin config.
        static $adminconfig;
        if (empty($adminconfig)) {
            $adminconfig = plagiarism_copyleaks_pluginconfig::admin_config();
        }

        // Is Copyleaks plugin enabled for this module type?
        static $ismodenabledforcl;
        if (empty($ismodenabledforcl)) {
            $moduleconfigname = 'plagiarism_copyleaks_mod_' . $coursemodule->modname;
            if (!isset($adminconfig->$moduleconfigname) || $adminconfig->$moduleconfigname !== '1') {
                // Plugin not enabled for this module.
                $ismodenabledforcl = false;
            } else {
                $ismodenabledforcl = true;
            }
        }

        // Exit if plugin is disabled or only disabled for this module.
        $enabledproperty = 'plagiarism_copyleaks_enable';
        if (empty($ismodenabledforcl) || empty($clmodulesettings[$enabledproperty])) {
            return $output;
        }

        // Init context.
        static $ctx;
        if (empty($ctx)) {
            $ctx = context_course::instance($coursemodule->course);
        }

        // Check current user if instructor.
        static $isinstructor;
        if (empty($isinstructor)) {
            $isinstructor = plagiarism_copyleaks_assignmodule::is_instructor($ctx);
        }

        // Incase of students, check if he is allowed to view the plagiairsm report progress & results.
        if (!$isinstructor && empty($clmodulesettings['plagiarism_copyleaks_allowstudentaccess'])) {
            return;
        }

        // Proceed if content / files exists and cmid was set.
        if (!empty($submissionref["cmid"] && (!empty($submissionref["content"]) || !empty($submissionref["file"])))) {

            $subitemid = 0;
            $subidentifier = '';

            // Set identifier & itemid for files.
            if (!empty($submissionref["content"])) {
                $subidentifier = sha1($submissionref["content"]);
            } else if (!empty($submissionref["file"])) {
                $subitemid = $file->get_itemid();
                $subidentifier = $file->get_pathnamehash();
            }

            // Passed userid is 0 for group submissions, change it to the current userid.
            if ($submissionref['userid'] == 0 && !$isinstructor) {
                $submissionref['userid'] = $USER->id;
            }

            /*
               If instructor is submitting on behalf of a student, set the author to the student.
            */
            $submittedfile = null;
            $assignsubmission = $DB->get_record(
                'assign_submission',
                array('id' => $subitemid),
                'id, groupid'
            );
            if ((!empty($assignsubmission->groupid)) && ($coursemodule->modname == "assign")) {
                $submittedfiles = $DB->get_records(
                    'plagiarism_copyleaks_files',
                    ['itemid' => $subitemid, 'cm' => $coursemodule->id, 'identifier' => $subidentifier],
                    'lastmodified DESC',
                    '*',
                    0,
                    1
                );

                $submittedfile = reset($submittedfiles);

                if (!$submittedfile) {
                    return;
                }

                $author = $submittedfile->userid;
                $submissionref['userid'] = $author;
            } else if ($coursemodule->modname == "assign") {
                $author = $submissionref['userid'];
                if ($subitemid != 0) {
                    $author = plagiarism_copyleaks_assignmodule::get_author($subitemid);
                    $submissionref['userid'] = (!empty($author)) ? $author : $submissionref['userid'];
                }
            }

            $submissionusers = array($submissionref["userid"]);
            switch ($coursemodule->modname) {
                case "assign":
                    $moduledata = $DB->get_record($coursemodule->modname, array('id' => $coursemodule->instance));
                    if ($moduledata->teamsubmission) {
                        // Allow all related group users to see the originality link & score.
                        require_once($CFG->dirroot . '/mod/assign/locallib.php');
                        $assignmentref = new assign($ctx, $coursemodule, null);

                        $submissionusers = array();
                        if ($groupref = $assignmentref->get_submission_group($submissionref["userid"])) {
                            $groupmembers = groups_get_members($groupref->id);
                            $submissionusers = array_keys($groupmembers);
                        }
                    }
                    break;
            }

            // Show the originality link and score for submission.
            if ($isinstructor || in_array($USER->id, $submissionusers)) {

                // If plagiarismfile is null, try to init it again.
                if (is_null($submittedfile)) {
                    $query = "cm = ? AND identifier = ?";
                    $queryparams = array($submissionref["cmid"], $subidentifier);

                    if (count($submissionusers) > 0) {
                        $query .= " AND userid IN (";
                        foreach ($submissionusers as $userid) {
                            $query .= "?,";
                            array_push($queryparams, $userid);
                        }
                        $query  = substr($query, 0, -1);
                        $query .= ")";
                    }

                    $submittedfiles = $DB->get_records_select(
                        'plagiarism_copyleaks_files',
                        $query,
                        $queryparams,
                        '',
                        '*',
                        0,
                        1
                    );

                    $submittedfile = current($submittedfiles);
                }

                if ($submittedfile) {
                    $clpoweredbycopyleakstxt = get_string('clpoweredbycopyleaks', 'plagiarism_copyleaks');
                    switch ($submittedfile->statuscode) {
                        case 'success':
                            $scorelevelclass = '';
                            if ($submittedfile->similarityscore <= 40) {
                                $scorelevelclass = "score-level-low";
                            } else if ($submittedfile->similarityscore <= 80) {
                                $scorelevelclass = "score-level-mid";
                            } else {
                                $scorelevelclass = "score-level-high";
                            }

                            $results["score"] = $submittedfile->similarityscore;

                            $results['reporturl'] =
                                "$CFG->wwwroot/plagiarism/copyleaks/plagiarism_copyleaks_report.php" .
                                "?cmid=$submittedfile->cm&userid=$submittedfile->userid" .
                                "&identifier=$submittedfile->identifier&modulename=$coursemodule->modname";

                            $results['downloadpdfurl'] = '';

                            $cheatdetectionicon = html_writer::tag(
                                'div',
                                $OUTPUT->pix_icon(
                                    'copyleaks-alert-icon',
                                    get_string('clcheatingdetected', 'plagiarism_copyleaks'),
                                    'plagiarism_copyleaks',
                                    array('class' => 'cls-icon-no-margin')
                                ),
                                null
                            );
                            $cmid = $submissionref['cmid'];
                            $downloadpdfurl = "$CFG->wwwroot/plagiarism/copyleaks/plagiarism_copyleaks_download_report_pdf.php" .
                                "?cmid=$cmid&userid=$submittedfile->userid&identifier=$submittedfile->identifier&modulename=$coursemodule->modname";

                            $similaritystring = html_writer::tag(
                                'div',
                                html_writer::tag(
                                    'div',
                                    html_writer::tag(
                                        'div',
                                        $OUTPUT->pix_icon(
                                            'copyleaks-logo-new',
                                            $clpoweredbycopyleakstxt,
                                            'plagiarism_copyleaks',
                                            array('class' => 'cls-logo-new')
                                        ),
                                        array('class' => 'cls-icon')
                                    ) .
                                        html_writer::tag(
                                            'div',
                                            // Download report
                                            html_writer::tag(
                                                'a',
                                                $OUTPUT->pix_icon(
                                                    'copyleaks-download-icon',
                                                    get_string('cldownloadreport', 'plagiarism_copyleaks'),
                                                    'plagiarism_copyleaks',
                                                    array('class' => 'cls-icon-no-margin')
                                                ),
                                                array('href' => $downloadpdfurl, 'target' => '_blank', 'title' => get_string('clopenreport', 'plagiarism_copyleaks'))
                                            ) .
                                                // Open report page
                                                html_writer::tag(
                                                    'a',
                                                    $OUTPUT->pix_icon(
                                                        'copyleaks-open-url-icon',
                                                        get_string('clopenreport', 'plagiarism_copyleaks'),
                                                        'plagiarism_copyleaks',
                                                        array('class' => 'cls-icon-no-margin')
                                                    ),
                                                    array('href' => $results['reporturl'], 'target' => '_blank', 'title' => get_string('clopenreport', 'plagiarism_copyleaks'))
                                                ) .
                                                // Copy report URL
                                                html_writer::tag(
                                                    'div',
                                                    $OUTPUT->pix_icon(
                                                        'copyleaks-copy-icon',
                                                        get_string('clcopyreporturl', 'plagiarism_copyleaks'),
                                                        'plagiarism_copyleaks',
                                                        array('class' => 'cls-icon-no-margin')
                                                    ),
                                                    array(
                                                        'id' => "cls-copy-container_" . $submittedfile->externalid,
                                                        'class' => "cls-copy-container",
                                                        "onclick" => 'cl_copy_to_clipboard()'
                                                    )
                                                ),
                                            array('class' => 'cls-action')
                                        ),
                                    array('class' => 'cls-details-header')
                                ) .
                                    html_writer::tag(
                                        'div',
                                        // AI score
                                        html_writer::tag(
                                            'div',
                                            html_writer::tag(
                                                'div',
                                                get_string('claicontentscore', 'plagiarism_copyleaks'),
                                                array('class' => 'cls-text-result')
                                            ) .
                                                html_writer::tag(
                                                    'div',
                                                    html_writer::tag(
                                                        'span',
                                                        '',
                                                        array('class' => "score-level $scorelevelclass")
                                                    ) . $results["score"],
                                                    array('class' => 'cls-score-container')
                                                ),
                                            array('class' => 'cls-result-item')
                                        ) .
                                            // Plagiarism score
                                            html_writer::tag(
                                                'div',
                                                html_writer::tag(
                                                    'div',
                                                    get_string('clplagiarismscore', 'plagiarism_copyleaks'),
                                                    array('class' => 'cls-text-result')
                                                ) .
                                                    // Cheat detection alert
                                                    ($submittedfile->ischeatingdetected ? $cheatdetectionicon : '') .
                                                    html_writer::tag(
                                                        'div',
                                                        html_writer::tag(
                                                            'span',
                                                            '',
                                                            array('class' => "score-level $scorelevelclass")
                                                        ) . $results["score"],
                                                        array('class' => 'cls-score-container')
                                                    ),
                                                array('class' => 'cls-result-item')
                                            ) .
                                            // Grammer cases
                                            html_writer::tag(
                                                'div',
                                                html_writer::tag(
                                                    'div',
                                                    get_string('clgrammerissues', 'plagiarism_copyleaks'),
                                                    array('class' => 'cls-text-result')
                                                ) .
                                                    html_writer::tag(
                                                        'div',
                                                        ($results["grammercases"] ? html_writer::tag(
                                                            'span',
                                                            '',
                                                            array('class' => "score-level $scorelevelclass")
                                                        ) . $results["score"] : 'N/A'),
                                                        array('class' => 'cls-score-container')
                                                    ),
                                                array('class' => 'cls-result-item')
                                            ),
                                        array('class' => 'cls-details-content')
                                    ),
                                array('class' => 'cls-large-report-details cls-mini-report')
                            );

                            // Copy to clipboard.
                            $urlpdf = $results['reporturl'];
                            $externalid = $submittedfile->externalid;
                            $similaritystring .= "<input type='text' style='display:none;' name='clsreporturl' id='clsreporturlinput_$externalid' value='$urlpdf'>"  .
                                "<script>function cl_copy_to_clipboard() {" .
                                "var copyText = document.getElementById('clsreporturlinput_$externalid');" .
                                'copyText.style.display = "block";' .
                                'copyText.select();' .
                                'copyText.setSelectionRange(0, 99999);' .
                                'copyText.style.display = "none";' .
                                'navigator.clipboard.writeText(copyText.value);' .
                                'alert("Copied the text: " + copyText.value);' .
                                "}</script>";

                            $output .= html_writer::tag(
                                'div',
                                $similaritystring,
                                array('class' => 'copyleaks')
                            );
                            break;
                        case 'error':
                            if ($isinstructor) {
                                $currenturl = $PAGE->url->get_path();
                                $params = $PAGE->url->params();
                                $querystring = http_build_query($params, '', '&');
                                $route = str_replace('/moodle', '', $currenturl);
                                $route = $route . '?' . "$querystring";

                                $cmid = $submissionref['cmid'];
                                $courseid = $COURSE->id;

                                $resubmiturl = "$CFG->wwwroot/plagiarism/copyleaks/plagiarism_copyleaks_resubmit_handler.php" .
                                    "?fileid=$submittedfile->id&cmid=$cmid&courseid=$courseid&route=$route";

                                try {

                                    $outputcontent = html_writer::tag(
                                        'div',
                                        html_writer::tag(
                                            'div',
                                            $OUTPUT->pix_icon(
                                                'copyleaks-alert-icon',
                                                $submittedfile->errormsg,
                                                'plagiarism_copyleaks',
                                                array('class' => 'cls-icon-no-margin')
                                            ) .
                                                html_writer::tag(
                                                    'span',
                                                    get_string('clscanfailedbtn', 'plagiarism_copyleaks'),
                                                    null
                                                ),
                                            array('class' => 'failed content')
                                        ) .
                                            html_writer::tag(
                                                'div',
                                                html_writer::tag(
                                                    'a',
                                                    $OUTPUT->pix_icon(
                                                        'copyleaks-retry-icon',
                                                        null,
                                                        'plagiarism_copyleaks',
                                                        array('class' => 'cls-icon-no-margin')
                                                    ) .
                                                        html_writer::tag(
                                                            'span',
                                                            get_string('cltryagainbtn', 'plagiarism_copyleaks'),
                                                            null
                                                        ),
                                                    array('href' => $resubmiturl)
                                                ),
                                                array('class' => 'retry content')
                                            ),
                                        array('class' => 'small-report-details error cls-mini-report')
                                    );
                                } catch (Exception $e) {
                                    \plagiarism_copyleaks_logs::add(
                                        "Fail to add resubmit button - " . $e->getMessage(),
                                        "UI_ERROR"
                                    );
                                }

                                $output = html_writer::tag(
                                    'div',
                                    $outputcontent,
                                    array('class' => 'copyleaks')
                                );
                            }
                            break;
                        case 'pending':
                            $outputcontent = html_writer::tag(
                                'div',
                                html_writer::tag(
                                    'div',
                                    $OUTPUT->pix_icon(
                                        'copyleaks-logo-new',
                                        $clpoweredbycopyleakstxt,
                                        'plagiarism_copyleaks',
                                        array('class' => 'cls-icon-no-margin', 'class' => 'cls-logo-new')
                                    ),
                                    array('class' => 'cls-icon')
                                ) .
                                    html_writer::tag(
                                        'div',
                                        // Spinner
                                        html_writer::tag(
                                            'div',
                                            '',
                                            array('class' => 'cls-spinner')
                                        ) .
                                            html_writer::tag(
                                                'span',
                                                get_string('clscaninprogress', 'plagiarism_copyleaks'),
                                                null
                                            ),
                                        array('class' => 'content')
                                    ),
                                array('class' => 'small-report-details cls-mini-report in-progress')
                            );
                            $output = html_writer::tag(
                                'div',
                                $outputcontent,
                                array('class' => 'copyleaks')
                            );
                            break;
                        case 'queued':
                            $queuedtxt = get_string(
                                'clplagiarisequeued',
                                'plagiarism_copyleaks',
                                date("F j, Y, g:i a", $submittedfile->scheduledscandate)
                            );

                            $date = new DateTime();
                            $date->setTimestamp($submittedfile->scheduledscandate);
                            $timeleft = plagiarism_copyleaks_utils::time_left_to_date($date);
                            $timeleftstr = plagiarism_copyleaks_utils::get_time_left_str($timeleft);

                            $dateicon = $OUTPUT->pix_icon(
                                'copyleaks-date-logo',
                                $queuedtxt,
                                'plagiarism_copyleaks',
                                array('class' => 'cls-icon-no-margin')
                            );
                            $queuedwrapper = html_writer::tag(
                                'div',
                                html_writer::tag(
                                    'div',
                                    html_writer::tag(
                                        'div',
                                        $OUTPUT->pix_icon(
                                            'copyleaks-logo-new',
                                            $clpoweredbycopyleakstxt,
                                            'plagiarism_copyleaks',
                                            array('class' => 'cls-icon-no-margin', 'class' => 'cls-logo-new')
                                        ),
                                        array('class' => 'cls-icon')
                                    ) .
                                        html_writer::tag(
                                            'div',
                                            get_string('clscheduledintime', 'plagiarism_copyleaks', $timeleftstr),
                                            array('class' => 'scheduled-text')
                                        ),
                                    array('class' => 'cls-details-header')
                                ) .
                                    html_writer::tag(
                                        'div',
                                        html_writer::tag(
                                            'div',
                                            html_writer::tag(
                                                'div',
                                                get_string('claicontentscheduled', 'plagiarism_copyleaks'),
                                                array('class' => 'cls-text-result')
                                            ) . $dateicon,
                                            array('class' => 'cls-scheduled-item')
                                        ) .
                                            html_writer::tag(
                                                'div',
                                                html_writer::tag(
                                                    'div',
                                                    get_string('clplagiarismcontentscheduled', 'plagiarism_copyleaks'),
                                                    array('class' => 'cls-text-result')
                                                ) . $dateicon,
                                                array('class' => 'cls-scheduled-item')
                                            ) .
                                            html_writer::tag(
                                                'div',
                                                html_writer::tag(
                                                    'div',
                                                    get_string('clgrammarcontentscheduled', 'plagiarism_copyleaks'),
                                                    array('class' => 'cls-text-result')
                                                ) . $dateicon,
                                                array('class' => 'cls-scheduled-item')
                                            ),
                                        array('class' => 'content')
                                    ),
                                array('class' => 'small-report-details cls-mini-report scheduled')
                            );

                            $output = html_writer::tag(
                                'div',
                                $queuedwrapper,
                                array('class' => 'copyleaks')
                            );
                            break;
                        default:
                            break;
                    }
                }
            }
        }

        return "<br/>$output<br/>";
    }
}
