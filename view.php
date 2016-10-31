<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 28/10/2016
 * Time: 14:58
 */

require('../../config.php');
require_once('../../lib/moodlelib.php');

require_login();

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
// echo $msgID . '<br/>';
$key = (int) $msgID;
try {
    // $message = new \stdClass();
    $message = $DB->get_record( 'block_glsubs_messages_log',array('id' => $key ));
    // check if the message was delivered, and if not mark it with the date time stamp
    if( ! (int) $message->timedelivered > 0 ){
        $message->timedelivered = time() ;
        $DB->update_record('block_glsubs_messages_log', $message );
    }
    $message->event = $DB->get_record('block_glsubs_event_subs_log', array('id' => $key));
    $message->date = gmdate("Y-m-d H:i:s", (int)$message->event->timecreated);
    $message->user = $DB->get_record('user', array('id' => (int)$message->event->userid));
    $message->author = $DB->get_record('user', array('id' => (int)$message->event->authorid));
} catch (\Exception $exception){
    echo 'There was an error while attempting to read from the glossary event log';
    $error = true ;
}

if(! $error){
    echo '<p/><p/><p/>';
    $userpicture = $OUTPUT->user_picture($message->user, array('size'=>35));
    $elementUrl = new moodle_url('/user/view.php', array('id' => $message->user->id));
    $elementLink = html_writer::link($elementUrl,$userpicture);

    // create a link to the user's list of entries in this glossary
    echo get_string('view_the_user','block_glsubs') . fullname($message->user) . ' ' .$elementLink;
    if( $message->event->crud === 'c' ){
        echo get_string('view_created','block_glsubs');
    } elseif ( $message->event->crud === 'u' ){
        echo get_string('view_updated','block_glsubs');
    } elseif ( $message->event->crud === 'd' ){
        echo get_string('view_deleted','block_glsubs');
    }

    if( $message->event->eventtype === EVENT_GENERIC ){
        echo get_string('view_generic','block_glsubs');
    } elseif ( $message->event->eventtype ===  EVENT_CATEGORY ){
        echo get_string('view_category','block_glsubs');
    } elseif ( $message->event->eventtype ===  EVENT_ENTRY ){
        echo get_string('view_concept','block_glsubs');
    }

    echo get_string('view_on','block_glsubs');
    echo ' ';
    $userpicture = $OUTPUT->user_picture($message->author, array('size'=>35));
    $elementUrl = new moodle_url('/user/view.php', array('id' => $message->author->id));
    $elementLink = html_writer::link($elementUrl,$userpicture);
    echo ' '. fullname($message->user) . ' ' .$elementLink;

    echo '<p/>' .$message->event->eventtext;

    echo '<p/><p/><p/><p/>'get_string('view_message_at','block_glsubs'). $message->timedelivered ;
}
echo '<p><p><p><a href="'.$_SERVER['HTTP_REFERER'] . '">Go back</a>' ;

//send footer out to browser
echo $OUTPUT->footer();
return;
