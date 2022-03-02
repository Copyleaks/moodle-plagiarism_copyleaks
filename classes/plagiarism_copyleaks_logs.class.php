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
 * admins logs helper for Copyleaks plugin
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_pluginconfig.class.php');
/**
 * admins logs helper for Copyleaks plugin
 */
class plagiarism_copyleaks_logs {
    /**
     * add log message
     * @param string $errormessage the error message
     * @param string $code the log code
     */
    public static function add($errormessage, $code) {
        // NOTE: we save up to 10 files only.
        $directorypath = self::temp_dir_path() . "/plagiarism_copyleaks/error_logs";
        if (!file_exists($directorypath)) {
            mkdir($directorypath, 0777, true);
        }

        $directoryref = opendir($directorypath);
        $dirfiles = array();

        while ($file = readdir($directoryref)) {
            // Make sure the file includes the prefix.
            if (substr(basename($file), 0, 1) != "." && substr_count(basename($file), PLAGIARISM_COPYLEAKS_LOGS_PREFIX) > 0) {
                $dirfiles[] = basename($file);
            }
        }

        // Sort files.
        sort($dirfiles);

        // Delete older files as we only save up to 10 files.
        for ($i = 0; $i < count($dirfiles) - 10; $i++) {
            unlink($directorypath . "/" . $dirfiles[$i]);
        }

        // Write to log file.
        $newfilepath = $directorypath . "/" . PLAGIARISM_COPYLEAKS_LOGS_PREFIX . gmdate('Y-m-d', time()) . ".txt";
        $newfileref = fopen($newfilepath, 'a');

        // Shown error message and code.
        $messageoutput = date('Y-m-d H:i:s O') . " - " . "(" . $code . ")" . " - " . $errormessage . "\r\n";
        // Remove leading and trailing spaces.
        $messageoutput = preg_replace('/^\s+|\s+$|\s+(?=\s)/', '', $messageoutput);
        // Write to log file a max of 1000 chars.
        $messageoutput = substr($messageoutput, 0, 1000);

        fwrite($newfileref, $messageoutput);
        fclose($newfileref);
    }

    /**
     * display copyleaks logs list OR show a specific log
     * @param string $date if passed, a specific log data will be displayed
     */
    public static function displaylogs($date = null) {
        global  $OUTPUT, $CFG;

        $errordir = self::temp_dir_path() . "/plagiarism_copyleaks/error_logs/";

        if (is_null($date)) {
            $haslogs = false;
            if (file_exists($errordir) && $readerrdir = opendir($errordir)) {
                while (false !== ($file = readdir($readerrdir))) {
                    if (substr_count($file, 'log') > 0) {
                        $haslogs = true;
                        $date = preg_split("/_/", $file);
                        $date = array_pop($date);
                        $date = str_replace('.txt', '', $date);
                        $displayedfilename = 'error_log' . ' (' . userdate(strtotime($date), '%d/%m/%Y') . ')';
                        echo $OUTPUT->box(html_writer::link(
                            $CFG->wwwroot . '/plagiarism/copyleaks/settings.php?tab=copyleakslogs' . '&date=' . $date,
                            $displayedfilename,
                            array('target' => '_blank')
                        ), '');
                    }
                }
            }
            if ($haslogs == false) {
                echo get_string("nologsfound");
            }
        } else {
            $file = PLAGIARISM_COPYLEAKS_LOGS_PREFIX . $date . '.txt';
            if (file_exists($errordir . $file)) {
                header("Content-type: plain/text; charset=UTF-8");
                send_file($errordir . $file, $file, false);
            } else {
                self::add("searched for a missing log file ($file).", "LOG_FILE_NOT_FOUND");
                self::displaylogs(gmdate('Y-m-d', time()));
            }
        }
    }

    /**
     * return temp directory path
     */
    private static function temp_dir_path() {
        global $CFG;
        return $CFG->tempdir;
    }
}
