<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 24/10/2016
 * Time: 16:51
 */

// for tasks the namespace is based on the frankenstyle of plugin type plus underscore plus the plugin name plus backslash plus task
// use this namespace also in the ./db/tasks.php
namespace block_glsubs\task;

// Use this when the category of the event is generic
define('CATEGORY_GENERIC',get_string('CATEGORY_GENERIC','block_glsubs'));
// define the catered limit of user IDs in the system for assisting in creating unique query ids, one billion I think is fine
define('MAX_USERS',1000000000);

class find_subscribers extends \core\task\scheduled_task
{
    public function get_name()
    {
        return get_string('findsubscribers','block_glsubs');
    }

    /**
     * delete entries with 0 as glossaryid as they are not valid to process
     */
    protected function delete_invalid_glossary_entries(){
        global $DB;
        // delete entries with 0 as glossaryid as they are not valid to process
        try {
            $DB->delete_records('block_glsubs_event_subs_log',array('glossaryid' => 0 ));
            mtrace('Invalid glossary subscriptions deletion process is finished');
            return true;
        } catch (\Exception $exception) {
            mtrace('ERROR: There was a database access error while deleting invalid glossary subscriptions '.$exception->getMessage());
        }
        return false;
    }


    /**
     * @param $timenow
     *
     * @return bool
     */
    protected function find_full_subscriptions($timenow ){
        global $DB;
        // get the list of the glossary IDs in these log entries for the full subscribers
        // find full glossary subscriber users to these glossaries and register the user id , log entry id pairs to be used for message deliveries
        mtrace('Fetching log IDs for the Full Glossary event subscriptions');
        $sql  = ' SELECT min(id) logid, glossaryid, timecreated, 0 full FROM {block_glsubs_event_subs_log} ';
        $sql .= ' WHERE glossaryid > 0 AND processed = 0 AND timecreated < :timenow ';
        $sql .= ' GROUP BY userid,glossaryid,eventlink, timecreated ORDER BY timecreated,glossaryid';

        try {
            $full_log_glossary_ids = $DB->get_records_sql($sql,array('timenow' => $timenow ));
            foreach ($full_log_glossary_ids as $log_id => & $full_log_glossary_id ){
                $full_glossary_subscriber_IDs = $DB->get_records('block_glsubs_glossaries_subs', array('glossaryid' => $full_log_glossary_id->glossaryid, 'active' => 1),'userid','userid');
                $records = array();
                foreach ($full_glossary_subscriber_IDs as $key => $glossary_subscriber_ID ){
                    $filters = array('userid' => (int) $glossary_subscriber_ID , 'eventlogid' => $log_id );
                    // avoid duplicate messages logged in the system
                    if(! $DB->record_exists('mdl_block_glsubs_messages_log' , $filters )){
                        $record = new \stdClass();
                        $record->userid = (int) $glossary_subscriber_ID->userid ;
                        $record->eventlogid = (int) $log_id ;
                        $record->timecreated  = time() ;
                        $record->timedelivered = null ;
                        // check if we have valid IDs in order to avoid faulty records
                        if($record->userid * $record->eventlogid > 0 ) {
                            $records[] = $record;
                        }
                    }
                }
                if(count($records) > 0 ){
                    // store the new records into the messages log
                    $DB->insert_records('mdl_block_glsubs_messages_log',$records);
                    mtrace('Added '.count($records).' new full subscription messages');
                }

                // release the memory of the records
                $records = null ;

                // mark this event as processed for full subscriptions
                $full_log_glossary_id->full = 1;
            }
            mtrace('Full subscriptions process is finished');
            return true;
        } catch (\Exception $exception) {
            mtrace('ERROR: There was a database access error while processing the new full glossary subscriptions '.$exception->getMessage());
        }
        return false;
    }

    protected function find_new_uncategorised_subscriptions( $timenow ){
        global $DB;
        mtrace('Fetching log IDs for the New Glossary Uncategorised Concept subscriptions');
        // cater for one billion user IDs , to get unique query IDs
        $sql  = ' SELECT ( l.id * '. MAX_USERS . ' + f.userid ) i , l.id logid , f.userid FROM {block_glsubs_event_subs_log} l ';
        $sql .= ' JOIN {block_glsubs_glossaries_subs} f ON f.glossaryid = l.glossaryid AND f.active = 0 AND f.newentriesuncategorised = 1';
        $sql .= ' WHERE l.processed = 0 AND l.timecreated < :timenow  AND l.eventtype = \'G\' AND ( l.conceptid IS NOT NULL ) ';
        $sql .= ' ORDER BY l.id, l.glossaryid, l.conceptid , f.userid';

        try {
            $new_uncategorised_concept_log_ids = $DB->get_records_sql($sql,array('timenow' => $timenow ) );
            $records = array();
            foreach ($new_uncategorised_concept_log_ids as $log_id => $new_uncategorised_concept_log_id){
                $filter =  array( 'userid' => (int) $new_uncategorised_concept_log_id->userid , 'eventlogid' => (int) $new_uncategorised_concept_log_id->logid );
                // avoid duplicate messages logged in the system
                if(! $DB->record_exists('block_glsubs_messages_log', $filter ) ){
                    $record = new \stdClass();
                    $record->userid = (int) $new_uncategorised_concept_log_id->userid ;
                    $record->eventlogid = (int) $new_uncategorised_concept_log_id->logid ;
                    $record->timecreated = time();
                    // check if we have valid IDs in order to avoid faulty records
                    if($record->userid * $record->eventlogid > 0 ) {
                        $records[] = $record;
                    }
                }
            }
            // if there are any new records store them in the message log
            if(count($records) > 0 ){
                // add the new messages to the message log
                $DB->insert_records('block_glsubs_messages_log',$records);
                mtrace('Added '.count($records).' new uncategorised concept subscription messages');
            }
            // clear memory
            $records = null ;
            mtrace('New uncategorised concept subscriptions process is finished');
            return true;
        } catch (\Exception $exception) {
            mtrace('ERROR: There was a database access error while processing the glossary new uncategorised concepts subscriptions '.$exception->getMessage());
        }
        return false;
    }
    /**
     * @param $timenow
     *
     * @return bool
     */
    protected function find_new_categories_subscriptions( $timenow ){
        global $DB;
        mtrace('Fetching log IDs for the New Glossary Categories subscriptions');
        // cater for one billion user IDs , to get unique query IDs
        $sql  = ' SELECT ( l.id * '. MAX_USERS .' + f.userid ) i , l.id logid , f.userid FROM {block_glsubs_event_subs_log} l ';
        $sql .= ' JOIN {block_glsubs_glossaries_subs} f ON f.glossaryid = l.glossaryid AND f.active = 0 AND f.newcategories = 1';
        $sql .= ' WHERE l.processed = 0 AND l.categoryid > 0 AND l.timecreated < :timenow  AND l.eventtype = \'G\' AND ( l.categoryid IS NOT NULL ) ';
        $sql .= ' ORDER BY l.id, l.glossaryid, l.categoryid , f.userid';

        try {
            $new_category_log_ids = $DB->get_records_sql( $sql , array('timenow' => $timenow ) );
            $records = array();
            foreach ($new_category_log_ids as $log_id => $new_category_log_id){
                $filter = array( 'userid' => (int) $new_category_log_id->userid , 'eventlogid' => (int) $new_category_log_id->logid );
                // avoid duplicate messages logged in the system
                if(! $DB->record_exists('block_glsubs_messages_log', $filter ) ){
                    $record = new \stdClass();
                    $record->userid = (int) $new_category_log_id->userid ;
                    $record->eventlogid = (int) $new_category_log_id->logid ;
                    $record->timecreated = time();
                    // check if we have valid IDs in order to avoid faulty records
                    if($record->userid * $record->eventlogid > 0 ){
                        $records[] = $record ;
                    }
                }
            }
            // if there are any new records store them in the message log
            if(count($records) > 0 ){
                // add messages to the meesages log
                $DB->insert_records('block_glsubs_messages_log',$records);
                mtrace('Added '. count($records) .' new categories subscription messages');
            }
            // clear memory
            $records = null ;
            mtrace('New categories subscriptions process is finished');
            return true;
        } catch (\Exception $exception) {
            mtrace('ERROR: There was a database access error while processing the glossary new categories subscriptions '.$exception->getMessage());
        }
        return false;
    }

    protected function find_author_subscriptions( $timenow ){
        global $DB ;
        mtrace('Fetching log IDs for the Glossary Author subscriptions');
        // cater for a lot of user IDs to get unique query IDs
        $sql  = ' SELECT ( l.id * '. MAX_USERS . ' + a.userid ) i , l.id logid , a.userid ';
        $sql .= ' FROM mdl_block_glsubs_event_subs_log l';
        $sql .= ' JOIN {block_glsubs_glossaries_subs} f ON f.glossaryid = l.glossaryid AND f.active = 0 ';
        $sql .= ' JOIN {block_glsubs_authors_subs} a  ON a.glossaryid = l.glossaryid AND a.authorid = l.authorid ';
        $sql .= ' WHERE l.processed = 0 AND l.authorid > 0 AND l.timecreated < :timenow GROUP BY l.categoryid, l.conceptid ORDER BY i';

        try {
            $new_author_sub_messages = $DB->get_records_sql( $sql , array('timenow' => $timenow ) );
            $records = array();
            foreach ($new_author_sub_messages as $id => $new_author_sub_message) {
                $filter = array('userid' => (int) $new_author_sub_message->userid , 'eventlogid' => (int) $new_author_sub_message->logid );
                // avoid duplicate messages logged in the system
                if(! $DB->record_exists( 'block_glsubs_messages_log', $filter ) ){
                    $record = new \stdClass();
                    $record->userid = (int) $new_author_sub_message->userid ;
                    $record->eventlogid = (int) $new_author_sub_message->logid ;
                    $record->timecreated = time();
                    // check if we have valid IDs in order to avoid faulty records
                    if($record->userid * $record->eventlogid > 0 ){
                        $records[] = $record ;
                    }
                }
            }

            // if there are any new records store them in the message log
            if(count($records) > 0 ){
                // add messages to the meesages log
                $DB->insert_records('block_glsubs_messages_log',$records);
                mtrace('Added '. count($records) .' authors subscription messages');
            }
            // clear memory
            $records = null ;
            mtrace('Authors subscriptions process is finished');
            return true;
        } catch (\Exception $exception) {
            mtrace('ERROR: There was a database access error while processing the glossary authors subscriptions '.$exception->getMessage());
            return false;
        }
    }
    protected function find_category_subscriptions( $timenow ){
        global $DB ;
        mtrace('Fetching log IDs for the Glossary Category subscriptions');
        // cater for a lot of user IDs to get unique query IDs
        $sql  = ' SELECT ( l.id * ' . MAX_USERS . ' + c.userid ) i , l.id logid , c.userid , l.categoryid , l.conceptid, l.eventtext FROM mdl_block_glsubs_event_subs_log l';
        $sql .= ' JOIN mdl_block_glsubs_glossaries_subs f ON f.userid = l.userid AND f.active = 0';
        $sql .= ' JOIN mdl_block_glsubs_categories_subs c ON c.glossaryid = l.glossaryid AND c.categoryid = l.categoryid';
        $sql .= ' WHERE l.processed = 0 AND l.authorid > 0 AND l.categoryid > 0 AND l.timecreated < :timenow';
        $sql .= ' GROUP BY l.categoryid, l.conceptid ORDER BY i';

        try {
            $new_category_sub_messages = $DB->get_records_sql( $sql , array('timenow' => $timenow ) );
            $records = array();
            foreach ($new_category_sub_messages as $id => $new_category_sub_message) {
                $filter = array('userid' => (int) $new_category_sub_message->userid , 'eventlogid' => (int) $new_category_sub_message->logid );
                // avoid duplicate messages logged in the system
                if(! $DB->record_exists( 'block_glsubs_messages_log', $filter ) ){
                    $record = new \stdClass();
                    $record->userid = (int) $new_category_sub_message->userid ;
                    $record->eventlogid = (int) $new_category_sub_message->logid ;
                    $record->timecreated = time();
                    // check if we have valid IDs in order to avoid faulty records
                    if($record->userid * $record->eventlogid > 0 ){
                        $records[] = $record ;
                    }
                }
            }

            // if there are any new records store them in the message log
            if(count($records) > 0 ){
                // add messages to the meesages log
                $DB->insert_records('block_glsubs_messages_log',$records);
                mtrace('Added '. count($records) .' category subscription messages');
            }
            // clear memory
            $records = null ;
            mtrace('Category subscriptions process is finished');
            return true;
        } catch (\Exception $exception) {
            mtrace('ERROR: There was a database access error while processing the glossary category subscriptions '.$exception->getMessage());
            return false;
        }
    }
    protected function find_concept_subscriptions( $timenow ){
        global $DB ;
        mtrace('Fetching log IDs for the Glossary Concepts subscriptions');
        // cater for a lot of user IDs to get unique query IDs
        $sql  = ' SELECT ( l.id * ' . MAX_USERS . ' + c.userid ) i , l.id logid , c.userid , c.conceptactive , c.commentsactive';
        $sql .= ' FROM mdl_block_glsubs_event_subs_log l';
        $sql .= ' JOIN mdl_block_glsubs_glossaries_subs f ON f.userid = l.userid AND f.active = 0';
        $sql .= ' JOIN mdl_block_glsubs_concept_subs c ON c.glossaryid = l.glossaryid AND c.conceptid = l.conceptid ';
        $sql .= ' AND (c.conceptactive = 1 OR c.commentsactive = 1)';
        $sql .= ' WHERE l.processed = 0 AND l.authorid > 0 AND l.conceptid > 0 AND l.timecreated < :timenow';
        $sql .= ' GROUP BY l.categoryid, l.conceptid ORDER BY i';

        try {
            $new_concept_sub_messages = $DB->get_records_sql( $sql , array('timenow' => $timenow ) );
            $records = array();
            foreach ($new_concept_sub_messages as $id => $new_concept_sub_message) {
                // check if we have either concept or comment related active subscription
                if( (int) $new_concept_sub_message->conceptactive ===1 || (int) $new_concept_sub_message->commentsactive === 1 ){
                    $filter = array('userid' => (int) $new_concept_sub_message->userid , 'eventlogid' => (int) $new_concept_sub_message->logid );
                    // avoid duplicate messages logged in the system
                    if(! $DB->record_exists( 'block_glsubs_messages_log', $filter ) ){
                        $record = new \stdClass();
                        $record->userid = (int) $new_concept_sub_message->userid ;
                        $record->eventlogid = (int) $new_concept_sub_message->logid ;
                        $record->timecreated = time();
                        // check if we have valid IDs in order to avoid faulty records
                        if($record->userid * $record->eventlogid > 0 ){
                            $records[] = $record ;
                        }
                    }
                }
            }
            // if there are any new records store them in the message log
            if(count($records) > 0 ){
                // add messages to the meesages log
                $DB->insert_records('block_glsubs_messages_log',$records);
                mtrace('Added '. count($records) .' concepts subscription messages');
            }
            // clear memory
            $records = null ;
            mtrace('Glossary concepts subscriptions process is finished');
            return true;
        } catch (\Exception $exception) {
            mtrace('ERROR: There was a database access error while processing the glossary concept subscriptions '.$exception->getMessage());
            return false;
        }
    }

    /**
     * @param $timenow
     *
     * @return bool
     */
    protected function remove_deleted_concepts_subscriptions( $timenow ){
        global $DB ;
        mtrace('Removing deleted concepts subscriptions');
        try {
            $counter = $DB->count_records( 'block_glsubs_concept_subs',array('conceptid' => 0 ));
            // delete records refering to a non existing concept ID like 0
            $DB->delete_records('block_glsubs_concept_subs',array('conceptid' => 0 ) );
            mtrace('Erased '. $counter . ' subscriptions with invalid concept ID');
        } catch (\Exception $exception) {
            mtrace('Error while erasing invalid concept subscriptions');
            return false;
        }
        // get the set of the latest erased glossary concept IDs
        $sql  = ' SELECT DISTINCT t.conceptid FROM {block_glsubs_concept_subs} t JOIN {block_glsubs_event_subs_log} l ';
        $sql .= ' ON t.conceptid = l.conceptid AND l.processed = 0 AND l.timecreated < :timenow ';
        $sql .= ' WHERE t.conceptid > 0 AND t.conceptid NOT IN (SELECT id FROM {glossary_entries})';

        try {
            $deleted_concept_IDs = $DB->get_records_sql( $sql , array( 'timenow' => $timenow ) );
            $counter = 0 ;
            foreach ($deleted_concept_IDs as $key => $deleted_concept_ID){
                $counter += $DB->count_records( 'block_glsubs_concept_subs' , array('conceptid' => (int) $key ) );
                $DB->delete_records( 'block_glsubs_concept_subs' , array('conceptid' => (int) $key ) );
            }
            mtrace('Erased '. $counter . ' subscriptions with erased concept IDs');
        } catch (\Exception $exception) {
            mtrace('ERROR: There was a database error while removing subscriptions on erased concept IDs '.$exception->getMessage() );
            return false;
        }
        return true;
    }

    /**
     * @param $timenow
     *
     * @return bool
     */
    protected function remove_deleted_category_subscriptions( $timenow ){
        global $DB ;
        mtrace('Removing deleted category subscriptions');
        try {
            $counter = $DB->count_records( 'block_glsubs_categories_subs',array('categoryid' => 0 ));
            // delete records refering to a non existing category ID like 0
            $DB->delete_records('block_glsubs_categories_subs',array('categoryid' => 0 ) );
            mtrace('Erased '. $counter . ' subscriptions with invalid category ID');
        } catch (\Exception $exception) {
            mtrace('Error while erasing invalid category subscriptions');
            return false;
        }
        // get the set of the latest erased glossary category IDs
        $sql  = ' SELECT DISTINCT t.categoryid FROM {block_glsubs_categories_subs} t JOIN {block_glsubs_event_subs_log} l ';
        $sql .= ' ON t.categoryid = l.categoryid AND l.processed = 0 AND l.timecreated < :timenow ';
        $sql .= ' WHERE t.categoryid > 0 AND t.categoryid NOT IN (SELECT id FROM {glossary_categories})';

        try {
            $deleted_category_IDs = $DB->get_records_sql( $sql , array( 'timenow' => $timenow ) );
            $counter = 0 ;
            foreach ($deleted_category_IDs as $key => $deleted_category_ID){
                $counter += $DB->count_records( 'block_glsubs_categories_subs' , array('categoryid' => (int) $key ) );
                $DB->delete_records( 'block_glsubs_categories_subs' , array('categoryid' => (int) $key ) );
            }
            mtrace('Erased '. $counter . ' subscriptions with erased category IDs');
        } catch (\Exception $exception) {
            mtrace('ERROR: There was a database error while removing subscriptions on erased category IDs '.$exception->getMessage() );
            return false;
        }
        return true;
    }

    /**
     * @param $timenow
     *
     * @return bool
     */
    protected function remove_deleted_author_subscriptions( $timenow ){
        global $DB ;
        mtrace('Removing deleted authors subscriptions');
        try {
            $counter = $DB->count_records( 'block_glsubs_authors_subs',array('authorid' => 0 ));
            // delete records refering to a non existing author ID like 0
            $DB->delete_records('block_glsubs_authors_subs',array('authorid' => 0 ) );
            mtrace('Erased '. $counter . ' subscriptions with invalid author ID');
        } catch (\Exception $exception) {
            mtrace('Error while erasing invalid author subscriptions');
            return false;
        }

        // get the set of the latest erased glossary author IDs
        $sql  = 'SELECT l.authorid FROM {block_glsubs_event_subs_log} l JOIN {user} u ON u.id = l.authorid and u.deleted = 1 ';
        $sql .=' WHERE l.authorid > 0 AND l.processed = 0 AND l.timecreated < :timenow GROUP BY l.authorid ';

        try {
            $deleted_author_IDs = $DB->get_records_sql( $sql , array( 'timenow' => $timenow ) );
            $counter = 0 ;
            foreach ($deleted_author_IDs as $key => $deleted_author_ID){
                $counter += $DB->count_records( 'block_glsubs_authors_subs' , array('authorid' => (int) $key ) );
                $DB->delete_records( 'block_glsubs_authors_subs' , array('authorid' => (int) $key ) );
            }
            mtrace('Erased '. $counter . ' subscriptions with erased author IDs');
        } catch (\Exception $exception) {
            mtrace('ERROR: There was a database error while removing subscriptions on erased author IDs '.$exception->getMessage() );
            return false;
        }
        return true;
    }
    protected function remove_deleted_concept_subscriptions( $timenow ){
        global $DB ;
        mtrace('Removing deleted concept subscriptions');
        try {
            $counter = $DB->count_records( 'block_glsubs_concept_subs',array('conceptid' => 0 ));
            // delete records refering to a non existing concept ID like 0
            $DB->delete_records('block_glsubs_concept_subs',array('conceptid' => 0 ) );
            mtrace('Erased '. $counter . ' subscriptions with invalid concept ID');
        } catch (\Exception $exception) {
            mtrace('Error while erasing invalid concept subscriptions');
            return false;
        }

        // get the set of the latest erased glossary author IDs
        $sql  = ' SELECT DISTINCT l.conceptid FROM {block_glsubs_event_subs_log} l';
        $sql .= ' WHERE l.conceptid > 0 AND l.processed = 0 AND l.timecreated < :timenow AND l.conceptid NOT IN ( SELECT id FROM {glossary_entries} )';
        $sql .= ' ORDER BY l.conceptid';
        try {
            $deleted_concept_IDs = $DB->get_records_sql( $sql , array( 'timenow' => $timenow ) );
            $counter = 0 ;
            foreach ($deleted_concept_IDs as $key => $deleted_concept_ID){
                $counter += $DB->count_records( 'block_glsubs_concept_subs' , array('conceptid' => (int) $key ) );
                $DB->delete_records( 'block_glsubs_concept_subs' , array('conceptid' => (int) $key ) );
            }
            mtrace('Erased '. $counter . ' subscriptions with erased concept IDs');
        } catch (\Exception $exception) {
            mtrace('ERROR: There was a database error while removing subscriptions on erased concept IDs '.$exception->getMessage() );
            return false;
        }
        // all fine
        return true;
    }
    /**
     * Main entry point for the task to find subscribers for the glossary events
     */
    public function execute()
    {
        global $DB , $CFG ;
        require_once $CFG->dirroot . '/config.php' ;
        if($this->execute_condition()){
            $error_status = false ;
            $timenow = time();
            ini_set('max_execution_time',0);
            mtrace('=================================================================================================');
            mtrace('Find Glossary Subscribers Task started at '.date('c',$timenow) );
            // mtrace("Config dir root is [$CFG->dirroot]" );
            mtrace('Counting glossary event entries');

            // create a table to keep the user id , event log id pairs for the subscriptions, time creation stamp and delivery flag via XMLDB

            // delete invalid entries
            $error_status = ( ! $this->delete_invalid_glossary_entries() ) || $error_status ;

            // now it is clean to read the unprocessed entries
            try {
                $new_events_counter = $DB->get_record_sql('SELECT COUNT(id) entries FROM {block_glsubs_event_subs_log} WHERE processed = 0 AND timecreated < :timenow ', array( 'timenow' => $timenow ) );
                mtrace("The unprocessed log entries are $new_events_counter->entries" );
            } catch (\Exception $exception) {
                mtrace('ERROR: There was a database access error '.$exception->getMessage());
                $error_status = true;
                $new_events_counter = 0 ;
            }
            if( ! $error_status && $new_events_counter > 0 ){
                // deal with full subscriptins first
                $error_status = ( ! $this->find_full_subscriptions( $timenow ) ) || $error_status ;

                // deal with generic subscriptions now
                // deal with new categories subscriptions
                $error_status = ( ! $this->find_new_categories_subscriptions( $timenow ) ) || $error_status ;

                // deal with new uncategorised concepts subscriptions
                $error_status = ( ! $this->find_new_uncategorised_subscriptions( $timenow ) ) || $error_status ;

                // get the list of the author IDs from these log entries
                // find the glossary authors subscribers that are not having full subscription and register user id , log entry id pairs to be used for message deliveries
                $error_status = ( ! $this->find_author_subscriptions( $timenow )) || $error_status ;

                // get the list of the category IDs from these log entries
                // find the glossary categories subscribers that are not having full subscription and register user id, log entry id pairs to be used for message deliveries
                $error_status = ( ! $this->find_category_subscriptions( $timenow )) || $error_status ;

                // get the list of the concept IDs from the latest loeg entries
                // find the users subscribing to these concepts and / or their comments that are not having full subscription
                // register user id , log entry id pairs to be used for message deliveries
                $error_status = ( ! $this->find_concept_subscriptions( $timenow )) || $error_status ;

                if( ! $error_status ){
                    mtrace('There were no errors, continuing to remove non existing target subscriptions');
                    // if no errors occured then
                    // delete all non existing concept subscriptions
                    $error_status = ( ! $this->remove_deleted_concepts_subscriptions( $timenow ) ) || $error_status ;

                    // delete all non existing category subscriptions
                    $error_status = ( ! $this->remove_deleted_category_subscriptions( $timenow ) ) || $error_status ;

                    // delete all non existing author subscriptions
                    $error_status = ( ! $this->remove_deleted_author_subscriptions( $timenow ) ) || $error_status ;

                    // delete all non existing glossary subscriptions
                    $error_status = ( ! $this->remove_deleted_concept_subscriptions( $timenow ) ) || $error_status ;

                }

                // continue if there is no error and there are unprocessed event log entries
                if($error_status){
                    mtrace('ERROR: There was an issue while erasing invalid subscriptions ');
                } else {
                    // update all events up to now as processed and add time stamp
                    mtrace('All good, ready to mark all unprocessed events as done');

                    // db update processed and timeprocessed
                    try {
                        if( $DB->set_field_select('block_glsubs_event_subs_log' , 'timeprocessed' , time() , ' processed = 0 AND timecreated < :timenow ', array('timenow' => $timenow ) ) ){
                            $DB->set_field_select('block_glsubs_event_subs_log' , 'processed' , 1 , ' processed = 0 AND timecreated < :timenow ', array('timenow' => $timenow ) );
                        }
                        mtrace('Events up to '.date('c',$timenow).' are marked as processed');
                    } catch (\Exception $exception) {
                        mtrace('ERROR: An error occured on updating the glossary event logs as processed, will try again next time');
                        return false;
                    }
                }
                mtrace('=================================================================================================');
            }
        } else {
            return;
        }
    }

    /**
     * @return bool
     */
    public function execute_condition(){
        return true;
    }
}
