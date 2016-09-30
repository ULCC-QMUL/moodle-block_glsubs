<?php
// namespace moodle\blocks\glsubs;
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');
//require_once('../../config.php');
require_once('../../blocks/glsubs/classes/block_glsubs_form.php');

class block_glsubs extends block_base {
    /**
     * Glossary Subscriptions Block Frontend Controller Presenter
     * Sept 2016
     * QM+
     * vasileios
     */
    public function init(){
        $this->title = get_string('pluginname', 'block_glsubs');
    }

    /**
     * Reduce text to the maximum parameterised length
     * @param $text
     * @param $size
     *
     * @return string
     */
    protected function ellipsisString($text, $size){
        $retstr = $text ;
        if($size <1) {
            $size = 3;
        }
        if ($this->is_multibyte($text)){
            if(mb_strlen($text) > $size) {
                $retstr = mb_substr($retstr,0,$size-3) . '...';
            }
        } else {
            if(strlen($text) > $size) {
                $retstr = substr($retstr,0,$size-3) . '...';
            }
        }
        return $retstr;
    }
    /**
     * check for multibyte strings
     */
    protected function is_multibyte($s) {
        return mb_strlen($s,'utf-8') < strlen($s);
    }
    /**
     * Return the current page URL
     * @return string
     */
    private function curPageURL() {
        $pageURL = 'http';
        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
        $pageURL .= "://";
        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
        } else {
            $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
        }

        $pageurlparts = explode('?',$pageURL);
        $pageurlparts['protocol'] = explode('://',$pageurlparts[0])[0];
        // $pageurlparts['hostpath'] = explode('://',$pageurlparts[0])[1];
        $pageurlparts['host'] = explode('/',explode('://',$pageurlparts[0])[1])[0];
        $pageurlparts['pagepath'] = explode($pageurlparts['host'],explode('://',$pageurlparts[0])[1])[1];
        // $pageurlparts['parametervalues'] = explode('&',$pageurlparts[1]);
        foreach(explode('&',$pageurlparts[1]) as $key =>$parameterset ){
            $pageurlparts['parameters'][explode('=',$parameterset)[0]]= explode('=',$parameterset)[1] ;
        }
        $pageurlparts['fullurl'] = $pageURL;

        return $pageurlparts;
    }

    protected function getusersubs($user, $glossaryid ){
        global $DB ;
        $full_glossary_subscription = array('desc' => 'Full Subscription', 'enabled' => FALSE , 'newcategories'=>FALSE , 'newentriesuncategorised'=> FALSE );
        $usersubs = new stdClass();
        $usersubs->userid = $user->id;
        $usersubs->glossaryid = $glossaryid ;
        $usersubs->glossary     = $DB->get_record( 'block_glsubs_glossaries_subs', array('userid'=> $usersubs->userid , 'glossaryid' => $glossaryid));
        if(! $usersubs->glossary ){
            $usersubs->glossary = $full_glossary_subscription;
        }
        $usersubs->authors      = $DB->get_record( 'block_glsubs_authors_subs', array('userid'=> $usersubs->userid , 'glossaryid' => $glossaryid));
        $usersubs->categories   = $DB->get_record( 'block_glsubs_categories_subs', array('userid'=> $usersubs->userid , 'glossaryid' => $glossaryid));
        $usersubs->concepts     = $DB->get_record( 'block_glsubs_concept_subs', array('userid'=> $usersubs->userid , 'glossaryid' => $glossaryid));
        return $usersubs;
    }
    /**
     * Contents creation function
     * @return string
     */
    public function get_content()
    {
        global $USER, $PAGE, $COURSE, $DB, $SITE , $OUTPUT, $CFG; //, $THEME, $OUTPUT ;
        /** @var stdClass $this */
        if (isset($this->title)) {
            $this->title = get_string('blockheader','block_glsubs');
        }

        if($this->content !==  NULL) {
            return $this->content;
        }

        if( !isloggedin() or isguestuser() ){
            return '';
        }

        // get the current configuration
        require_once( __DIR__. '/../../config.php');

        // this is only for logged in users
        require_login();

        // $systemcontext = context_system::instance();

        // get the course id
        // $courseid = $COURSE->id;

        //$coursecontext = context_course::instance($courseid);

        // get the course details
        // $courseobject = $COURSE ; // $DB->get_record('course', array('id' => $courseid));
        // get the module information
        $courseinfo = get_fast_modinfo($COURSE);

        // prapare for contents
        $this->content = new stdClass;
        $this->content->text = "";
        $this->content->text .= '<strong>'.$PAGE->title . '</strong>';

/*        // make a link to the user profile over a picture link
        $userurl = new moodle_url('/user/view.php',array('id'=>$USER->id));
        $userlink = html_writer::link($userurl, $OUTPUT->user_picture($USER, array('size'=>35) ) );

        $this->content->text .= '<hr style="display: block!important;"/><strong>Subscriptions for User:</strong><br/>' . $userlink ;

        // make a link to user profile over the user full name
        $userlink = html_writer::link($userurl,(fullname($USER,true)));
        $this->content->text .= ' '.$userlink.'<br/>';*/

        // add a footer for the block
        $this->content->footer = '<hr style="display: block!important;"/><div style="text-align:center;">'.get_string('blockfooter','block_glsubs').'</div>';

        // $querystring = http_build_query($_GET);
        // get the id parameter if exists
        $cmid = optional_param('id', 0, PARAM_INT);
        // get the form submission parameter if submitted by the form
        $form_glossary_subs_submitted = optional_param('form_glossary_subs_submitted', 0, PARAM_INT);

        // Check if the page is referring to a glossary module view activity
        if( $cmid > 0 ){
            if( ! ( '/mod/glossary/view.php' == $PAGE->url->get_path() ) ){
                return $this->content ;
            }
        }
        // there is a valid glossary view page
        $PAGE->set_context(context_module::instance($cmid));
        $cm = $courseinfo->get_cm($cmid);

        // Check if the course module is available and it is visible and it is visible to the user and it is a glossary module
        if( ! ( TRUE == $cm->available && TRUE == $cm->visible && TRUE == $cm->uservisible && 'glossary' == $cm->modname ) ){
            return $this->content ;
        }
        // get the glossaryid for the database entries queries
        $glossaryid = $cm->instance;
        $test = $cm->url->get_path();

        // create a glossary subscriptions block form
        $action = $this->curPageURL()['fullurl'];
        $subscriptions_form =  new glsubs_form($action);
        // test for the form status , do kee the order of cancelled, submitted, new
        if($subscriptions_form->is_cancelled()){
            $this->content->text .= '<br/><u>Cancelled form</u><br/>';
        } elseif($subscriptions_form->is_submitted()){
            $this->content->text .= '<br/><u>Submitted form</u><br/>';
        } else {
            $this->content->text .= '<br/><u>New form</u><br/>';
        }
        // add the contents of the form to the block
        $this->content->text .= $subscriptions_form->render();

        // Show Glossary Settings HTML standard Post Form
        // $this->content->text .= 'Course: <strong>'. $courseid. ':' . $COURSE->fullname . '</strong><br>';
        // $this->content->text .= 'Module ID:'. $cmid  . '<br>';

        // define settings arrays for manipulation and display requirements
        $full_glossary_subscription = ['desc' => get_string('fullsubscription', 'block_glsubs'), 'enabled' => FALSE , 'newcategoriesdesc'=>get_string('newcategoriessubscription', 'block_glsubs') ,'newcategories'=>FALSE , 'newentriesuncategoriseddesc'=> get_string('newuncategorisedconceptssubscription', 'block_glsubs'), 'newentriesuncategorised'=> FALSE];
        $glossary_authors = array();
        $glossary_categories = array();
        $glossary_concepts = array();
        // get data setting values from the database tables
        $UserSubs = $this->getusersubs($USER, $glossaryid );

        $glossary_authors = $DB->get_records_sql('SELECT userid id, count(userid) entries FROM {glossary_entries} WHERE glossaryid = ? GROUP BY userid', array($glossaryid));
        foreach($glossary_authors as $key => $record){
            $glossary_authors[$key]->fullname = fullname(\core_user::get_user($record->id)); ;//$authorfullname;
            $glossary_authors[$key]->user = \core_user::get_user($record->id);
            $glossary_authors[$key]->entries = $record->entries;
        }
        // $author = NULL;
        // $authorfullname = NULL;
        // check if there is a posted form and update database records accordingly

        // update the settings arrays for the presentation layer

        // create content for the block
        // $this->content->text .= '<strong>'. $cm->name . '</strong><br/>';
        // $this->content->text .= '<br/>';

        // create a form for the settings
        $this->content->text .= '<form method="post" target="_top" name="form_glossary_'.$glossaryid.'">';
        // define a form submission identifier
        $this->content->text .= '<input type="hidden" name="form_glossary_subs_submitted" id="form_glossary_subs_submitted" value="1">';

        // Show full glossary subscription choice
/*        $checkbox = "";
        if($form_glossary_subs_submitted){
            if($_REQUEST["user_".$USER->id."_glossary_".$glossaryid] == "on" ) {
                $checkbox = "checked='checked'";
            }
        }
        $this->content->text .= '<input type="checkbox" name="user_'.$USER->id.'_glossary_'.$glossaryid.'" id="user_'.$USER->id.'_glossary_'.$glossaryid.'" '.$checkbox.'>';
        $this->content->text .= '<label for="user_'.$USER->id.'_glossary_'.$glossaryid.'">'. $full_glossary_subscription['desc'] .'</label>';
        $userurl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid));
        $userlink = html_writer::link($userurl,'&#9658;');
        $this->content->text .= $userlink;
        $this->content->text .= '<br/>';*/

/*        if($form_glossary_subs_submitted){
            if( "on" === $_REQUEST["user_".$USER->id."_glossary_".$glossaryid.'_new_category']  ) {
                $checkbox = "checked='checked'";
            }
        }
        $this->content->text .= '<input type="checkbox" name="user_'.$USER->id.'_glossary_'.$glossaryid.'_new_category" id="user_'.$USER->id.'_glossary_'.$glossaryid.'_new_category" '.$checkbox.'>';
        $this->content->text .= '<label for="user_'.$USER->id.'_glossary_'.$glossaryid.'_new_category">'. $full_glossary_subscription['newcategoriesdesc'] .'</label><br/>';*/
/*
        if($form_glossary_subs_submitted){
            if("on" === $_REQUEST["user_".$USER->id."_glossary_".$glossaryid.'_new_concept']) {
                $checkbox = "checked='checked'";
            }
        }
        $this->content->text .= '<input type="checkbox" name="user_'.$USER->id.'_glossary_'.$glossaryid.'_new_concept" id="user_'.$USER->id.'_glossary_'.$glossaryid.'_new_concept" '.$checkbox.'>';
        $this->content->text .= '<label for="user_'.$USER->id.'_glossary_'.$glossaryid.'_new_concept">'. $full_glossary_subscription['newentriesuncategoriseddesc'] .'</label><br/>';

 */       // $this->content->text .= 'Module Type:'. $cm->modname  . '<br>';
        // $this->content->text .= 'Module DB ID:'. $glossaryid  . '<br>';
        // $this->content->text .= 'Module availability:'. $cm->available  . '<br>';
        // $this->content->text .= 'Module visibility:'. $cm->visible  . '<br>';
        // $this->content->text .= 'Module user visibility:'. $cm->uservisible  . '<br>';

        // Show glossary authors
        $this->content->text .= '<strong>Glossary Authors</strong><br/>';
        foreach ($glossary_authors as $key => $author ){
            $checkbox = '';
            if( isset($_REQUEST["user_".$USER->id."_glossary_".$glossaryid."_author_".$key]) && $_REQUEST["user_".$USER->id."_glossary_".$glossaryid."_author_".$key] == "on"){
                $checkbox = "checked='checked'" ;
            }
            // create a link with image to the author's profile
            $userpicture = $OUTPUT->user_picture($author->user, array('size'=>35));
            $userurl = new moodle_url('/user/view.php', array('id' => $key));
            $userlink = html_writer::link($userurl,$userpicture);
            $this->content->text .= $userlink. ' ';
            // create a checkbox for author subscription

            $this->content->text .= '<input type="checkbox" name="user_'.$USER->id.'_glossary_'.$glossaryid.'_author_'.$key.'" id="user_'.$USER->id.'_glossary_'.$glossaryid.'_author_'.$key.'" '.$checkbox.'>';
            $this->content->text .= '<label title="'.$author->fullname.'" for="user_'.$USER->id.'_glossary_'.$glossaryid.'_author_'.$key.'">'. $this->ellipsisString($author->fullname,25) .' (' .$author->entries. ')</label>';

            // create a link to the author's list of entries in this glossary
            $userurl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'author','sortkey'=>'FIRSTNAME','hook'=>$author->fullname));
            $userlink = html_writer::link($userurl,'&#9658;');
            $this->content->text .= $userlink;
            $this->content->text .= '<br/>';
        }

        // Show Glossary Categories
        $glossary_categories = $DB->get_records('glossary_categories',array('glossaryid'=>$glossaryid));
        $glossary_categories_entries = $DB->get_records_sql('SELECT categoryid, count(categoryid) entries FROM {glossary_entries_categories} WHERE categoryid in (SELECT id FROM {glossary_categories} WHERE glossaryid = ?) GROUP BY categoryid ', array($glossaryid));
        foreach($glossary_categories_entries as $key => $value){
            $glossary_categories[$key]->entries = (float)$value->entries;
        }
        $this->content->text .= '<strong>Glossary Categories</strong><br/>';
        foreach($glossary_categories as $key => $value){
            if(! isset($value->entries)) {
                $glossary_categories[$key]->entries = 0;
            }

            $checkbox = '';
            if(isset($_REQUEST["user_".$USER->id."_glossary_".$glossaryid."_category_".$key]) && $_REQUEST["user_".$USER->id."_glossary_".$glossaryid."_category_".$key] == "on"){
                $checkbox = "checked='checked'" ;
            }
            $this->content->text .= '<input type="checkbox" name="user_'.$USER->id.'_glossary_'.$glossaryid.'_category_'.$key.'" id="user_'.$USER->id.'_glossary_'.$glossaryid.'_category_'.$key.'" '.$checkbox.'>';
            $this->content->text .= '<label title="'.$value->name.'" for="user_'.$USER->id.'_glossary_'.$glossaryid.'_category_'.$key.'">'. $this->ellipsisString($value->name,25) . ' (' . $value->entries. ')</label>';
            $this->content->text .= '<a href="/mod/glossary/view.php?id='.$cmid.'&mode=cat&hook='.$key.'">&#9658;</a><br/>';
        }

        // Show  Glossary Entries
        $this->content->text .= '<strong>Glossary Enries</strong><br/>';
        $this->content->text .= '[E]&ensp;[C] Conc<strong>E</strong>pts & <strong>C</strong>omments <br/>';
        $glossaryentries = $DB->get_records('glossary_entries',array('glossaryid'=>$glossaryid));
        foreach($glossaryentries as $key => $entry){
            $checkbox = '';
            if(isset($_REQUEST['user_'.$USER->id.'_glossary_'.$glossaryid.'_entry_'.$key]) && $_REQUEST['user_'.$USER->id.'_glossary_'.$glossaryid.'_entry_'.$key] == "on"){
                $checkbox = "checked='checked'" ;
            }
            $this->content->text .= '<input type="checkbox" name="user_'.$USER->id.'_glossary_'.$glossaryid.'_concept_'.$key.'" id="user_'.$USER->id.'_glossary_'.$glossaryid.'_concept_'.$key.'" '.$checkbox.'>';
            $checkbox = '';
            if(isset($_REQUEST['user_'.$USER->id.'_glossary_'.$glossaryid.'_concept_'.$key.'_comments']) && $_REQUEST['user_'.$USER->id.'_glossary_'.$glossaryid.'_concept_'.$key.'_comments'] == "on") {
                $checkbox = "checked='checked'";
            }
            $this->content->text .= '<input type="checkbox" name="user_'.$USER->id.'_glossary_'.$glossaryid.'_concept_'.$key.'_comments'.'" id="user_'.$USER->id.'_glossary_'.$glossaryid.'_concept_'.$key.'_comments'.'" '.$checkbox.'>';
            $this->content->text .= '<span title="'.$entry->concept.'">'. $this->ellipsisString($entry->concept,20) . '</span>';
            $this->content->text .= '<a style="" href="/mod/glossary/view.php?id='.$cmid.'&mode=entry&hook='.$key.'">&#9658;</a> <br/>';
        }

        $this->content->text .= '<br/><br/><input type="submit" name="Apply" id="form_glossary_submit">';
        $this->content->text .= '</form>';

        // Finish and return contents
        return $this->content ;

    }
}


