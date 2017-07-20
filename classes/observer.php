<?php
/****************************************************************
 *
 * File:  blocks/glsubs/classes/observer.php
 *
 * Purpose:  A class handling the subscription events for all the
 * glossaries and their subscriptions, storing the event and its
 * associated data in a database table log
 *
 * Input:    N/A
 *
 *
 *
 * Output:   N/A
 *
 *
 *
 * Notes:   The block should be installed, added to the course
 *          and configured to be available for all course pages
 *          It does not matter if the moodle event subscriptions
 *          are configured to work for the general events
 *
 ****************************************************************/

/**
 * Created by PhpStorm.
 * User: vasileios
 * Queen Mary University of London
 * Date: 14/10/2016
 * Time: 14:42

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

// define event types
// each event may be split into more than one log entries as some activities have more aspects than one
define('EVENT_GENERIC','G'); // New Category or New Concept without category
define('EVENT_AUTHOR','A'); // Entry activity related to the Concept Author
define('EVENT_CATEGORY','C'); // Category Updated Or Deleted
define('EVENT_ENTRY','E'); // Any Concept activity
define('CATEGORY_GENERIC',get_string('CATEGORY_GENERIC','block_glsubs')); // Use this when the category of the event is generic

class block_glsubs_observer
{
    /****************************************************************
     *
     * Method:       static observe_all
     *
     * Purpose:      the entry point of all glossary subscription events
     *              as it is defined in the blocks/glsubs/db/events.php
     *              for every event type. The reason is to create a single
     *              entry point to classify and reduce duplicate code
     *
     *
     * Parameters:   a core event object
     *
     * Returns:      true on succesful execution
     ****************************************************************/
    /**
     * @param \core\event\base $event
     *
     * @return bool|null
     */
    static public function observe_all (\core\event\base $event){

        switch ($event->eventname) {
            case '\mod_glossary\event\category_created':
                block_glsubs_observer::category_event( $event );
                break;
            case '\mod_glossary\event\category_updated':
                block_glsubs_observer::category_event( $event );
                break;
            case '\mod_glossary\event\category_deleted':
                block_glsubs_observer::category_event( $event );
                break;
            case '\mod_glossary\event\entry_created':
                block_glsubs_observer::entry_event( $event );
                break;
            case '\mod_glossary\event\entry_updated':
                block_glsubs_observer::entry_event( $event );
                break;
            case '\mod_glossary\event\entry_deleted':
                block_glsubs_observer::entry_event( $event );
                break;
            case '\mod_glossary\event\entry_approved':
                block_glsubs_observer::entry_event( $event );
                break;
            case '\mod_glossary\event\entry_disapproved':
                block_glsubs_observer::entry_event( $event );
                break;
            case '\mod_glossary\event\comment_created':
                block_glsubs_observer::comment_event( $event );
                break;
            case '\mod_glossary\event\comment_deleted':
                block_glsubs_observer::comment_event( $event );
                break;
            default:
                return NULL;
        }
        return TRUE;
        // save the event in the glossary events log
    }

    /****************************************************************
     *
     * Method:       static entry_event
     *
     * Purpose:      the entry point of glossary concept events
     *
     * Parameters:   a core event object
     *
     * Returns:      the new log entry id or false/null in case of error
     ****************************************************************/
    /**
     * @param \core\event\base $event
     *
     * @return bool|int|null
     */
    private static function entry_event(\core\event\base $event ){
        global $DB;

        //get the autosubscription setting
        $auto_subscribe = ( '1' === get_config('block_glsubs','autoselfsubscribe') );

        // get the event data array
        $eventdata = $event->get_data();

        // define the default return value in case of error
        $logid = null ;

        // get glossary concept id
        $glossary_concept_id = (int) $eventdata['objectid'];

        try {
            // get glossary concept
            if( 'created' === $eventdata['action'] ){
                $glossary_concept = $DB->get_record($eventdata['objecttable'] , array( 'id' => $glossary_concept_id ) );
            } else {
                $glossary_concept = $event->get_record_snapshot($eventdata['objecttable'] , $glossary_concept_id );
            }

            // get concept category IDs
            $concept_categories = $DB->get_records('glossary_entries_categories',array('entryid' => $glossary_concept_id ));

            // store categories as names
            $categories = '';

            // store categories as array of objects
            if( count( $concept_categories ) > 0 ){
                foreach ($concept_categories as $key => & $my_concept_category ) {
                    $category =  $DB->get_record('glossary_categories', array( 'id' => (int) $my_concept_category->categoryid ));
                    $my_concept_category->name = $category->name ;
                    $categories .= '['. $category->name .'] ';
                }
            } else { // add a generic category for the event
                $concept_categories[0] = new \stdClass();
                $concept_categories[0]->id = 0 ;
                $concept_categories[0]->categoryid = null ;
                $concept_categories[0]->entryid = $glossary_concept_id ;
                $concept_categories[0]->name = CATEGORY_GENERIC ;
                $categories = $concept_categories[0]->name ;
            }

            // get author of the concept where the comment is created
            $authorid = (int) $glossary_concept->userid ;

            // get the glossary id
            $glossaryid = (int) $glossary_concept->glossaryid ;

            // get the user
            $user = $DB->get_record('user', array('id' => (int)$eventdata['userid']));

            // get the course
            $course = $DB->get_record('course', array('id' => (int)$eventdata['courseid']));

            // get course module
            $course_module = $DB->get_record('course_modules', array('id' => (int)$eventdata['contextinstanceid']));

            // get module
            $module = $DB->get_record('modules',array( 'id' => (int) $course_module->module ));

            // get the module database table name
            $module_table = $module->name ;

            // get the module  table entry
            $course_module_entry = $DB->get_record($module_table, array( 'id' => (int) $course_module->instance ) );

            // get the module name
            $module_name = $course_module_entry->name ;

            // get the event url
            // $event_url = $event->get_url();

            if( 'created' === $eventdata['action'] || 'updated' === $eventdata['action'] ){
                // add a subscription for this concept comment for this user/creator
                $userid = (int) $user->id ;
                $glossaryid = (int) $glossaryid ;
                $glossary_concept_id = (int) $glossary_concept_id ;
                $filters = array( 'userid' => $userid , 'glossaryid' => $glossaryid , 'conceptid' => $glossary_concept_id ) ;

                // check if the user is registered into the glossary subscriptions main table
                block_glsubs_observer::check_user_subscription( $auto_subscribe , $userid , $glossaryid );

                // save the subscription to this glossary concept comments for this user/creator
                // check if the subscription to the concept/comments already exists first
                if( $DB->record_exists( 'block_glsubs_concept_subs' , $filters ) ) {
                    $concept_subscription = $DB->get_record( 'block_glsubs_concept_subs' , $filters );
                    $concept_subscription->conceptactive = 1 ;  // mark active the concept subscription
                    $concept_subscription->commentsactive = 1 ; // mark active the concept comments subscription

                    // check if an automatic subscription should be created
                    if($auto_subscribe){
                        $DB->update_record('block_glsubs_concept_subs',$concept_subscription , false );
                    }
                } else {
                    $concept_subscription = new \stdClass();
                    $concept_subscription->userid = (int) $user->id ;
                    $concept_subscription->glossaryid = $glossaryid ;
                    $concept_subscription->conceptid = $glossary_concept_id ;
                    $concept_subscription->conceptactive = 1 ;
                    $concept_subscription->commentsactive = 1 ;
                    if($auto_subscribe){
                        $logid = $DB->insert_record( 'block_glsubs_concept_subs' , $concept_subscription , true );
                    }
                }
            }
            // save the log entry for this glossary event

            // make the text request object
            $text_request = array();
            $text_request['event_type'] = 'entry';
            $text_request['event'] = $event ;
            $text_request['event_item'] = $glossary_concept;
            $text_request['event_course'] = $course;
            $text_request['event_module'] = $module_name;
            $text_request['event_comment'] = null ;
            $text_request['event_categories'] = $categories;
            $text_request['event_author'] = $authorid ;
            // get the event text
            $event_text  = block_glsubs_observer::get_event_text( $text_request );

            // clean record
            $clean_record = new \stdClass();
            // create log entries for each concept category or one generic
            foreach ( $concept_categories as $key => $myconcept_category ) {
                // build an event record to add to the subscriptions log for each category or the generic category
                $record = clone $clean_record;
                $record->userid = (int)$eventdata['userid']; // get the user id for the event
                $record->glossaryid = $glossaryid; // get the glossary id
                $record->categoryid =  ( (int) $myconcept_category->categoryid === 0 ) ? null : (int) $myconcept_category->categoryid  ; // get each of the category id from the event
                $record->conceptid = $glossary_concept_id; // the concept id of the comment created
                $record->authorid = $authorid; // get the user id of the concept
                $record->processed = 0; // mark it to be processed
                $record->useremail = $user->email; // get user's email at the time of the event
                $record->eventlink = html_writer::link($event->get_url(), 'LINK'); // create a link to the event
                $record->eventtext = $event_text;
                $record->eventtype = ( $myconcept_category->name === CATEGORY_GENERIC ) ? EVENT_GENERIC : EVENT_ENTRY ; // Concept Entry comment related event
                $record->timecreated = $eventdata['timecreated'];
                $record->timeprocessed = null;
                $record->contextinstanceid = (int) $eventdata['contextinstanceid'] ;
                $record->crud = $eventdata['crud'];
                $record->edulevel = (int) $eventdata['edulevel'];

                // store the event record for this category
                $logid = $DB->insert_record('block_glsubs_event_subs_log', $record, true);
            }
        } catch (\Exception $e) {
            // there was an error creating the event entry for this category in this glossary
            return false;
        }

        // return the log id
        return $logid;
    }

    /****************************************************************
     *
     * Method:       static comment_event
     *
     * Purpose:      the entry point of glossary comment events
     *
     * Parameters:   a core event object
     *
     * Returns:      the new log entry id or false/null in case of error
     ****************************************************************/
    /**
     * @param \core\event\base $event
     *
     * @return bool|int
     */
    private static function comment_event(\core\event\base $event){
        global $DB ;

        //get the autosubscription setting
        $auto_subscribe = ( '1' === get_config('block_glsubs','autoselfsubscribe') );

        // get the event data array
        $eventdata = $event->get_data();

        // define the default return value in case of error
        $logid = null ;

        //get the category id
        $commentid = (int) $eventdata['objectid'];

        try {
            // get the comment record
            if('created' === $eventdata['action']){
                $comment_record = $DB->get_record( $eventdata['objecttable'] , array('id' => $commentid ) );
            } else {
                $comment_record = $event->get_record_snapshot( $eventdata['objecttable'] ,  $commentid );
            }

           //get comment content
            $comment_content = $comment_record->content ;

            // get glossary concept id
            if( 'created' === $eventdata['action']){
                $glossary_concept_id = (int) $comment_record->itemid ;
            } else {
                $glossary_concept_id = (int) $eventdata['other']['itemid'];
            }

            // get glossary concept
            $glossary_concept = $DB->get_record('glossary_entries', array('id' => $glossary_concept_id));

            // get concept category IDs
            $concept_categories = $DB->get_records('glossary_entries_categories',array('entryid' => $glossary_concept_id ));

            // store categories as names
            $categories = '';

            // store categories as array of objects
            if( count( $concept_categories ) > 0 ){
                foreach ( $concept_categories as $key => & $concept_category ) {
                    $category =  $DB->get_record('glossary_categories', array( 'id' => (int) $concept_category->categoryid ));
                    $concept_category->name = $category->name ;
                    $categories .= '['. $category->name .'] ';
                }
            } else { // add a generic category for the event
                $concept_categories[0] = new \stdClass();
                $concept_categories[0]->id = 0 ;
                $concept_categories[0]->categoryid = null ;
                $concept_categories[0]->entryid = $glossary_concept_id ;
                $concept_categories[0]->name = CATEGORY_GENERIC ;
                $categories = $concept_categories[0]->name ;
            }

            // get author of the concept where the comment is created
            $authorid = (int) $glossary_concept->userid ;

            // get the glossary id
            $glossaryid = (int) $glossary_concept->glossaryid ;

            // get the user
            $user = $DB->get_record('user', array('id' => (int)$eventdata['userid']));

            // get the course
            $course = $DB->get_record('course', array('id' => (int)$eventdata['courseid']));

            // get course module
            $course_module = $DB->get_record('course_modules', array('id' => (int)$eventdata['contextinstanceid']));

            // get module
            $module = $DB->get_record('modules',array( 'id' => (int) $course_module->module ));

            // get the module database table name
            $module_table = $module->name ;

            // get the module  table entry
            $course_module_entry = $DB->get_record($module_table, array( 'id' => (int) $course_module->instance ) );

            // get the module name
            $module_name = $course_module_entry->name ;

            // make the text request object
            $text_request = array();
            $text_request['event_type'] = 'comment';
            $text_request['event'] = $event ;
            $text_request['event_item'] = $glossary_concept;
            $text_request['event_course'] = $course;
            $text_request['event_module'] = $module_name;
            $text_request['event_comment'] = $comment_content ;
            $text_request['event_categories'] = $categories;
            $text_request['event_author'] = $authorid ;
            // get the event text
            $event_text  = block_glsubs_observer::get_event_text( $text_request );

            // add a subscription for this concept comment for this user/creator
            $userid = (int) $user->id ;
            $glossaryid = (int) $glossaryid ;
            $glossary_concept_id = (int) $glossary_concept_id ;
            $filters = array( 'userid' => $userid , 'glossaryid' => $glossaryid , 'conceptid' => $glossary_concept_id ) ;

            // check if the user is registered into the glossary subscriptions main table
            block_glsubs_observer::check_user_subscription( $auto_subscribe , $userid , $glossaryid );

            // save the subscription to this glossary concept comments for this user/creator
            // check if the subscription to the concept/comments already exists first
            if( $DB->record_exists( 'block_glsubs_concept_subs' , $filters ) ) {
                // activate the subscriptions for this user on this concept and its comments
                // after the events recorder and messages are sent over to the users
                $concept_subscription = $DB->get_record('block_glsubs_concept_subs', $filters);
                $concept_subscription->conceptactive = 1;
                $concept_subscription->commentsactive = 1;
                if($auto_subscribe){
                    $DB->update_record( 'block_glsubs_concept_subs' , $concept_subscription, false );
                }
            } else {
                // create a subscription as this activity should be reported to them by a message
                $concept_subscription = new \stdClass();
                $concept_subscription->userid = (int) $user->id ;
                $concept_subscription->glossaryid = $glossaryid ;
                $concept_subscription->conceptid = $glossary_concept_id ;
                $concept_subscription->conceptactive = 1 ;
                $concept_subscription->commentsactive = 1 ;
                if($auto_subscribe){
                    $logid = $DB->insert_record( 'block_glsubs_concept_subs' , $concept_subscription , true );
                }
            }

            $clean_record = new \stdClass();
            // save the log entries for this glossary event, one for each category or a generic one
            foreach ($concept_categories as $key => $concept_category_a) {
                // build an event record to add to the subscriptions log
                $record = clone $clean_record;
                $record->userid = (int)$eventdata['userid']; // get the user id for the event
                $record->glossaryid = $glossaryid; // get the glossary id
                $record->categoryid = ( (int) $concept_category_a->categoryid === 0 ) ? null : (int) $concept_category_a->categoryid ; // get the category id
                $record->conceptid = $glossary_concept_id; // the concept id of the comment created
                $record->authorid = $authorid; // get the user id of the concept
                $record->processed = 0; // mark it to be processed
                $record->useremail = $user->email; // get user's email at the time of the event
                $record->eventlink = html_writer::link($event->get_url(), 'LINK'); // create a link to the event
                $record->eventtext = $event_text;
                $record->eventtype = EVENT_ENTRY; // Concept Entry comment related event
                $record->timecreated = $eventdata['timecreated'];
                $record->timeprocessed = null;
                $record->contextinstanceid = (int) $eventdata['contextinstanceid'] ;
                $record->crud = $eventdata['crud'];
                $record->edulevel = (int) $eventdata['edulevel'];

                // store the event record for this category
                $logid = $DB->insert_record('block_glsubs_event_subs_log', $record, true);
            }

        } catch (\Exception $e) {
            // there was an error creating the event entry for this category in this glossary
            return false;
        }

        // return the log id
        return $logid;
    }

    /****************************************************************
     *
     * Method:       static category_event
     *
     * Purpose:      the entry point of glossary category events
     *
     * Parameters:   a core event object
     *
     * Returns:      the new log entry id or false/null in case of error
     ****************************************************************/
    /**
     * @param \core\event\base $event
     *
     * @return bool|int
     */
    private static function category_event(\core\event\base $event){
        global $DB ;

        //get the autosubscription setting
        $auto_subscribe = ( '1' === get_config('block_glsubs','autoselfsubscribe') );

        // get the event data array
        $eventdata = $event->get_data();

        //get the category id
        $categoryid = (int) $eventdata['objectid'];

        try {
            // get the glossary category
            if('created' === $eventdata['action'] ){
                $glossary_category = $DB->get_record( $eventdata['objecttable'] , array('id' => $categoryid ) );
            } else {
                $glossary_category = $event->get_record_snapshot( $eventdata['objecttable'] ,  $categoryid );
            }

            // get the glossary id
            $glossaryid = (int) $glossary_category->glossaryid ;

            // get the user
            $user = $DB->get_record('user',array('id' => (int)$eventdata['userid'] ) );

            // get the course
            $course = $DB->get_record('course' , array('id' => (int) $eventdata['courseid'] ));

            // get course module
            $course_module = $DB->get_record('course_modules',array('id' => (int) $eventdata['contextinstanceid']));

            // get module
            $module = $DB->get_record('modules',array( 'id' => (int) $course_module->module ));

            // get the module database table name
            $module_table = $module->name ;

            // get the module  table entry
            $course_module_entry = $DB->get_record($module_table, array( 'id' => (int) $course_module->instance ) );

            // get the module name
            $module_name = $course_module_entry->name ;

            // make the text request object
            $text_request = array();
            $text_request['event_type'] = 'category';
            $text_request['event'] = $event ;
            $text_request['event_item'] = $glossary_category;
            $text_request['event_course'] = $course;
            $text_request['event_module'] = $module_name;
            $text_request['event_comment'] = null ;
            $text_request['event_categories'] = $glossary_category->name;
            $text_request['event_author'] = $user->id ;
            // get the event text
            $event_text  = block_glsubs_observer::get_event_text( $text_request );

            // check if the user is registered into the glossary subscriptions main table
            block_glsubs_observer::check_user_subscription( $auto_subscribe , (int) $user->id , $glossaryid );

            // build an event record to add to the subscriptions log
            $record = new \stdClass() ;
            $record->userid = (int)$eventdata['userid'] ; // get the user id for the event
            $record->glossaryid = (int) $glossaryid ; // get the glossary id
            $record->categoryid = $categoryid ; // get the category id from the event
            $record->conceptid = null ; // there is no concept id related to new category events
            $record->authorid = (int)$eventdata['userid']; // get the user id as the author id for creating this category
            $record->processed = 0; // mark it to be processed
            $record->useremail = $user->email; // get user's email at the time of the event
            $record->eventlink = html_writer::link($event->get_url(),'LINK'); // create a link to the event
            $record->eventtext = $event_text ;
            // conditionally set the event type
            $record->eventtype = 'created' === $eventdata['action'] ? EVENT_GENERIC : EVENT_CATEGORY;
            $record->timecreated = $eventdata['timecreated'];
            $record->timeprocessed = null;
            $record->contextinstanceid = (int) $eventdata['contextinstanceid'] ;
            $record->crud = $eventdata['crud'];
            $record->edulevel = (int) $eventdata['edulevel'];

            // check if this is a category created action and initialise a subscription for the creator/user
            if('created' === $eventdata['action']){
                // add a subscription for this category for this user/creator
                $category_subscription = new \stdClass();
                $category_subscription->userid = (int) $user->id ;
                $category_subscription->glossaryid = (int) $glossaryid ;
                $category_subscription->categoryid = $categoryid ;
                $category_subscription->active = 1 ;

                // save the subscription to this glossary category for this user/creator
                if($auto_subscribe){
                    $DB->insert_record('block_glsubs_categories_subs',$category_subscription,false);
                }
            } elseif ('updated' === $eventdata['action']){
                // activate the subscription to this category for the user
                $filters['userid'] = (int) $user->id ;
                $filters['glossaryid'] = (int) $glossaryid ;
                $filters['categoryid'] = (int) $categoryid ;
                $category_subscription = $DB->get_record('block_glsubs_categories_subs' , $filters );
                $category_subscription->active = 1 ;
                if ($auto_subscribe){
                    $DB->update_record('block_glsubs_categories_subs',$category_subscription,false);
                }
            }

            // save the log entry for this glossary event
            $logid = $DB->insert_record( 'block_glsubs_event_subs_log' , $record , true );
        } catch (\Exception $e) {
            // there was an error creating the event entry for this category in this glossary
            return false;
        }
        // return the log id
        return $logid;
    }

    /****************************************************************
     *
     * Method:       static get_event_text
     *
     * Purpose:      Create an event related message in HTML format
     *
     * Parameters:   an event object based on the event type
     * (category, entry, comment)
     *
     * Returns:      the HTML text for the event
     ****************************************************************/
    /**
     * @param array $text_event
     * $text_event['event_type']
     * $text_event['event']
     * $text_event['event_item']
     * $text_event['event_course']
     * $text_event['event_module']
     * $text_event['event_comment']
     * $text_event['event_categories']
     * $text_event['event_author']
     *
     * @return string
     */
    protected static function get_event_text(array $text_event )
    {

        $event_text = '';
        // get event data
        $eventdata = $text_event['event']->get_data();

        //get the autosubscription setting
        $auto_subscribe = ('1' === get_config('block_glsubs', 'autoselfsubscribe'));

        // get event user link to use it in the message
        if ((int)$eventdata['userid'] > 0) {
            try {
                $user_url = new moodle_url('/user/view.php', array('id' => (int)$eventdata['userid']));
                $user_link = html_writer::link($user_url, fullname(\core_user::get_user((int)$eventdata['userid'])));
            } catch (Exception $exception) {
                $user_link = '';
            }
        } else {
            $user_link = '';
        }

        // get the author link to use it in the message
        if ((int)$text_event['event_author'] > 0) {
            try {
                $author_url = new moodle_url('/user/view.php', array('id' => (int)$text_event['event_author']));
                $author_link = html_writer::link($author_url, fullname(\core_user::get_user((int)$text_event['event_author'])));
            } catch (Exception $exception) {
                $author_link = '';
            }
        } else {
            $author_link = '';
        }

        $event_array = str_replace(array("\\", '_', 'mod'), array(' ', ' ', 'module'), $text_event['event']->eventname);
        $event_array = explode(' ',$event_array);
        $event_activity = $event_array[4]; // what it was
        $event_action = $event_array[5]; // what happened
        $event_datetime = date('l d/F/Y G:i:s', (int)$text_event['event']->timecreated); // when it happened

        // new version Message
        // Course
        $event_text .= PHP_EOL . $text_event['event_course']->fullname;

        // Module
        $event_text .= PHP_EOL . $text_event['event_module'];

        // user + action + indefinite article + activity
        $event_text .= PHP_EOL . $user_link ;
        $event_text .= get_string('message_' . $event_action, 'block_glsubs');
        $event_text .= get_string('message_singular_indefinite_article', 'block_glsubs');
        $event_text .= get_string('message_' . $event_activity, 'block_glsubs');

        // if this event is a comment or an entry
        if( $text_event['event_type'] === 'entry' || $text_event['event_type'] === 'comment' ){
            // + for + indefinite article + activity
            $event_text .= get_string('message_for', 'block_glsubs');
            $event_text .= get_string('message_singular_indefinite_article', 'block_glsubs');
            if( $text_event['event_type'] === 'comment' || $event_action === 'updated'){
                $event_text .= get_string('message_entry','block_glsubs');
            } else {
                $event_text .= get_string('message_glossary','block_glsubs');
            }
            if( (int) $text_event['event_author'] > 0  ) {
                // witten by + author
                $event_text .= get_string('message_written_by','block_glsubs');
                $event_text .= $author_link ;
            }
            // on + date
            $event_text .= get_string('message_on','block_glsubs');
            $event_text .= $event_datetime;
        }

        // add two lines of separation
        $event_text .= PHP_EOL . PHP_EOL ;

        // if exists, add the comment with the link to the concept
        if( $text_event['event_type'] === 'comment' ){
            $event_text .= PHP_EOL . get_string('message_' . $event_activity, 'block_glsubs');
            $event_text .= ' ';
            $event_text .= get_string('message_' . $event_action, 'block_glsubs') .' : ';
            // add the comment with the Moodle link
            $event_url = $text_event['event']->get_url();
            $event_url->param('mode','entry');
            $event_url->param('hook',(int) $eventdata['other']['itemid'] );
            $event_text .= html_writer::link( $event_url , $text_event['event_comment'] );
        }

        // if exists, add concept
        if( $text_event['event_type'] === 'entry' || $text_event['event_type'] === 'comment' ){
            $event_text .= PHP_EOL . get_string('message_entry','block_glsubs') . '[';
            if( $text_event['event_type'] === 'comment'){
                $event_text .=  $text_event['event_item']->concept ;
            } else {
                // if(){}
                $event_text .= html_writer::link( $text_event['event']->get_url() , $text_event['event_item']->concept );
            }
            $event_text .= ']';
        }

        // add categories
        if( $text_event['event_categories'] > '' ){
            $event_text .= PHP_EOL . get_string('message_category','block_glsubs') . ' ';
            if($text_event['event_type'] !== 'category'){
                $event_text .=  $text_event['event_categories'];
            } else {
                $event_url = $text_event['event']->get_url();
                $event_url->param('mode','cat');
                $event_url->param('hook','ALL');
                $event_text .= html_writer::link( $event_url , $text_event['event_categories'] );
            }

            $event_text .= ' ';
        }

        // if exists, add definition
        if( $text_event['event_type'] === 'entry' || $text_event['event_type'] === 'comment' ){
            $event_text .= PHP_EOL . get_string('message_definition','block_glsubs') .'[';
            $event_text .= $text_event['event_item']->definition . ']';
        }

        // if exists, add author
        if( $author_link > ''){
            $event_text .= PHP_EOL . get_string('message_author','block_glsubs');
            $event_text .= $author_link;
        }

        // show activity of the event
        $event_text .= PHP_EOL ;
        if( 'created' === $eventdata['action']){
            // if there is an auto subscription choice , inform about it
            if( $auto_subscribe ){
                $event_text .= get_string('glossarysubscriptionon','block_glsubs') ;
            }
            // else state the subscribers will be informed
        } elseif( 'updated' === $eventdata['action'] || ( $text_event['event_type'] === 'comment' && 'deleted' === $eventdata['action'] ) ){
            $event_text .= get_string('glossarysubscriptionsupdated','block_glsubs');
        } elseif ( 'deleted' === $eventdata['action']){
            $event_text .= get_string('glossarysubscriptionsdeleted','block_glsubs');
        }
        $event_text .= $eventdata['target'];

        // add HTML line breaks
        $event_text = nl2br($event_text);

        // send it back
        return $event_text ;
    }


    /****************************************************************
     *
     * Method:       static check_user_subscription
     *
     * Purpose:      Create a main subscription record for the event user
     *               based on the block settings for
     *               automated subscriptions
     *               The default is to create user subscription records
     *
     * Parameters:   autosubscribe setting, user id , glossary id
     * (category, entry, comment)
     *
     * Returns:      the HTML text for the event
     ****************************************************************/
/**
     * @param $auto_subscribe
     * @param $userid
     * @param $glossaryid
     */
    protected static function check_user_subscription($auto_subscribe , $userid  , $glossaryid ){
        global $DB ;
        try {
            $rec_exists = $DB->record_exists('block_glsubs_glossaries_subs',array('userid' => (int) $userid , 'glossaryid' => $glossaryid ) ) ;
        } catch (Exception $exception){
            $rec_exists = true; // trigger an error condition to disable creation of a record while the database is not responding
        }
        // check if the user is registered into the glossary subscriptions main table
        if( $auto_subscribe && ( ! $rec_exists ) ){
            // you must add a subscription record for the user in this main table
            // in order for the subscriptions logic to work
            $record = new \stdClass();
            $record->userid = (int) $userid ; // specify user id
            $record->glossaryid = $glossaryid ; // specify glossary id
            $record->active = 0 ;               // specify full subscription
            $record->newcategories = 0 ;        // specify new categories subscription
            $record->newentriesuncategorised = 0 ; // specify new concepts without categories subscription
            // insert the main record for th user on the specific glossary
            $DB->insert_record('block_glsubs_glossaries_subs', $record) ;
        }
    }
}