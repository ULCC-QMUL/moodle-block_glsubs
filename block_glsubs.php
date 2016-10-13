<?php
// namespace moodle\blocks\glsubs;
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');
//require_once('../../config.php');
include_once($CFG->dirroot.'/blocks/glsubs/classes/block_glsubs_form.php');

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
     * Return the current page URL
     * @return string
     */
    private function currentPageURL() {
        $pageURL = 'http';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {$pageURL .= 's';}
        $pageURL .= '://';
        if ($_SERVER['SERVER_PORT'] !== '80') {
            $pageURL .= $_SERVER['SERVER_NAME']. ':' .$_SERVER['SERVER_PORT'].$_SERVER['REQUEST_URI'];
        } else {
            $pageURL .= $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
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

    /**
     * Subscriptions Block Contents creation function
     * @return string
     */
    public function get_content()
    {
        /** @var stdClass $this */
        // define usage of global variables
        global $PAGE, $COURSE ; // $USER, $DB, $SITE , $OUTPUT, $CFG, $THEME, $OUTPUT ;

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
        require_once( __DIR__. '/../../config.php');

        // this is only for logged in users
        require_login();

        // get the module information
        $courseinfo = get_fast_modinfo($COURSE);

        // prapare for contents
        $this->content = new stdClass;
        $this->content->text = "";
        $this->content->text .= '<strong>'.$PAGE->title . '</strong>';

        // add a footer for the block
        $this->content->footer = '<hr style="display: block!important;"/><div style="text-align:center;">'.get_string('blockfooter','block_glsubs').'</div>';

        // get the id parameter if exists
        $cmid = optional_param('id', 0, PARAM_INT);

        // check if there is a valid glossary view page
        if( $cmid > 0 ) {
            // Check if the page is referring to a glossary module view activity
            if( ! ( '/mod/glossary/view.php' === $PAGE->url->get_path() ) ){
                return $this->content ;
            }
            $PAGE->set_context(context_module::instance($cmid));
            if( $courseinfo->get_cm($cmid) ) {
                $cm = $courseinfo->get_cm($cmid);
            } else {
                return $this->content ;
            }

            // Check if the course module is available and it is visible and it is visible to the user and it is a glossary module
            if (!(TRUE == $cm->available && TRUE == $cm->visible && TRUE == $cm->uservisible && 'glossary' === $cm->modname)) {
                return $this->content;
            }

            // create a glossary subscriptions block form and assign its action to the original page
            $subscriptions_form = new block_glsubs_form($this->currentPageURL()['fullurl']);
            // test for the form status , do kee the order of cancelled, submitted, new
            if ($subscriptions_form->is_cancelled()) {
                // redirect to the original page where the Cancel button was pressed, so use the $_SERVER['HTTP_REFERER'] variable
                $url = new moodle_url($_SERVER['HTTP_REFERER'],array());
                redirect($url);
            } elseif ($subscriptions_form->is_submitted()) {
                // $this->content->text .= '<br/><u>Submitted form</u><br/>';
                $subs_data = $subscriptions_form->get_data();
                if ( $subs_data ){
                    // store this data set
                    $errors = $this->store_data($subs_data);
                    // if there were any errors, display them
                    if($errors->messages){
                        foreach ($errors->messages as $key => $errmsg ){
                            $this->content->text .= '<p>Error: '.$errmsg .'</p>';
                        }
                    }
                }
            } /*else {
                // $this->content->text .= '<br/><u>New form</u><br/>';
                $subscriptions_form->glsub_state = 'New'; // not used, just to state the condition of the flow
            }*/
            // add the contents of the form to the block
            $this->content->text .= $subscriptions_form->render();
        }
        // Finish and return contents
        return $this->content ;
    }

    private function store_data($dataset){
        global $DB;
        $error = new stdClass();
        $userid = $dataset->glossary_userid;
        $glossaryid = $dataset->glossary_glossaryid;
        $fullsubkey     =   get_string('glossaryformfullelementname','block_glsubs');
        $newcat         =   get_string('glossaryformcategorieselementname','block_glsubs');
        $newconcept     =   get_string('glossaryformconceptselementname','block_glsubs');
        $arrData = (array)$dataset;
        foreach ( $arrData as $key => $value ){

            // if the data in the form is a gossary comment subscription instruction then
            if( $key === $fullsubkey ) {
                if( $DB->record_exists('block_glsubs_glossaries_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid))){
                    $oldrecord = $DB->get_record('block_glsubs_glossaries_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid));
                    $oldrecord->active = $value ;
                    $DB->update_record('block_glsubs_glossaries_subs',$oldrecord, false);
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
            } elseif ( substr($key,0,17) === 'glossary_category'){
                $categoryid = (int)preg_replace('/[^0-9,.]/', '', $key);
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
            } elseif ( substr($key,0,16) === 'glossary_concept') {
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
            } elseif ( substr($key ,0  ,16) === 'glossary_comment') {
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
            } elseif ( substr($key ,0  ,15) === 'glossary_author') {
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
        if(count($error->messages) > 0) {
            return $error;
        } else {
            return NULL;
        }
    }

}