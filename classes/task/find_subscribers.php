<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 24/10/2016
 * Time: 16:51
 */

namespace block_glsubs\task;

class find_subscribers extends \core\task\scheduled_task
{
    public function get_name()
    {
        return get_string('findsubscribers','block_glsubs');
    }

    public function execute()
    {
        global $DB , $CFG ;
        require_once $CFG->dirroot . '/config.php' ;
        if($this->execute_condition()){
            $timenow = time();
            ini_set('max_execution_time',0);
            mtrace('Find Glossary Subscribers Task started at '.date('c',$timenow) );
            mtrace("Config dir root is [$CFG->dirroot]" );
            $newevents = $DB->get_record_sql('SELECT COUNT(id) entries FROM {block_glsubs_event_subs_log} WHERE processed = 0 AND timecreated < :timenow ', array( 'timenow' => $timenow ) );
            mtrace("The unprocessed log entries are $newevents->entries" );
        } else {
            return;
        }
    }
    public function execute_condition(){
        return true;
    }
}
