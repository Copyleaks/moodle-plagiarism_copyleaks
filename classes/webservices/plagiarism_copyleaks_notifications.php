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
 * Copyleaks notifications
 * @package   plagiarism_copyleaks
 * @author    Shade Amasha <shadea@copyleaks.com>
 * @copyright 2021 Copyleaks
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/plagiarism/copyleaks/classes/exceptions/plagiarism_copyleaks_webserviceexception.class.php');

class plagiarism_copyleaks_notifications extends external_api {

  /**
   * Returns description of method parameters
   * @return external_function_parameters
   */
  public static function send_notification_parameters() {
    return new external_function_parameters(
      array(
        'userid'    => new external_value(PARAM_INT, 'User id'),
        'subject' => new external_value(PARAM_TEXT, 'Notification subject'),
        'fullmessage' => new external_value(PARAM_TEXT, 'Notification message'),
        'fullmessageformat' => new external_value(PARAM_TEXT, 'Notification message format'),
        'fullmessagehtml' => new external_value(PARAM_RAW, 'Notification message html'),
        'smallmessage' => new external_value(PARAM_TEXT, 'Notification small message'),
        'contexturl' => new external_value(PARAM_URL, 'Notification message context URL'),
        'contexturlname' => new external_value(PARAM_TEXT, 'Notification message  context URL name'),
      )
    );
  }

  /**
   * Send notification to user
   * @param int $userid User ID
   * @param string $subject Notification subject
   * @param string $fullmessage Notification message
   * @param string $fullmessageformat Notification message format
   * @param string $fullmessagehtml Notification message html
   * @param string $smallmessage Notification small message
   * @param string $contexturl Notification message context URL
   * @param string $contexturlname Notification message context URL name
   * @return array
   */
  public static function send_notification($userid, $subject, $fullmessage, $fullmessageformat, $fullmessagehtml, $smallmessage, $contexturl, $contexturlname) {
    global $DB;

    // Validate parameters
    $params = self::validate_parameters(self::send_notification_parameters(), array(
      'userid' => $userid,
      'subject' => $subject,
      'fullmessage' => $fullmessage,
      'fullmessageformat' => $fullmessageformat,
      'fullmessagehtml' => $fullmessagehtml,
      'smallmessage' => $smallmessage,
      'contexturl' => $contexturl,
      'contexturlname' => $contexturlname,
    ));

    // Get the recipient user object
    $user = $DB->get_record('user', array('id' => $params['userid']), '*', MUST_EXIST);

    $message = new \core\message\message();
    $message->component = 'plagiarism_copyleaks';
    $message->name = 'copyleaks_notification';
    $message->userfrom = core_user::get_noreply_user();
    $message->userto = $user;
    $message->subject = $params['subject'];
    $message->fullmessage = $params['fullmessage'];
    $message->fullmessageformat = $params['fullmessageformat'];
    $message->fullmessagehtml = $params['fullmessagehtml'];
    $message->smallmessage = $params['smallmessage'];
    $message->notification = 1;
    $message->contexturl = $params['contexturl'];
    $message->contexturlname = $params['contexturlname'];

    $messageid = message_send($message);

    if ($messageid) {
      return null;
    }
    throw new plagiarism_copyleaks_webservice_exception('clsendnotificationfailed');
  }

  /**
   * Describes the return value for send_notification
   * @return external_single_structure
   */
  public static function send_notification_returns() {
    return null;
  }
}
