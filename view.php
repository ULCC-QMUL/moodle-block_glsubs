<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 28/10/2016
 * Time: 14:58
 *
 * File:     blocks/glsubs/view.php
 *
 * Purpose:  A glossary subscriptions message viewer
 *
 * Input:    required_param('id',PARAM_INT)
 *
 * Output:   A page showing the specified message ID to the associated user
 *
 * Notes:    This is developed for the QM+ Moodle site of the Queen Mary university of London
 *

 */

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

require('../../config.php');
require_once('../../lib/moodlelib.php');

require_login();
$error = false ;
// define event types
// each event may be split into more than one log entries as some activities have more aspects than one
define('EVENT_GENERIC','G'); // New Category or New Concept without category
define('EVENT_AUTHOR','A'); // Entry activity related to the Concept Author
define('EVENT_CATEGORY','C'); // Category Updated Or Deleted
define('EVENT_ENTRY','E'); // Any Concept activity
define('CATEGORY_GENERIC',get_string('CATEGORY_GENERIC','block_glsubs')); // Use this when the category of the event is generic

//get our config
//$def_config = get_config('block_glsubs');

$usercontext = context_user::instance($USER->id);
$PAGE->set_course($COURSE);
$PAGE->set_url('/blocks/glsubs/view.php');
$PAGE->set_heading($SITE->fullname);
$PAGE->set_pagelayout('course');
//$PAGE->set_pagelayout($def_config->pagelayout);
$PAGE->set_title(get_string('pluginname', 'block_glsubs'));
$PAGE->navbar->add(get_string('pluginname', 'block_glsubs'));


// start output to browser
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'block_glsubs'),5);

// Some content goes here
echo '<p/>Message for ';

$userpicture = $OUTPUT->user_picture($USER, array('size'=>65));
$elementUrl = new moodle_url('/user/view.php', array('id' => $USER->id));
$elementLink = html_writer::link($elementUrl,$userpicture);

// create a link to the user's list of entries in this glossary
echo  fullname($USER);
echo '<br>' .$elementLink;
echo '<p/>';

if( ! ( $msgID = required_param('id',PARAM_INT) ) > 0 ){
    $url = new moodle_url($_SERVER['HTTP_REFERER'], array());
    redirect($url);
}

// get the message ID
$key = (int) $msgID;

try {
    // $message = new \stdClass();
    $message = $DB->get_record( 'block_glsubs_messages_log',array('id' => $key ));
    // check if the message was delivered, and if not mark it with the date time stamp
    if( ! (int) $message->timedelivered > 0 ){
        $message->timedelivered = time() ;
        // $DB->update_record('block_glsubs_messages_log', $message , false); // let the message delivery system send it
    }
    // get the event
    $message->event = $DB->get_record('block_glsubs_event_subs_log', array('id' => (int) $message->eventlogid ));
    if($message->event){
        // if the event is valid , show it
        $message->date = gmdate("Y-m-d H:i:s", (int)$message->event->timecreated);
        $message->user = $DB->get_record('user', array('id' => (int)$message->event->userid));
        $message->author = $DB->get_record('user', array('id' => (int)$message->event->authorid));
    }
} catch (\Exception $exception){
    echo 'There was an error while attempting to read from the glossary event log';
    $error = true ;
}
// check if the logged in user is the user this message is intended to be seen by, otherwise set an error condition
if( (int) $message->userid !== (int) $USER->id ){
    $error = true;
}
// if there is no error then show the message
if(! $error){
    if($message->event){
        $messageHtml = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $message->event->eventtext);
        echo '<p/>' . $messageHtml;
    }

    // show message view date time
    echo '<p/><p/><p/><p/>'.get_string( 'view_message_at' , 'block_glsubs' ). ' '. date( 'd-M-Y H:i:s' , $message->timedelivered ) ;
}
echo '<p><p><p><strong><a href="'.$_SERVER['HTTP_REFERER'] . '">Go back</a></strong>' ;

//send footer out to browser
echo $OUTPUT->footer();
return;
