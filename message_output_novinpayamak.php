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
 * novinpayamak message processor to send messages by novinpayamak
 *
 * @package    message_novinpayamak
 * @copyright  2020 Mohammad Damavandi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
require_once($CFG->dirroot . '/message/output/lib.php');

class message_output_novinpayamak extends message_output {

  /**
   * Processes the message and sends a notification via novinpayamak
   *
   * @param stdClass $eventdata the event data submitted by the message sender plus $eventdata->savedmessageid
   * @return true if ok, false if error
   */
  function send_message($eventdata) {
    global $CFG;

    // Skip any messaging of suspended and deleted users.
    if ($eventdata->userto->auth === 'nologin' or $eventdata->userto->suspended or $eventdata->userto->deleted) {
      return true;
    }

    if (!empty($CFG->nosmsever)) {
      // hidden setting for development sites, set in config.php if needed
      debugging('$CFG->nosmsever is active, no novinpayamak message sent.', DEBUG_MINIMAL);
      return true;
    }

    if (PHPUNIT_TEST) {
      // No connection to external servers allowed in phpunit tests.
      return true;
    }

    //hold onto novinpayamak id preference because /admin/cron.php sends a lot of messages at once
    static $numbers = array();

    if (!array_key_exists($eventdata->userto->id, $numbers)) {
      $phone2 = $eventdata->userto->phone2;
      // validate $phone2
      $phone2 = $this->mobileValidation($phone2);
      $numbers[$eventdata->userto->id] = $phone2;
    }

    $number = $numbers[$eventdata->userto->id];

    //calling s() on smallmessage causes novinpayamak to display things like &lt; novinpayamak != a browser
    $message = !empty($eventdata->smallmessage) ? $eventdata->smallmessage : $eventdata->fullmessage;
    $message = strip_tags($message);

    try {
		$parameters['userName'] = $CFG->novinpayamakusername;
		$parameters['password'] = $CFG->novinpayamakpassword;
		$parameters['fromNumber'] = $CFG->novinpayamaknumber;
	  
		if(strlen($number) == 11)
			$number = substr($number,1);
		if(strlen($number) == 12)
			$number =  substr($number,2);
		if(strlen($number) == 13)
			$number = substr($number,3);
			
		$number = '0' .$number;
			
		$domain = str_replace( "www.", "", $_SERVER['HTTP_HOST'] );
	  
		$parameters['toNumbers'] = array($number);
		$message = substr($message, 0 , 260);
		$parameters['messageContent'] = urldecode(str_replace("\n", PHP_EOL, $message ."\n".$parameters['userName']));

        $Recipients = implode(",", $parameters['toNumbers']);
        $smsUrl = "https://api.sabanovin.com/v1/{$parameters['password']}/sms/send.json?gateway={$parameters['fromNumber']}&to={$Recipients}&text={$parameters['messageContent']}&";
        $response = file_get_contents($smsUrl);
        $responseJson = json_decode($response);
        debugging($responseJson);

    } catch (Exception $e) {
      debugging($e->getMessage());
      return false;
    }
    return true;
  }

  //define "98" to first of the numbet
  function mobileValidation($number) {
    $number = (int) $number;
    if(strlen($number) == 11)
			$number = substr($number,1);
		if(strlen($number) == 12)
			$number =  substr($number,2);
		if(strlen($number) == 13)
			$number = substr($number,3);
    return $number;
  }

  /**
   * Creates necessary fields in the messaging config form.
   *
   * @param array $preferences An array of user preferences
   */
  function config_form($preferences) {
    global $CFG, $USER;

    if (!$this->is_system_configured()) {
      return get_string('notconfigured', 'message_novinpayamak');
    } else {
      return get_string('novinpayamakmobilenumber', 'message_novinpayamak') . ': ' . $USER->phone2;
    }
  }

  /**
   * Parses the submitted form data and saves it into preferences array.
   *
   * @param stdClass $form preferences form class
   * @param array $preferences preferences array
   */
  function process_form($form, &$preferences) {
    
  }

  /**
   * Loads the config data from database to put on the form during initial form display
   *
   * @param array $preferences preferences array
   * @param int $userid the user id
   */
  function load_data(&$preferences, $userid) {
    
  }

  /**
   * Tests whether the novinpayamak settings have been configured
   * @return boolean true if novinpayamak is configured
   */
  function is_system_configured() {
    global $CFG;
    return (!empty($CFG->novinpayamaknumber) && !empty($CFG->novinpayamakusername) && !empty($CFG->novinpayamakpassword));
  }

  /**
   * Tests whether the novinpayamak settings have been configured on user level
   * @param  object $user the user object, defaults to $USER.
   * @return bool has the user made all the necessary settings
   * in their profile to allow this plugin to be used.
   */
  function is_user_configured($user = null) {
    global $USER;

    if (is_null($user)) {
      $user = $USER;
    }
    return (bool) $user->phone2;
  }

}

