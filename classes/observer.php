<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 14/10/2016
 * Time: 14:42
 */

// namespace moodle\blocks\glsubs\classes\;

define('EVENT_GENERIC','G');
define('EVENT_AUTHOR','A');
define('EVENT_CATEGORY','C');
define('EVENT_ENTRY','E');

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
                block_glsubs_observer::category_created( $event );
                break;
            case '\mod_glossary\event\category_updated':
                break;
            case '\mod_glossary\event\category_deleted':
                break;
            case '\mod_glossary\event\entry_created':
                break;
            case '\mod_glossary\event\entry_updated':
                break;
            case '\mod_glossary\event\entry_deleted':
                break;
            case '\mod_glossary\event\entry_approved':
                break;
            case '\mod_glossary\event\entry_disapproved':
                break;
            case '\mod_glossary\event\comment_created':
                break;
            case '\mod_glossary\event\comment_deleted':
                break;
            default:
                return NULL;
        }
        return TRUE;
        // save the event in the glossary events log
    }

    public function init(){

    }

    /**
     * @param \core\event\base $event
     */
    private static function category_created(\core\event\base $event){
        global $DB ;
        // get the new or deleted record if it was saved by the event handler
        // try {
        //   $event_record = $event->get_record_snapshot($event->data['objecttable'], (int) $event->data['objectid']);
        // } catch (\Exception $e){
            // the record was not saved by the event handler
            // As this is a NEW record, there is no record snapshot, not any logic around it is required
            // $event_record = new \stdClass();
        // }
        $eventdata = $event->get_data();
        $glossary_category = $DB->get_record( $eventdata['objecttable'] , array('id' => (int) $eventdata['objectid'] ) );
        $glossaryid = $glossary_category->glossaryid ;
        $user = $DB->get_record('user',array('id' => (int)$eventdata['userid'] ) );

        $event_text = $event->eventname .' @ '. date('l d/m/Y G:i:s', time());
        $event_text .= PHP_EOL .'<br/>' . $event_description = $event->get_description();
        $event_text .= PHP_EOL .'<br/> URL: ' . html_writer::link($event->get_url(),null);
        $event_text .= PHP_EOL .'<br/> ' . $eventdata['target']. ' '. $eventdata['action'];
        $event_text .= PHP_EOL .'<br/> ' . fullname( \core_user::get_user( (int) $eventdata['userid'] ) ). ' @ ' . $eventdata['action'];


        $record = new \stdClass() ;
        $record->userid = (int)$eventdata['userid'] ; // get the user id for the event
        $record->glossaryid = (int) $glossaryid ; // get the glossary id
        $record->categoryid = (int) $eventdata['objectid'] ; // get the category id from the event
        $record->conceptid = null ; // there is no concept id related to new category events
        $record->authorid = (int)$eventdata['userid']; // get the user id as the author id for creating this category
        $record->processed = 0; // mark it to be processed
        $record->useremail = $user->email; // get user's email at the time of the event
        $record->eventlink = html_writer::link($event->get_url(),null); // create a link to the event
        $record->eventtext = $event_text ;
        $record->eventtype = EVENT_CATEGORY ; // Category related event
        $record->timecreated = $eventdata['timecreated'];
        $record->timeprocessed = null;
        // $logid = 0 ;
        try {
            $logid = $DB->insert_record( 'block_glsubs_event_subs_log' , $record , true );
        } catch (\Exception $e) {
            return false;
        }
        return $logid;
    }
}