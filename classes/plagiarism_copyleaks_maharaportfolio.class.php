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
 * Manage connection to Mahara for portfolio 'HTML Lite' export.
 * @package   plagiarism_copyleaks
 * @copyright 2025 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_pluginconfig.class.php');
/**
 * Class plagiarism_copyleaks_maharaportfolio
 */
class plagiarism_copyleaks_maharaportfolio {

    private $instanceid;
    private $context;
    private $userid;
    private $relateduserid;
    private $submission;
    private $fileinfo;
    private $host;
    private $serviceUrl;
    private $wstoken;
    private $fileurl;
    private $zipfile;

    /**
     * Constructor.
     *
     * @param array[int] Data about this assignsubmission_maharaws submission:
     *      $instanceid instance id of the assignment.
     *      $submissionid id of the submission.
     *      $userid id of the user submitting.
     *      $relateduserid id of the user being submitted onbehalf of.
     * @throws dml_exception
     */
    public function __construct($data) {
        global $CFG, $DB;
        $this->instanceid = $data->instanceid;
        $this->userid = $data->userid;
        $this->relateduserid = $data->relateduserid;

        // Get submission context id from context instance id.
        $this->context = \context_module::instance($this->instanceid);

        // Load submission details.
        $this->submission = $this->get_mahara_submission($data->submissionid);

        // Set site config.
        $config = plagiarism_copyleaks_pluginconfig::admin_config();
        $this->host = $CFG->wwwroot;
        $this->serviceUrl = $config->plagiarism_copyleaks_maharawsurl;
        $this->wstoken = $config->plagiarism_copyleaks_maharawshtmllitetoken;

        // Check if there's a zip file already downloaded by previous processing.
        $fs = get_file_storage();
        $fileinfo = array(
                'contextid' => $this->context->id,
                'instanceid' => $this->instanceid,
                'component' => 'plagiarism_copyleaks',
                'filearea' => 'htmllite',
                'itemid' => $this->submission->id,
                'filepath' => '/maharahtmllite/',
                'filename' => 's' . $this->submission->id . '.zip'
        );
        $this->fileinfo = $fileinfo;

        if ($file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
                $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename'])) {
            mtrace('Copyleaks: plagiarism_copyleaks_maharaportfolio: Using previously downloaded zip file.');
            $this->zipfile = $file;
        }
    }

    /**
     * Get Mahara submission information from the database
     *
     * @param  int $submissionid
     * @return mixed
     */
    private function get_mahara_submission($submissionid) {
        global $DB;

        return $DB->get_record('assignsubmission_maharaws', array('submission' => $submissionid));
    }

    /**
     * Run all the steps to download a portfolio and add it to the Ouriginal queue.
     *
     */
    public function get_mahara_file() {
        if (empty($this->zipfile)) {
            $this->get_fileurl();
            $this->download_zip();
        }
        return $this->fetch_mahara_file();
    }

    /**
     * Make initial Mahara HTML Lite API request to generate files for the portfolio.
     *
     */
    private function get_fileurl() {
        mtrace('Copyleaks: plagiarism_copyleaks_maharaportfolio: Calling Mahara HTML Lite API.');

        // Get fileurl.
        $params = [
            'views[0][viewid]' => $this->submission->viewid,
            'views[0][iscollection]' => $this->submission->iscollection,
            'views[0][submittedhost]' => $this->host,
            'views[0][exporttype]' => 'htmllite'
            // 'views[0][exporttype]' => 'pdflite'
        ];

        $this->fileurl = $this->webservice_call('mahara_submission_generate_view_for_plagiarism_test', $params);        
    }

    /**
     * Webservice call helper.
     *
     * @param string $function
     * @param array $params
     * @param string $method
     *
     * @return mixed
     */
    private function webservice_call($function, $params, $verb = "POST") {
        global $CFG;

        if (!class_exists('curl')) {

            require_once($CFG->libdir . '/filelib.php');
        }

        $url = $this->serviceUrl .
            (preg_match('/\/$/', $this->serviceUrl) ? '' : '/') .
            'webservice/rest/server.php';

        $c = new curl(['proxy' => true]);

        $params['wstoken'] = $this->wstoken;
        $params['wsfunction'] = $function;                    

        switch ($verb) {
            case 'GET':
                $result = $c->get($url);
                break;
            case 'POST':
                $result = $c->post($url, $params);
                break;
            default:
                throw new Exception('Unsupported HTTP verb: ' . $verb);
        }

        // Get status code.
        $statuscode = $c->info['http_code'];

        if ($statuscode >= 200 && $statuscode <= 299) {
            if (isset($result)) {
                $contenttype = $c->info['content_type'];
                if ($contenttype == 'application/json; charset=utf-8') {
                    return json_decode($result);
                } else if($contenttype == 'application/xml; charset=utf-8') {
                    // Convert XML response to PHP Array
                    libxml_use_internal_errors(true);
                    $xmlObject = simplexml_load_string($result, "SimpleXMLElement", LIBXML_NOCDATA);

                    if ($xmlObject === false) {
                        // If failed to parse the XML
                        $errors = libxml_get_errors();
                        foreach ($errors as $error) {
                            echo "\n", $error->message;
                        }
                        throw new Exception('Failed to parse XML response.');
                    }

                    // Convert SimpleXML object to JSON string, then decode to associative array
                    $jsonString = json_encode($xmlObject);
                    $data = json_decode($jsonString, true);

                    // Now extract the fileurl
                    $fileurl = null;
                    if (isset($data['MULTIPLE']['SINGLE']['KEY'])) {
                        foreach ($data['MULTIPLE']['SINGLE']['KEY'] as $item) {
                            if ($item['@attributes']['name'] === 'fileurl') {
                                $fileurl = $item['VALUE'];
                                break;
                            }
                        }
                    }
                    return $fileurl;
                }else{
                    return $result;
                }
            } else {
                return;
            }
        }else{
            throw new Exception($result, $statuscode);
        }
    }

    /**
     * Download HTML Lite export .zip file from Mahara.
     *
     */
    private function download_zip() {
        mtrace('Copyleaks: plagiarism_copyleaks_maharaportfolio: Downloading portfolio zip file.');
        // Would be nice to use the zip streaming added in M3.11 (MDL-68533),
        // but for now we have to save to a temp file and then extract it.
        $url = $this->fileurl . '&wstoken=' . $this->wstoken;

        $fs = get_file_storage();

        $options = [
            'skipcertverify' => true,
            'timeout' => 600, // 10 minutes
            'connecttimeout' => 120 // 2 minutes
        ];
        $this->zipfile = $fs->create_file_from_url($this->fileinfo, $url, $options);
    }

    /**
     * Extract files from zip.
     *
     */
    private function fetch_mahara_file() {
        global $CFG;
        mtrace('Copyleaks: plagiarism_copyleaks_maharaportfolio: Extracting files from zip file.');
        require_once($CFG->dirroot . '/plagiarism/Copyleaks/lib.php');

        $success = true;
        $zipfile = $this->zipfile;
        $fileinfo = $this->fileinfo;
        $dirpath = $fileinfo['filepath'] . 's' . $this->submission->id . '/';
        if ($this->relateduserid) {
            $theuserid = $this->relateduserid;
        } else {
            $theuserid = $this->userid;
        }

        // Extract the files to storage and save them as Moodle files,
        // to make them available to the Ouriginal queue in a scheduled task.
        $packer = new \zip_packer();
        $zipcontents = $zipfile->extract_to_storage($packer, $this->context->id, $fileinfo['component'],
                $fileinfo['filearea'], $this->submission->submission, $dirpath, $theuserid);
        if (!$zipcontents) {
            mtrace('Copyleaks: plagiarism_copyleaks_maharaportfolio: Extracting zip failed - aborting');
            return false;
        }
        $fs = get_file_storage();
        foreach (array_keys($zipcontents) as $entry) {
            if ($pos = strpos($entry, '/')) {
                // Split path components from the entry into the path param.
                $thisdir = $dirpath . substr($entry, 0, $pos + 1);
                $thisfile = substr($entry, $pos +1 );
            } else {
                $thisdir = $dirpath;
                $thisfile = $entry;
            }

            // Load as a Moodle file object, suitable for adding to the queue.
            $file = $fs->get_file($this->context->id, $fileinfo['component'], $fileinfo['filearea'],
                    $this->submission->submission, $thisdir, $thisfile);
            if ($file->get_filename() != '.') {
                break;
            }
        }

        if ($success) {
            global $DB;            
            // Remove saved zip file from storage.
            mtrace('Copyleaks: plagiarism_copyleaks_maharaportfolio: Deleting downloaded portfolio zip file ' . $this->zipfile->get_filename());
            $this->zipfile->delete();

            return $file;
        }        

        return false;
    }

}