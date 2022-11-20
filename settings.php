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
 * settings.php - allows the admin to configure the plugin
 * @package   plagiarism_copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @copyright 2021 Copyleaks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(dirname(__FILE__)) . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/plagiarismlib.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/lib.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/forms/plagiarism_copyleaks_adminform.class.php');
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/plagiarism_copyleaks_logs.class.php');

require_login();

admin_externalpage_setup('plagiarismcopyleaks');

$context = context_system::instance();

require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");

$qpselectedtabid = optional_param('tab', "copyleaksconfiguration", PARAM_ALPHA);
$qpdate = optional_param('date', null, PARAM_ALPHANUMEXT);

$copyleakssetupform = new plagiarism_copyleaks_adminform();

if ($copyleakssetupform->is_cancelled()) {
    redirect(new moodle_url('/admin/category.php?category=plagiarism'));
}

$pagetabs = array();
$pagetabs[] = new tabobject(
    'copyleaksconfiguration',
    'settings.php',
    get_string('clpluginconfigurationtab', 'plagiarism_copyleaks'),
    get_string('clpluginconfigurationtab', 'plagiarism_copyleaks'),
    false
);

$pagetabs[] = new tabobject(
    'copyleakslogs',
    'settings.php?tab=copyleakslogs',
    get_string('cllogstab', 'plagiarism_copyleaks'),
    get_string('cllogstab', 'plagiarism_copyleaks'),
    false
);

switch ($qpselectedtabid) {
    case 'copyleakslogs':
        if (!is_null($qpdate)) {
            plagiarism_copyleaks_logs::displaylogs($qpdate);
        } else {
            echo $OUTPUT->header();
            $pagetabs[1]->selected = true;
            echo $OUTPUT->tabtree($pagetabs);
            echo $OUTPUT->heading(get_string('cllogsheading', 'plagiarism_copyleaks'));
            plagiarism_copyleaks_logs::displaylogs();
        }
        break;
    default:
        echo $OUTPUT->header();
        $pagetabs[0]->selected = true;
        echo $OUTPUT->tabtree($pagetabs);
        // Form data save flow.
        if (($data = $copyleakssetupform->get_data()) && confirm_sesskey()) {
            $copyleakssetupform->save($data);
            $output = $OUTPUT->notification(get_string('cladminconfigsavesuccess', 'plagiarism_copyleaks'), 'notifysuccess');
        }

        // Init form data.
        $copyleakssetupform->init_form_data();

        echo $copyleakssetupform->display();
        break;
}

echo $OUTPUT->footer();
