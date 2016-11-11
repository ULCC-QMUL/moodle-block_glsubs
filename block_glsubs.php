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

// namespace glsubs;
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');
require_once __DIR__.'/../../config.php' ;
global $CFG ;
include_once  $CFG->dirroot . '/blocks/glsubs/classes/block_glsubs_form.php' ;
/** @noinspection PhpIllegalPsrClassPathInspection */
class block_glsubs extends block_base {
    /**
     * Glossary Subscriptions Block Frontend Controller Presenter
     * Sept 2016
     * QM+
     * vasileios
     */
    public function init(){
        // set the title of this plugin
        $this->title = get_string('pluginname', 'block_glsubs');
    }

    /**
     * Return the current page URL
     * Used to associate the current page with the form action target, so it returns to the same page after submit
     * @return string
     */
    private function currentPageURL() {
        $pageURL = 'http';
        if (array_key_exists('HTTPS',$_SERVER) && $_SERVER['HTTPS'] === 'on') {$pageURL .= 's';}
        $pageURL .= '://';
        if ($_SERVER['SERVER_PORT'] !== '80') {
            $pageURL .= $_SERVER['SERVER_NAME']. ':' .$_SERVER['SERVER_PORT'].$_SERVER['REQUEST_URI'];
        } else {
            $pageURL .= $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
        }

        $pageurlparts = explode('?',$pageURL);
        $pageurlparts['protocol'] = explode('://',$pageurlparts[0])[0];
        $pageurlparts['host'] = explode('/',explode('://',$pageurlparts[0])[1])[0];
        $pageurlparts['pagepath'] = explode($pageurlparts['host'],explode('://',$pageurlparts[0])[1])[1];
        foreach(explode('&',$pageurlparts[1]) as $key =>$parameterset ){
            $keyValueArray = explode('=',$parameterset);
            if(array_key_exists(0,$keyValueArray)){  $param = $keyValueArray[0] ;} else { $param = ''; }
            if(array_key_exists(1,$keyValueArray)){  $value = $keyValueArray[1] ;} else { $value = ''; }
            $pageurlparts['parameters'][ $param ]= $value ;
        }
        $pageurlparts['fullurl'] = $pageURL;

        return $pageurlparts;
    }

    /**
     * @param $glossaryid
     *
     * @return array
     */
    protected function get_latest_messages( $glossaryid ){
        global $DB , $USER ;
        $messages = array();
        $glsubs_settings = get_config('block_glsubs');
        $messages_count = (int) $glsubs_settings->messagestoshow ;
        try {
            $sql  = 'SELECT l.id , l.userid , l.eventlogid , l.timecreated ,l.timedelivered FROM {block_glsubs_messages_log} l ';
            $sql .= ' JOIN {block_glsubs_event_subs_log} e ON l.eventlogid = e.id AND e.glossaryid = :glossaryid ';
            $sql .= ' WHERE l.userid = :userid AND l.timedelivered IS NULL ORDER BY l.id';
            $messages = $DB->get_records_sql( $sql , array('userid' => (int) $USER->id , 'glossaryid' => $glossaryid ) , 0 , $messages_count);
            // if there are no unread messages show the latest read
            if( count($messages ) === 0){
                $sql  = 'SELECT l.* FROM {block_glsubs_messages_log} l ';
                $sql .= 'JOIN {block_glsubs_event_subs_log} e ON e.id = l.eventlogid AND e.glossaryid = :glossaryid ';
                $sql .=' WHERE l.userid = :userid ORDER BY l.id DESC ';
                $messages = $DB->get_records_sql( $sql , array('userid' => (int) $USER->id , 'glossaryid' => $glossaryid ) , 0 , $messages_count);
            }
        } catch (\Exception $exception){
            return $messages;
        }

        if( count($messages) > 0 ){
            foreach ($messages as $key =>& $message){
                try {
                    $message->event = $DB->get_record('block_glsubs_event_subs_log', array('id' => (int) $message->eventlogid ));
                    $message->date = date('Y-m-d H:i:s', (int) $message->event->timecreated);
                    $message->user = $DB->get_record('user', array('id' => (int) $message->event->userid));
                    $message->author = $DB->get_record('user', array('id' => (int) $message->event->authorid));
                } catch (\Exception $exception){
                    $this->content->text .= '<strong>'.get_string('block_could_not_access','block_glsubs').$glsubs_settings->messagestoshow.get_string('block_most_recent','block_glsubs').'</strong>';
                }
            }
        }
        return $messages;
    }

    /**
     * @param $glossaryid
     */
    private function show_messages($glossaryid ){
        global $DB, $USER ;
        // get the block settings from its configuration
        $glsubs_settings = get_config('block_glsubs');
        $messages_count = (int) $glsubs_settings->messagestoshow ;
        if( (int) $glsubs_settings->messagestoshow > 0 ){
            $messages = $this->get_latest_messages( $glossaryid );
            try {
                // read how many unread messages exist for this glossary
                $sql  = 'SELECT l.id , l.userid , l.eventlogid , l.timecreated ,l.timedelivered FROM {block_glsubs_messages_log} l ';
                $sql .= ' JOIN {block_glsubs_event_subs_log} e ON l.eventlogid = e.id AND e.glossaryid = :glossaryid ';
                $sql .= ' WHERE l.userid = :userid AND l.timedelivered IS NULL ORDER BY l.id';
                $cmessages = $DB->get_records_sql( $sql , array('userid' => (int) $USER->id , 'glossaryid' => $glossaryid ) , 0 , $messages_count );
                $counter = count( $cmessages ) ;
                // release memory now
                $cmessages = null;
                $unread = ( $counter > 0 );
            } catch (\Exception $exception){
                $unread = false ;
            }

            // Create a toggle mechanism for showing or hiding the recent messages
            if(count($messages)> 0){
                $javascriptswitch  = chr(13).' <script>';
                $javascriptswitch .= chr(13).' $( document ).ready(function(){ ';
                $javascriptswitch .= chr(13).'      $("#glossarymessagesshowhide").click(function(){ ';
                $javascriptswitch .= chr(13).'          $("#glossarymessagesblocktable").toggle();  ';
                $javascriptswitch .= chr(13).'          $("#glossarymessagesshowhide").toggle();  ';
                $javascriptswitch .= chr(13).'          $("#glossarymessagesshowhide_2").toggle();  ';
                $javascriptswitch .= chr(13).'      }); ';
                $javascriptswitch .= chr(13).'      $("#glossarymessagesshowhide_2").click(function(){ ';
                $javascriptswitch .= chr(13).'          $("#glossarymessagesblocktable").toggle();  ';
                $javascriptswitch .= chr(13).'          $("#glossarymessagesshowhide").toggle();  ';
                $javascriptswitch .= chr(13).'          $("#glossarymessagesshowhide_2").toggle();  ';
                $javascriptswitch .= chr(13).'      }); ';
                $javascriptswitch .= chr(13).' }) ; ';
                $javascriptswitch .= chr(13).' </script>';
                $javascriptswitch .= chr(13);
                $this->content->text .= '<div id="glossarymessagesblock">';
                $this->content->text .= '<span id="glossarymessagesshowhide">';
                $this->content->text .= get_string('view_show_hide','block_glsubs');
                $this->content->text .= '</span>'; // id="glossarymessagesshowhide"
                $this->content->text .= '<span id="glossarymessagesshowhide_2" style="display: none ;">';
                $this->content->text .= get_string('view_show_hide_2','block_glsubs');
                $this->content->text .= '</span>'; // id="glossarymessagesshowhide"

                $this->content->text .= get_string('block_found','block_glsubs') . count($messages);
                $this->content->text .= $unread ? get_string('block_unread_messages','block_glsubs') : get_string('block_read_messages','block_glsubs');
                $this->content->text .= '<br/>';
                $this->content->text .= '<div id="glossarymessagesblocktable" style="display: none ;">';


                $this->content->text .= '<table id="glossarymessagestable"><thead><tr>';
                $this->content->text .= '<th>'.get_string('view_when','block_glsubs') .'</th>';
                $this->content->text .= '<th>&nbsp;</th>';
                $this->content->text .= '</th><th>'.get_string('view_by_user','block_glsubs').'</th>';
                $this->content->text .= '<th>&nbsp;</th>';
                $this->content->text .= '<th>' . get_string('view_on_concept','block_glsubs').'</th></tr></thead><tbody>';
                foreach ($messages as $key => $message){
                    if( (int) $message->event->conceptid > 0 ){
                        try {
                            $record = $DB->get_record('glossary_entries', array('id' =>(int) $message->event->conceptid ) );
                            $name = $record->concept ;
                        } catch (\Exception $exception) {
                            $name = '';
                        }
                    } else {
                        try {
                            $record = $DB->get_record('glossary_categories', array('id' => (int) $message->event->categoryid ));
                            $name = $record->name ;
                        } catch (\Exception $exception) {
                            $name = '';
                        }
                    }
                    try {
                        $link = html_writer::link(new moodle_url('/blocks/glsubs/view.php' , array('id' => $key  )), substr( $message->date ,0 ,10 ),array('title' => $message->date, 'font-size' => '90%;' ));
                    } catch (\Exception $exception){
                        $link = '';
                    }
                    $this->content->text .= '<tr><td>' . $link .'</td>';
                    $this->content->text .= '<td>&nbsp;</td>';
                    $this->content->text .= '<td> '.fullname($message->user).'</td>';
                    $this->content->text .= '<td>&nbsp;</td>';
                    $this->content->text .= '<td>' . $name . '</td></tr>';
                }
                $this->content->text .= '</tbody></table><hr style="visibility: visible !important; display: inline !important;"/>';


                $this->content->text .= '</div>'; // id="glossarymessagesblocktable"
                $this->content->text .= '</div>'; // id="glossarymessagesblock"
                $this->content->text .= $javascriptswitch ;
            }
        }
    }
    /**
     * Subscriptions Block Contents creation function
     * @return string
     */
    public function get_content()
    {
        /** @var stdClass $this */
        // define usage of global variables
        global $PAGE , $COURSE ;// , $DB , $CFG ; // $USER, $SITE , $OUTPUT, $THEME, $OUTPUT ;

        if ( null !== $this->title) {
            $this->title = get_string('blockheader','block_glsubs');
        }

        // if the contents are already set, just return them
        if($this->content !==  NULL) {
            return $this->content;
        }

        // this is only for logged in users
        if( ! isloggedin() || isguestuser() ){
            return '';
        }

        // get the current moodle configuration
        require_once  __DIR__. '/../../config.php' ;

        // this is only for logged in users
        require_login();

        // get the module information
        $courseinfo = get_fast_modinfo($COURSE);

        // prapare for contents
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->text .= '<strong>'.$PAGE->title . '</strong>';

        // add a footer for the block
        $this->content->footer = '<hr style="display: block!important;"/><div style="text-align:center;">'.get_string('blockfooter','block_glsubs').'</div>';

        // get the id parameter if exists
        $cmid = optional_param('id', 0, PARAM_INT);

        // check if there is a valid glossary view page
        if( $cmid > 0 ) {
            // Check if the page is referring to a glossary module view activity
            if('mod-glossary-view' !== $PAGE->pagetype){
                return $this->content ;
            }
            // set page context
            $PAGE->set_context(context_module::instance($cmid));
            try {
                if ($courseinfo->get_cm($cmid)) {
                    $cm = $courseinfo->get_cm($cmid);
                } else {
                    return $this->content;
                }
            } catch (Exception $e) {
                return $this->content;
            }

            // Check if the course module is available and it is visible and it is visible to the user and it is a glossary module
            if ( ! ( TRUE === $cm->available && '1' === $cm->visible && TRUE === $cm->uservisible && 'glossary' === $cm->modname ) ) {
                return $this->content;
            }

            // get glossary ID
            $glossaryid = (int) $cm->instance ;

            // show unread messages
            $this->show_messages( $glossaryid );

            // create a glossary subscriptions block form and assign its action to the original page
            $subscriptions_form = new block_glsubs_form($this->currentPageURL()['fullurl']);
            // test for the form status , do kee the order of cancelled, submitted, new
            if ($subscriptions_form->is_cancelled()) {
                // redirect to the original page where the Cancel button was pressed, so use the $_SERVER['HTTP_REFERER'] variable
                try {
                    $url = new moodle_url($_SERVER['HTTP_REFERER'], array());
                    redirect($url);
                } catch (Exception $e) {
                    header( 'Location: '. $_SERVER['HTTP_REFERER'] ) ;
                }
            } elseif ($subscriptions_form->is_submitted()) {
                // $this->content->text .= '<br/><u>Submitted form</u><br/>';
                $subs_data = $subscriptions_form->get_data();
                if ( $subs_data ){
                    // store this data set
                    try {
                        $errors = $this->store_data($subs_data);
                    } catch (\Exception $exception){
                        $errors = new \stdClass();
                        $errors->messages[] = 'Error while attempting to save data '. $exception->getMessage();
                    }

                    // if there were any errors, display them
                    if(is_array($errors->messages)){
                        foreach ($errors->messages as $key => $errmsg ){
                            $this->content->text .= '<p>Error: '.$errmsg .'</p>';
                        }
                    }
                }
            }

            // add the contents of the form to the block
            $this->content->text .= $subscriptions_form->render();
        }
        // Finish and return contents
        return $this->content ;
    }

    /**
     * @param $dataset
     *
     * @return null|\stdClass
     */
    private function store_data( $dataset ){
        global $DB;
        $error = new stdClass();
        $userid = $dataset->glossary_userid;
        $glossaryid = $dataset->glossary_glossaryid;
        $fullsubkey     =   get_string('glossaryformfullelementname','block_glsubs');
        $newcat         =   get_string('glossaryformcategorieselementname','block_glsubs');
        $newconcept     =   get_string('glossaryformconceptselementname','block_glsubs');
        $arrData = (array) $dataset;
        try {
            foreach ( $arrData as $key => $value ){

                // if the data in the form is a glossary comment subscription instruction then
                if( $key === $fullsubkey ) {
                    if( $DB->record_exists( 'block_glsubs_glossaries_subs',array( 'userid' => $userid,'glossaryid' => $glossaryid ) ) ){
                        $oldrecord = $DB->get_record( 'block_glsubs_glossaries_subs',array( 'userid'=>$userid,'glossaryid' => $glossaryid ) );
                        $oldrecord->active = $value ;
                        $DB->update_record( 'block_glsubs_glossaries_subs' , $oldrecord , false);
                    } else {
                        $newrecord = new stdClass();
                        $newrecord->userid = $userid ;
                        $newrecord->glossaryid = $glossaryid ;
                        $newrecord->active = $value ;
                        $newrecord->newcategories = 0 ;
                        $newrecord->newentriesuncategorised = 0 ;
                        $newentryid = $DB->insert_record('block_glsubs_glossaries_subs',$newrecord, true , true ); // use bulk updates
                        if( ! $newentryid > 0 ){
                            $error->messages[] = "Cannot create new full subscription record for $userid on glossary $glossaryid ";
                        }
                    }
                    // $msg = 'glossaries table full subscription';
                    // if the data in the form is a new categories subscription instruction then
                } elseif ( $key === $newcat ){
                    if( $DB->record_exists('block_glsubs_glossaries_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid))){
                        $oldrecord = $DB->get_record('block_glsubs_glossaries_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid));
                        $oldrecord->newcategories = $value ;
                        $DB->update_record('block_glsubs_glossaries_subs',$oldrecord, false);
                    } else {
                        $newrecord = new stdClass();
                        $newrecord->userid = $userid ;
                        $newrecord->glossaryid = $glossaryid ;
                        $newrecord->active = 0 ;
                        $newrecord->newcategories = $value ;
                        $newrecord->newentriesuncategorised = 0 ;
                        $newentryid = $DB->insert_record('block_glsubs_glossaries_subs',$newrecord, true , true ); // use bulk updates
                        if( ! $newentryid > 0 ){
                            $error->messages[] = "Cannot create new new categories subscription record for $userid on glossary $glossaryid ";
                        }
                    }
                    // $msg = 'glossaries table new categories subscription';
                    // if the data in the form is a new uncategorised consepts sbscription instruction then
                } elseif( $key === $newconcept ){
                    if( $DB->record_exists('block_glsubs_glossaries_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid))){
                        $oldrecord = $DB->get_record('block_glsubs_glossaries_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid));
                        $oldrecord->newentriesuncategorised = $value ;
                        $DB->update_record('block_glsubs_glossaries_subs',$oldrecord, false);
                    } else {
                        $newrecord = new stdClass();
                        $newrecord->userid = $userid ;
                        $newrecord->glossaryid = $glossaryid ;
                        $newrecord->active = 0 ;
                        $newrecord->newcategories = 0 ;
                        $newrecord->newentriesuncategorised = $value ;
                        $newentryid = $DB->insert_record('block_glsubs_glossaries_subs',$newrecord, true , true ); // use bulk updates
                        if( ! $newentryid > 0 ){
                            $error->messages[] = "Cannot create new uncategorised concepts subscription record for $userid on glossary $glossaryid ";
                        }
                    }
                    // $msg = 'glossaries table new concepts no category subscription';
                    // if the data inthe form is a category subscription instruction then
                } elseif ( 0 === strpos( $key,'glossary_category') ){
//            } elseif ( substr($key,0,17) === 'glossary_category'){
                    $categoryid = (int) preg_replace ( '/[^0-9,.]/' , '' , $key ) ;

                    if($DB->record_exists('block_glsubs_categories_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid,'categoryid'=>$categoryid))){
                        $old_category_record = $DB->get_record('block_glsubs_categories_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid,'categoryid'=>$categoryid));
                        $old_category_record->active = $value ;
                        $DB->update_record('block_glsubs_categories_subs',$old_category_record, false);
                    } else {
                        $newrecord = new stdClass();
                        $newrecord->userid = $userid;
                        $newrecord->glossaryid = $glossaryid ;
                        $newrecord->categoryid = (int)preg_replace('/[^0-9,.]/', '', $key);
                        $newrecord->active = $value ;
                        $newentryid = $DB->insert_record('block_glsubs_categories_subs',$newrecord, true , true ); //use bulk updates
                        if( ! $newentryid > 0 ){
                            $error->messages[] = "Cannot create new category subscription record for $userid on glossary $glossaryid and category $key";
                        }
                    }
                    // $msg = "categories table $categoryid ";
                    // if the data in the form is a concept subscription instruction then
                } elseif ( 0 === strpos($key,'glossary_concept') ) {
//            } elseif ( substr($key,0,16) === 'glossary_concept') {
                    $conceptid = (int)preg_replace('/[^0-9,.]/', '', $key);
                    if($DB->record_exists('block_glsubs_concept_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid,'conceptid'=>$conceptid))){
                        $oldrecord = $DB->get_record('block_glsubs_concept_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid,'conceptid'=>$conceptid));
                        $oldrecord->conceptactive = $value;
                        $DB->update_record('block_glsubs_concept_subs',$oldrecord, false);
                    } else {
                        $newrecord = new stdClass();
                        $newrecord->userid = $userid ;
                        $newrecord->glossaryid = $glossaryid ;
                        $newrecord->conceptid = $conceptid ;
                        $newrecord->conceptactive = $value ;
                        $newrecord->commentsactive = 0 ;
                        $newentryid = $DB->insert_record('block_glsubs_concept_subs',$newrecord, true , true ); // use bulk updates
                        if( ! $newentryid > 0 ){
                            $error->messages[] = "Cannot create new concept subscription record for $userid on glossary $glossaryid and concept $key ";
                        }
                    }
                    // $msg = 'concepts table';
                    // if the data in the form is a concept comments subscription instruction then
                } elseif ( 0 === strpos($key , 'glossary_comment') ) {
//            } elseif ( substr($key ,0  ,16) === 'glossary_comment') {
                    $conceptid = (int)preg_replace('/[^0-9,.]/', '', $key);
                    if($DB->record_exists('block_glsubs_concept_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid,'conceptid'=>$conceptid))){
                        $oldrecord = $DB->get_record('block_glsubs_concept_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid,'conceptid'=>$conceptid));
                        $oldrecord->commentsactive = $value;
                        $DB->update_record('block_glsubs_concept_subs',$oldrecord, false);
                    } else {
                        $newrecord = new stdClass();
                        $newrecord->userid = $userid ;
                        $newrecord->glossaryid = $glossaryid ;
                        $newrecord->conceptid = $conceptid ;
                        $newrecord->conceptactive = 0 ;
                        $newrecord->commentsactive = $value ;
                        $newentryid = $DB->insert_record('block_glsubs_concept_subs',$newrecord, true , true ); // use bulk updates
                        if( ! $newentryid > 0 ){
                            $error->messages[] = "Cannot create new concept comments subscription record for $userid on glossary $glossaryid and concept $key ";
                        }
                    }
                    // $msg = 'concepts table for comments';
                    // if the data in the form is an author subscription instruction then
                } elseif ( 0 === strpos($key , 'glossary_author') ) {
//            } elseif ( substr($key ,0  ,15) === 'glossary_author') {
                    $authorid = (int)preg_replace('/[^0-9,.]/', '', $key);
                    if($DB->record_exists('block_glsubs_authors_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid,'authorid'=>$authorid))){
                        // $oldrecord = new stdClass();
                        $oldrecord = $DB->get_record('block_glsubs_authors_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid,'authorid'=>$authorid));
                        $oldrecord->active = $value ;
                        $DB->update_record('block_glsubs_authors_subs', $oldrecord, false);
                        // $msg = 'active author subscription';
                    } else {
                        $newrecord =  new stdClass();
                        $newrecord->userid = $userid ;
                        $newrecord->glossaryid = $glossaryid ;
                        $newrecord->authorid = $authorid ;
                        $newrecord->active = $value ;
                        $newentryid = $DB->insert_record('block_glsubs_authors_subs',$newrecord, true , true ); // use bulk updates
                        if( ! $newentryid > 0 ){
                            $error->messages[] = "Cannot create new author subscription record for $userid on glossary $glossaryid and author $authorid ";
                        }
                    }
                    // $msg = 'concepts table for authors';
                }
                // if there is a message to show then add it to the messages
                /*            if(! is_null($msg)){
                                $m[$key] = $msg . " $key = $value";
                                $msg = NULL;
                            }*/
            }
        } catch (\Exception $exception) {
            $error->messages[] = 'Error while attempting to store data '.$exception->getMessage();
        }

        // what to report at the end ?
        if(count($error->messages) > 0) {
            return $error;
        } else {
            return NULL;
        }
    }

    /**
     * @return bool
     */
    public function instance_allow_multiple() {
        return FALSE;
    }

    /**
     * This function is required by Moodle to overide the default inherited function and to return true
     * in order to be able to store and use relevant universal plugin settings.
     * If you need more fine grained settings such as user or role or other classification
     * you must provide a set of database structures and their associated business logic
     * @return bool
     */
    public function has_config()
    {
        return parent::has_config() || true;
    }
}