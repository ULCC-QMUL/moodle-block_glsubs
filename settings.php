<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 28/10/2016
 * Time: 15:03

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

*/

/**
 * glsubs block caps.
 *
 * @package    block_glsubs
 * @copyright  Daniel Neis <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
// do not forget !!!
// in order this to be activated you must create a function in the main class named has_config returning true

$settings->add(new admin_setting_heading('settings_header',
    get_string('settings_headerconfig', 'block_glsubs'),
    get_string('settings_descconfig', 'block_glsubs')));

$settings_autoselfsubscribe = 1 ;
$settings->add(new admin_setting_configcheckbox('block_glsubs/autoselfsubscribe',
    get_string('settings_autoselfsubscribe', 'block_glsubs'),
    get_string('settings_autoselfsubscribe_desc', 'block_glsubs'),
    $settings_autoselfsubscribe ));

$recent_messages_default_option = 'No messages shown' ; // no recent messages show
$recent_messages_options = array();
$recent_messages_options[0] = 'No messages shown';
$recent_messages_options[1] = '1' ;
$recent_messages_options[5] = '5' ;
$recent_messages_options[10] = '10' ;
$recent_messages_options[25] = '25' ;

$settings->add(new admin_setting_configselect('block_glsubs/messagestoshow',
    get_string('settings_messagestoshow', 'block_glsubs'),
    get_string('settings_messagestoshow_details', 'block_glsubs'),
    $recent_messages_default_option ,
    $recent_messages_options ) );

$default_pagelayout = 'course';
$page_layouts_options = array();
$page_layouts_options['course'] = get_string( $default_pagelayout );
$page_layouts_options['popup'] = get_string('popup');

$batch_name = get_string('settings_messagebatchsize','block_glsubs');
$batch_details = get_string('settings_messagebatchsize_details','block_glsubs');
$batch_default = 10000;
$settings->add(new admin_setting_configtext_int_only('block_glsubs/messagebatchsize',
    $batch_name ,
    $batch_details ,
    $batch_default,
    5 ));

$settings_messagenotification = '1';
$settings->add(new admin_setting_configcheckbox('block_glsubs/messagenotification',
    get_string('settings_messagenotification', 'block_glsubs'), get_string('settings_messagenotification_desc', 'block_glsubs'), $settings_messagenotification ));


