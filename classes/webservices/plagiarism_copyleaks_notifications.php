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
        'userto'    => new external_value(PARAM_INT, 'User id'),
        'userfrom' => new external_value(PARAM_INT, 'Notification message sender', VALUE_DEFAULT, null),
        'subject' => new external_value(PARAM_TEXT, 'Notification subject'),
        'fullmessage' => new external_value(PARAM_TEXT, 'Notification message'),
        'contexturl' => new external_value(PARAM_URL, 'Notification message context URL', VALUE_DEFAULT, null),
        'contexturlname' => new external_value(PARAM_TEXT, 'Notification message  context URL name', VALUE_DEFAULT, null),
      )
    );
  }

  /**
   * Send notification to user
   * @param int $userto reciver User ID
   * @param int $userfrom sender User ID
   * @param string $subject Notification subject
   * @param string $fullmessage Notification message
   * @param string $contexturl Notification message context URL
   * @param string $contexturlname Notification message context URL name
   * @return array
   */
  public static function send_notification($userto, $userfrom = null, $subject, $fullmessage, $contexturl = null, $contexturlname = null) {

    // Validate parameters
    $params = self::validate_parameters(self::send_notification_parameters(), array(
      'userto' => $userto,
      'subject' => $subject,
      'fullmessage' => $fullmessage,
      'userfrom' => $userfrom,
      'contexturl' => $contexturl,
      'contexturlname' => $contexturlname,
    ));

    if ($params['userfrom'] == null) {
      $params['userfrom'] = core_user::get_noreply_user();
    }

    $message = new \core\message\message();
    $message->component = 'plagiarism_copyleaks';
    $message->name = 'copyleaks_notification';
    $message->userfrom = $params['userfrom'];
    $message->userto = $params['userto'];
    $message->subject = $params['subject'];
    $message->fullmessage = $params['fullmessage'];
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml = text_to_html($params['fullmessage']);
    $message->notification = 1;
    if ($params['contexturl'] != null) {
      $message->contexturl = $params['contexturl'];
    }
    if ($params['contexturlname'] != null) {
      $message->contexturlname = $params['contexturlname'];
    }

    $messageid = message_send($message);

    if (!$messageid) {
      throw new plagiarism_copyleaks_webservice_exception('clsendnotificationfailed');
    }

    return null;
  }

  /**
   * Describes the return value for send_notification
   * @return external_single_structure
   */
  public static function send_notification_returns() {
    return null;
  }
}
