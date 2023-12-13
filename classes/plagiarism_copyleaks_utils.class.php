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
     * @param DateTime $targetdate - given date to know how much time left to the date.
     */
    public static function time_left_to_date($targetdate) {
        // Convert target date to DateTime object
        $targetdatetime = $targetdate;

        // Get current date and time
        $currentdatetime = new DateTime();

        // Initialize the result object
        $result = new stdClass();

        if ($targetdate < $currentdatetime) {
            $result->timetype = plagiarism_copyleaks_times::Soon;
            $result->value = 0;
            return $result;
        }

        // Calculate the interval between the two dates
        $interval = $currentdatetime->diff($targetdatetime);

        // Set default values
        $result->timetype = plagiarism_copyleaks_times::Minutes;
        $result->value = $interval->i;

        // Check if time left is under an hour
        if ($interval->h > 0 || $interval->d > 0 || $interval->m > 0 || $interval->y > 0) {
            // Show by hours
            $result->timetype = plagiarism_copyleaks_times::Houres;
            $result->value = $interval->h;

            // Check if time left is less than a day
            if ($interval->d > 0 || $interval->m > 0 || $interval->y > 0) {
                // Show by days
                $result->timetype = plagiarism_copyleaks_times::Days;
                $result->value = $interval->d;

                // Check if time left is less than a month
                if ($interval->m > 0 || $interval->y > 0) {
                    // Show by months
                    $result->timetype = plagiarism_copyleaks_times::Months;
                    $result->value = $interval->m;
                }
            }
        }
        return $result;
    }

    /**
     * @param stdClass $value - contains the enum time type and its value
     */
    public static function get_time_left_str($timeobj) {
        $retstr = get_string('cltimemin', 'plagiarism_copyleaks') . ' ' . $timeobj->value . ' ';
        switch ($timeobj->timetype) {
            case plagiarism_copyleaks_times::Minutes:
                $retstr  .= get_string('cltimeminutes', 'plagiarism_copyleaks');
                break;
            case plagiarism_copyleaks_times::Houres:
                $retstr  .= get_string('cltimehours', 'plagiarism_copyleaks');
                break;
            case plagiarism_copyleaks_times::Days:
                $retstr  .= get_string('cltimedays', 'plagiarism_copyleaks');
                break;
            case plagiarism_copyleaks_times::Months:
                $retstr  .= get_string('cltimemonths', 'plagiarism_copyleaks');
                break;
            default:
                return get_string('cltimesoon', 'plagiarism_copyleaks');
        }
        return $retstr;
    }
}
