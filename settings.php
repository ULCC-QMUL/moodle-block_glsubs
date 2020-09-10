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
 * Created by PhpStorm.
 * User: vasileios
 * Date: 28/10/2016
 * Time: 15:03
 *
 * File:     blocks/glsubs/settings.php
 *
 * Purpose:  Define the plugin settings to be used in the administration pages
 *          and to set their default values
 *
 * Input:    N/A
 *
 * Output:   N/A
 *
 * Notes:   By default the latest user messages are not shown,
 *          there is a cap of messages to send per run ,
 *          to avoid overloading of the cron/scheduler subsystem
 *          of the Moodle installation
 *
 * glsubs block caps.
 *
 * @package    block_glsubs
 * @copyright  Daniel Neis <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
// Do not forget !!!.
// In order this to be activated you must create a function in the main class named has_config returning true.

// Add settings header.
$settings->add(new admin_setting_heading('settings_header',
                                         get_string('settings_headerconfig', 'block_glsubs'),
                                         get_string('settings_descconfig', 'block_glsubs')));

// Add auto self subscription option.
$settingsautoselfsubscribe = 1;
$settings->add(new admin_setting_configcheckbox('block_glsubs/autoselfsubscribe',
                                                get_string('settings_autoselfsubscribe', 'block_glsubs'),
                                                get_string('settings_autoselfsubscribe_desc', 'block_glsubs'),
                                                $settingsautoselfsubscribe));

// Add option to show how many recent or iunread messages.
$recentmessagesdefaultoption = get_string('settings_no_messages', 'block_glsubs'); // No recent messages show.
$recentmessagesoptions = array();
$recentmessagesoptions[0] = $recentmessagesdefaultoption;
$recentmessagesoptions[1] = '1';
$recentmessagesoptions[5] = '5';
$recentmessagesoptions[10] = '10';
$recentmessagesoptions[25] = '25';

$settings->add(new admin_setting_configselect('block_glsubs/messagestoshow',
                                              get_string('settings_messagestoshow', 'block_glsubs'),
                                              get_string('settings_messagestoshow_details', 'block_glsubs'),
                                              $recentmessagesdefaultoption,
                                              $recentmessagesoptions));


// Add option to enable message notifications.
$settingsmessagenotification = 1;
$settings->add(new admin_setting_configcheckbox('block_glsubs/messagenotifocation',
                                                get_string('settings_messagenotification', 'block_glsubs'),
                                                get_string('settings_messagenotification_desc', 'block_glsubs'),
                                                $settingsmessagenotification));

// Add option to set the default batch size of message deliveries.
$deliverymessagesdefaultoption = '1000';
$deliverymessagesoptions = array();
$deliverymessagesoptions[0] = $deliverymessagesdefaultoption;
$deliverymessagesoptions[100] = '100';
$deliverymessagesoptions[500] = '500';
$deliverymessagesoptions[1000] = '1000';
$deliverymessagesoptions[2000] = '2000';
$deliverymessagesoptions[5000] = '5000';

$settings->add(new admin_setting_configselect('block_glsubs/messagebatchsize',
                                              get_string('settings_messagebatchsize_details', 'block_glsubs'),
                                              get_string('settings_messagebatchsize_desc', 'block_glsubs'),
                                              $deliverymessagesdefaultoption,
                                              $deliverymessagesoptions));
