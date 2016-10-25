<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Queen Mary University of London
 * Date: 14/10/2016
 * Time: 14:42
 */

// define event types
// each event may be split into more than one log entries as some activities have more aspects than one
define('EVENT_GENERIC','G'); // New Category or New Concept without category
define('EVENT_AUTHOR','A'); // Entry activity related to the Concept Author
define('EVENT_CATEGORY','C'); // Category Updated Or Deleted
define('EVENT_ENTRY','E'); // Any Concept activity

class block_glsubs_observer
{
    /**
     * @param \core\event\base $event
     *
     * @return bool|null
     */
    static public function observe_all (\core\event\base $event){
//        $event_dummy = '';
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

    /**
     * @param \core\event\base $event
     *
     * @return bool|int|null
     */
    private static function entry_event(\core\event\base $event ){
        global $DB;

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
                $concept_categories[0]->name = '[--generic--] ' ;
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
            $event_url = $event->get_url();

            if( 'created' === $eventdata['action'] || 'updated' === $eventdata['action'] ){
                // add a subscription for this concept comment for this user/creator
                $userid = (int) $user->id ;
                $glossaryid = (int) $glossaryid ;
                $glossary_concept_id = (int) $glossary_concept_id ;
                $filters = array( 'userid' => $userid , 'glossaryid' => $glossaryid , 'conceptid' => $glossary_concept_id ) ;

                // save the subscription to this glossary concept comments for this user/creator
                // check if the subscription to the concept/comments already exists first
                if( $DB->record_exists( 'block_glsubs_concept_subs' , $filters ) ) {
                    $concept_subscription = $DB->get_record( 'block_glsubs_concept_subs' , $filters );
                    $concept_subscription->conceptactive = 1 ;
                    $concept_subscription->commentsactive = 1 ;
                    $DB->update_record('block_glsubs_concept_subs',$concept_subscription , false );
                } else {
                    $concept_subscription = new \stdClass();
                    $concept_subscription->userid = (int) $user->id ;
                    $concept_subscription->glossaryid = $glossaryid ;
                    $concept_subscription->conceptid = $glossary_concept_id ;
                    $concept_subscription->conceptactive = 1 ;
                    $concept_subscription->commentsactive = 1 ;
                    $logid = $DB->insert_record( 'block_glsubs_concept_subs' , $concept_subscription , true );
                }
            }
            // save the log entry for this glossary event
            // build an event text to be used for subscription messages
            $event_text  = PHP_EOL .'<br/> ' . fullname( \core_user::get_user( (int) $eventdata['userid'] ) ). ' @ ' . $course->fullname . ' / ' . $module_name;
            $event_text .= PHP_EOL .'<br/> ' . '[ '. $glossary_concept->concept .' ]  ' . $categories . ' ';
            $event_text .= str_replace(array("\\",'_','mod'),array(' ',' ','module'),$event->eventname) .' @ '. date('l d/F/Y G:i:s', time());
            $event_text .= PHP_EOL .'<br/> [ '. $glossary_concept->definition .' ]  ' ;
            $event_text .= PHP_EOL .'<br/> URL: ' . html_writer::link($event_url,'LINK');
            $event_text .= PHP_EOL .'<br/>' . $event_description = $event->get_description();

            // add message for automatic subscription for this on the user/creator
            if('created' === $eventdata['action']){
                $event_text .= PHP_EOL .'<br/>' . get_string('glossarysubscriptionon','block_glsubs') . $eventdata['target'];
            } elseif ('updated' === $eventdata['action']){
                $event_text .= PHP_EOL .'<br/>' . get_string('glossarysubscriptionsupdated','block_glsubs') . $eventdata['target'];
            } elseif ('deleted' === $eventdata['action']){
                $event_text .= PHP_EOL .'<br/>' . get_string('glossarysubscriptionsdeleted','block_glsubs') . $eventdata['target'];
            }

            // create log entries for each concept category or one generic
            foreach ( $concept_categories as $key => $myconcept_category ) {
                // build an event record to add to the subscriptions log for each category or the generic category
                $record = new \stdClass();
                $record->userid = (int)$eventdata['userid']; // get the user id for the event
                $record->glossaryid = $glossaryid; // get the glossary id
                $record->categoryid =  ( (int) $myconcept_category->categoryid === 0 ) ? null : (int) $myconcept_category->categoryid  ; // get each of the category id from the event
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

    /**
     * @param \core\event\base $event
     *
     * @return bool|int
     */
    private static function comment_event(\core\event\base $event){
        global $DB ;

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

            // get the standard log id
            // $filters = array();
            // $filters['userid'] = (int) $eventdata['userid'] ;
            // $filters['contextinstanceid'] = (int) $eventdata['contextinstanceid'] ;
            // $filters['crud'] = $eventdata['crud'] ;
            // $filters['edulevel'] = $eventdata['edulevel'] ;
            // $filters['timecreated'] = $eventdata['timecreated'] ;
            //
            // you cannot associate at this moment the current event with the standard events log as this event will be written after this code finishes
            // $standard_log_id = $DB->get_record( 'logstore_standard_log' , $filters  ,'id');

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
                foreach ( $concept_categories as $key => & $concept_category_l ) {
                    $category =  $DB->get_record('glossary_categories', array( 'id' => (int) $concept_category_l->categoryid ));
                    $concept_category_l->name = $category->name ;
                    $categories .= '['. $category->name .'] ';
                }
            } else { // add a generic category for the event
                $concept_categories[0] = new \stdClass();
                $concept_categories[0]->id = 0 ;
                $concept_categories[0]->categoryid = null ;
                $concept_categories[0]->entryid = $glossary_concept_id ;
                $concept_categories[0]->name = '[--generic--] ' ;
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
            $event_url = $event->get_url();

            // build an event text to be used for subscription messages
            $event_text  = PHP_EOL .'<br/> ' . fullname( \core_user::get_user( (int) $eventdata['userid'] ) ). ' @ ' . $course->fullname . ' / ' . $module_name;
            $event_text .= PHP_EOL .'<br/> ' . '[ '. $glossary_concept->concept .' ]  [' . $comment_content .'] '. $categories ;
            $event_text .= str_replace(array("\\",'_','mod'),array(' ',' ','module'),$event->eventname) .' @ '. date('l d/F/Y G:i:s', time());
            $event_text .= PHP_EOL .'<br/> URL: ' . html_writer::link($event_url,'LINK');
            $event_text .= PHP_EOL .'<br/>' . $event_description = $event->get_description();
            if('created' === $eventdata['action']){
                $event_text .= PHP_EOL .'<br/>' . get_string('glossarysubscriptionon','block_glsubs') . $eventdata['target'];
            } elseif ('deleted' === $eventdata['action']){ // this is actually an update event for the concept , not a deleted one
                $event_text .= PHP_EOL .'<br/>' . get_string('glossarysubscriptionsupdated','block_glsubs') . $eventdata['target'];
            }

            // add a subscription for this concept comment for this user/creator
            $userid = (int) $user->id ;
            $glossaryid = (int) $glossaryid ;
            $glossary_concept_id = (int) $glossary_concept_id ;
            $filters = array( 'userid' => $userid , 'glossaryid' => $glossaryid , 'conceptid' => $glossary_concept_id ) ;

            // save the subscription to this glossary concept comments for this user/creator
            // check if the subscription to the concept/comments already exists first
            if( $DB->record_exists( 'block_glsubs_concept_subs' , $filters ) ) {
                // activate the subscriptions for this user on this concept and its comments
                // after the events recorder and messages are sent over to the users
                $concept_subscription = $DB->get_record('block_glsubs_concept_subs', $filters);
                $concept_subscription->conceptactive = 1;
                $concept_subscription->commentsactive = 1;
                $DB->update_record( 'block_glsubs_concept_subs' , $concept_subscription, false );
            } else {
                // create a subscription as this activity should be reported to them by a message
                $concept_subscription = new \stdClass();
                $concept_subscription->userid = (int) $user->id ;
                $concept_subscription->glossaryid = $glossaryid ;
                $concept_subscription->conceptid = $glossary_concept_id ;
                $concept_subscription->conceptactive = 1 ;
                $concept_subscription->commentsactive = 1 ;
                $logid = $DB->insert_record( 'block_glsubs_concept_subs' , $concept_subscription , true );
            }

            // save the log entries for this glossary event, one for each category or a generic one
            foreach ($concept_categories as $key => $concept_category_a) {
                // build an event record to add to the subscriptions log
                $record = new \stdClass();
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

    /**
     * @param \core\event\base $event
     *
     * @return bool|int
     */
    private static function category_event(\core\event\base $event){
        global $DB ;

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

            // get the event url
            $event_url = $event->get_url();

            // build an event text to be used for subscription messages
            $event_text  = PHP_EOL .'<br/> ' . fullname( \core_user::get_user( (int) $eventdata['userid'] ) ). ' @ ' . $course->fullname . ' / ' . $module_name;
            $event_text .= PHP_EOL .'<br/> ' . ' [' . $glossary_category->name .'] ';
            $event_text .= str_replace(array("\\",'_','mod'),array(' ',' ','module'),$event->eventname) .' @ '. date('l d/F/Y G:i:s', time());
            $event_text .= PHP_EOL .'<br/> URL: ' . html_writer::link($event_url,'LINK');
            $event_text .= PHP_EOL .'<br/>' . $event_description = $event->get_description();
            if('created' === $eventdata['action']){
                $event_text .= PHP_EOL .'<br/>' . get_string('glossarysubscriptionon','block_glsubs') . $eventdata['target'];
            } elseif('updated' === $eventdata['action']){
                $event_text .= PHP_EOL .'<br/>' . get_string('glossarysubscriptionsupdated','block_glsubs') . $eventdata['target'];
            } elseif ('deleted' === $eventdata['action']){
                $event_text .= PHP_EOL .'<br/>' . get_string('glossarysubscriptionsdeleted','block_glsubs') . $eventdata['target'];
            }

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
                $DB->insert_record('block_glsubs_categories_subs',$category_subscription,false);
            } elseif ('updated' === $eventdata['action']){
                // activate the subscription to this category for the user
                $filters['userid'] = (int) $user->id ;
                $filters['glossaryid'] = (int) $glossaryid ;
                $filters['categoryid'] = (int) $categoryid ;
                $category_subscription = $DB->get_record('block_glsubs_categories_subs' , $filters );
                $category_subscription->active = 1 ;
                $DB->update_record('block_glsubs_categories_subs',$category_subscription,false);
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
}