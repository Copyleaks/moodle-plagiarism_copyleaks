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
 * This file containes the translations for English
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname']  = 'Copyleaks plagiarism plugin';
$string['copyleaks'] = 'Copyleaks';
$string['clstudentdisclosure'] = 'Student disclosure';
$string['clstudentdisclosure_help'] = 'This text will be displayed to all students on the file upload page.';
$string['clstudentdisclosuredefault']  = '<span>By submitting your files you are agreeing to the plagiarism detection service </span><a target="_blank" href="https://copyleaks.com/legal/privacypolicy">privacy policy</a>';
$string['clstudentdagreedtoeula']  = '<span>You have already agreed to the plagiarism detection service </span><a target="_blank" href="https://copyleaks.com/legal/privacypolicy">privacy policy</a>';
$string['cladminconfigsavesuccess'] = 'Copyleaks plagiarism settings was saved successfully.';
$string['clpluginconfigurationtab'] = 'Configurations';
$string['cllogstab'] = 'Logs';
$string['cladminconfig'] = 'Copyleaks plagiarism plugin configuration';
$string['clpluginintro'] = 'The Copyleaks plagiarism checker is a comprehensive and accurate solution that helps teachers and students check if their content is original.<br>For more information on how to setup and use the plugin please check <a target="_blank" href="https://lti.copyleaks.com/guides/select-moodle-integration">our guides</a>.</br></br></br>';
$string['clenable'] = 'Enable Copyleaks';
$string['clenablemodulefor'] = 'Enable Copyleaks for {$a}';
$string['claccountconfig'] = "Copyleaks account configuration";
$string['clapiurl'] = 'Copyleaks API-URL';
$string['claccountkey'] = "Copyleaks key";
$string['claccountsecret'] = "Copyleaks secret";
$string['cldefaultsettings'] = 'Copyleaks plagiarism plugin default settings';
$string['clignorereferences'] = 'Ignore references';
$string['clignorequotes'] = 'Ignore quotes';
$string['clignoretitles'] = 'Ignore titles';
$string['clignoreretableofcontents'] = 'Ignore table of contents';
$string['clignoreresourcecodecomments'] = 'Ignore source code comments';
$string['clscaninternet'] = 'Scan internet';
$string['clscaninternaldatabase'] = 'Scan internal database';
$string['clenablesafesearch'] = 'Enable safe search';
$string['clenablecheatdetection'] = 'Enable cheat detection';
$string['clcheckforparaphrase'] = 'Check for paraphrased text';
$string['cldisablestudentinternalaccess'] = 'Disable view of database for students';
$string['cldisablestudentinternalaccess_help'] = 'Students will not be able to view ANY results from the internal database and private repositories';
$string['clshowstudentresultsinfo'] = "Show student information in results";
$string['clshowstudentresultsinfo_help'] = "Instructors will be able to see more details about students in the results card";
$string['clallowstudentaccess'] = 'Allow students access to plagiarism reports';
$string['clinvalidkeyorsecret'] = 'Invalid key or secret';
$string['clfailtoloaddata'] = 'Fail to load Copyleaks data';
$string['clfailtosavedata'] = 'Fail to save Copyleaks data';
$string['clplagiarised'] = 'Similarity score';
$string['clopenreport'] = 'Click to open Copyleaks report';
$string['clscoursesettings'] = 'Copyleaks settings';
$string['clupdateerror'] = 'Error while trying to update records in database';
$string['clinserterror'] = 'Error while trying to insert records to database';
$string['clsendqueuedsubmissions'] = "Copyleaks plagiarism plugin - handle queued files";
$string['clsendresubmissionsfiles'] = "Copyleaks plagiarism plugin - handle resubmitted results";
$string['clupdatereportscores'] = "Copyleaks plagiarism plugin - handle plagiairsm check similarity score update";
$string['cldraftsubmit'] = "Submit files only when students click the submit button";
$string['cldraftsubmit_help'] = "This option is only available if 'Require students to click the submit button' is Yes";
$string['clreportgenspeed'] = 'When to generate report?';
$string['clgenereportimmediately'] = 'Generate reports immediately';
$string['clgenereportonduedate'] = 'Generate reports on due date';
$string['cltaskfailedconnecting'] = 'connection to Copyleaks can not be established, error: {$a}';
$string['clapisubmissionerror'] = 'Copyleaks has returned an error while trying to send file for submission - ';
$string['clcheatingdetected'] = 'Cheating detected, Open report to learn more';
$string['clcheatingdetectedtxt'] = 'Cheating detected';
$string['clreportpagetitle'] = 'Copyleaks report';
$string['clrepositoriespagetitle'] = 'Copyleaks repositories settings';
$string['cldefaultrepositoriespagetitle'] = 'Copyleaks default repositories settings';
$string['cleditrepositories'] = 'Edit repositories settings';
$string['clopenfullscreen'] = 'Open in full screen';
$string['cllogsheading'] = 'Logs';
$string['clpoweredbycopyleaks'] = 'Powered by Copyleaks';
$string['clplagiarisefailed'] = 'Failed';
$string['clplagiarisescanning'] = 'Scanning for plagiarism...';
$string['clplagiarisequeued'] = 'Scheduled for plagiarism scan at {$a}';
$string['cldisabledformodule'] = 'Copyleaks plugin is disabled for this module.';
$string['clnopageaccess'] = 'You dont have access to this page.';

$string['privacy:metadata:core_files'] = 'Copyleaks stores files that have been uploaded to Moodle to form a Copyleaks submission.';

$string['privacy:metadata:plagiarism_copyleaks_files'] = 'Information that links a Moodle submission to a Copyleaks submission.';
$string['privacy:metadata:plagiarism_copyleaks_files:userid'] = 'The ID of the user who is the owner of the submission.';
$string['privacy:metadata:plagiarism_copyleaks_files:submitter'] = 'The ID of the user who has made the submission.';
$string['privacy:metadata:plagiarism_copyleaks_files:similarityscore'] = 'The similarity score of the submission.';
$string['privacy:metadata:plagiarism_copyleaks_files:lastmodified'] = 'A timestamp indicating when the user last modified their submission.';

$string['privacy:metadata:plagiarism_copyleaks_client'] = 'In order to integrate with a Copyleaks, some user data needs to be exchanged with Copyleaks.';
$string['privacy:metadata:plagiarism_copyleaks_client:module_id'] = 'The module id is sent to Copyleaks for identification purposes.';
$string['privacy:metadata:plagiarism_copyleaks_client:module_name'] = 'The module name is sent to Copyleaks for identification purposes.';
$string['privacy:metadata:plagiarism_copyleaks_client:module_type'] = 'The module type is sent to Copyleaks for identification purposes.';
$string['privacy:metadata:plagiarism_copyleaks_client:module_creationtime'] = 'The module creation time is sent to Copyleaks for identification purposes.';
$string['privacy:metadata:plagiarism_copyleaks_client:submittion_userId'] = 'The submission userId is sent to Copyleaks for identification purposes.';
$string['privacy:metadata:plagiarism_copyleaks_client:submittion_name'] = 'The submission name is sent to Copyleaks for identification purposes.';
$string['privacy:metadata:plagiarism_copyleaks_client:submittion_type'] = 'The submission type is sent to Copyleaks for identification purposes.';
$string['privacy:metadata:plagiarism_copyleaks_client:submittion_content'] = 'The submission content is sent to Copyleaks for scan processing.';
