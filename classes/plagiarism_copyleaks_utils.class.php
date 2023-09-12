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
 * plagiarism_copyleaks_utils.class.php
 * @package   plagiarism_copyleaks
 * @copyright 2023 Copyleaks
 * @author    Gil Cohen <gilc@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/constants/plagiarism_copyleaks.constants.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_dbutils.class.php');


/**
 * This class include functions that can be used in multiple places
 */
class plagiarism_copyleaks_utils {

    /**
     * Return the current lang code to use with Copyleaks
     * @return string Supported Copyleaks lang code
     */
    public static function get_lang() {
        $defaultlangcode = 'en';
        try {
            $langcode = str_replace("_utf8", "", current_language());
            $langarray = array(
                'en' => $defaultlangcode,
                'en_us' => $defaultlangcode,
                'fr' => 'fr',
                'fr_ca' => 'fr',
                'es' => 'es',
                'fr' => 'fr',
                'pt' => 'pt',
                'hi' => 'hi',
                'zh' => 'zh',
                'it' => 'it',
                'ja' => 'ja',
                'de' => 'de',
                'tr' => 'tr',
                'ru' => 'ru',
                'ar' => 'ar'
            );
            return (isset($langarray[$langcode])) ? $langarray[$langcode] : $defaultlangcode;
        } catch (Exception $e) {
            return $defaultlangcode;
        }
    }

    /**
     * Get Copyleaks temp course module id .
     * @param string $courseid
     * @return string
     */
    public static function get_copyleaks_temp_course_module_id($courseid) {
        $number = rand(100, 100000);
        $t = time();
        return $courseid . $number . ($t % 10);
    }

    /**
     * Set Copyleaks page navbar breadcrumbs.
     * @param mixed $cm
     * @param mixed $course
     * @return array $breadcrumbs
     */
    public static function set_copyleaks_page_navbar_breadcrumbs($cm, $course) {
        global $CFG;
        $breadcrumbs = [];
        if (isset($cm)) {
            $moodlecontext = get_site();
            $moodlename = $moodlecontext->fullname;
            $coursename = $course->fullname;
            $cmid = $cm == 'new' ? '123' : $cm->id;

            $breadcrumbs = [
                [
                    'url' => "$CFG->wwwroot",
                    'name' => $moodlename,
                ],
                [
                    'url' => "$CFG->wwwroot/course/view.php?id=$course->id",
                    'name' => $coursename,
                ],
                [
                    'url' => "$CFG->wwwroot/mod/assign/view.php?id=$cmid",
                    'name' => $cm == 'new' ? 'New Activity' : $cm->name,
                ],
            ];
        } else {
            $breadcrumbs = [
                [
                    'url' => "$CFG->wwwroot/admin/search.php",
                    'name' => 'Site Administration',
                ],
                [
                    'url' => "$CFG->wwwroot/plagiarism/copyleaks/settings.php",
                    'name' => 'Copyleaks Plugin',
                ],
                [
                    'url' => "$CFG->wwwroot/plagiarism/copyleaks/plagiarism_copyleaks_settings.php",
                    'name' => 'Integration Settings',
                ],
            ];
        }
        return $breadcrumbs;
    }

    /**
     * Get Copyleaks buttom for settings page.
     * @param string $settingsurlparams - assign the url to the link
     * @param bool $isadminform - for note above the link
     * @return string
     */
    public static function get_copyleaks_settings_button_link($settingsurlparams, $isadminform = false, $cmid = null) {
        global $CFG;
        $isbtndisabled = false;
        if (!$isadminform && isset($cmid)) {
            if (plagiarism_copyleaks_moduleconfig::is_course_module_request_queued($cmid)) {
                $isbtndisabled = true;
            }
        }

        $settingsurl = "$CFG->wwwroot/plagiarism/copyleaks/plagiarism_copyleaks_settings.php";
        if (!isset($settingsurlparams) || $settingsurlparams != "") {
            $settingsurl = $settingsurl . $settingsurlparams;
        }

        $text = get_string('clscansettingspagebtntxt', 'plagiarism_copyleaks');
        if (!$isadminform) {
            $text = get_string('clmodulescansettingstxt', 'plagiarism_copyleaks');
        }

        $content = $isbtndisabled ?
            html_writer::div($text, null, array(
                'style' => 'color:#8c8c8c',
                'title' => get_string('cldisablesettingstooltip', 'plagiarism_copyleaks')
            )) :
            html_writer::link("$settingsurl", $text, array('target' => '_blank'));

        return
            "<div class='form-group row'>" .
            "<div class='col-md-3'></div>" .
            "<div class='col-md-9'>" .
            $content
            . "</div>" .
            "</div>";
    }


    /**
     * @param string course module id
     * @return Date or null
     */
    public static function get_course_module_duedate($cmid) {
        global $DB;
        $dateTime = new DateTime();
        $issetdate = false;

        $coursemodule = get_coursemodule_from_id('', $cmid);

        $data = $DB->get_record_select(
            $coursemodule->modname,
            'id = ?',
            array($coursemodule->instance),
            '*'
        );

        switch ($coursemodule->modname) {
            case 'workshop':
                if ($data->completionexpected > 0) {
                    $dateTime->setTimestamp($data->completionexpected);
                    $issetdate = true;
                } else if ($data->submissionend > 0) {
                    $dateTime->setTimestamp($data->submissionend);
                    $issetdate = true;
                }
                break;
            case 'quiz':
                if ($data->timeclose > 0) {
                    $dateTime->setTimestamp($data->timeclose);
                    $issetdate = true;
                }
                break;
            default:
                if ($data->duedate > 0) {
                    $dateTime->setTimestamp($data->duedate);
                    $issetdate = true;
                }
                break;
        }

        return $issetdate ? $dateTime->format('Y-m-d H:i:s') : null;
    }
}
