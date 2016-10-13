<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 27/09/2016
 * Time: 09:17
 */
// namespace moodle\blocks\glsubs;
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

// required for assignment of roles to the block
require_once($CFG->libdir.'/formslib.php');

class block_glsubs_form extends moodleform
{
    private $usersubscriptions;

    /**
     * Defining the Glossary Subscriptions Form
     * @throws \HTML_QuickForm_Error
     */
    public function definition()
    {
        global $DB , $COURSE , $USER , $OUTPUT , $_GET ;
        // get course information
        $course_info = get_fast_modinfo($COURSE);

        // get course module id
        $cmid = optional_param('id',0, PARAM_INT);
        $cm = $course_info->get_cm($cmid);

        // get the glossaryid for the database entries queries
        $glossaryid = $cm->instance;

        // Define subscription structures
        $this->usersubscriptions = new stdClass();
        $this->user = new stdClass();
        $this->usersubscriptions->full = new stdClass();
        $this->usersubscriptions->full->full = new stdClass();
        $this->usersubscriptions->full->fullnewcat = new stdClass();
        $this->usersubscriptions->full->fullnewconcept = new stdClass();
        $this->usersubscriptions->authors = new stdClass();
        $this->usersubscriptions->categories = new stdClass();
        $this->usersubscriptions->concepts = new stdClass();

        // store the user id and the glossary id
        $this->usersubscriptions->userid = $USER->id;
        $this->usersubscriptions->glossaryid = $glossaryid;

        // Initiate a Moodle QuickForm object
        $mform = & $this->_form;

        // add user id to the form
        $mform->addElement('hidden','glossary_userid',(int)$this->usersubscriptions->userid);
        $mform->setType('glossary_userid', PARAM_INT);

        // add glossary id to the form
        $mform->addElement('hidden','glossary_glossaryid',(int)$this->usersubscriptions->glossaryid);
        $mform->setType('glossary_glossaryid',PARAM_INT);

        // make a link to the user profile over a picture link
        $elementUrl = new moodle_url('/user/view.php',array('id'=>$USER->id,'course'=>$COURSE->id));
        $elementLink = html_writer::link($elementUrl, $OUTPUT->user_picture($USER, array('size'=>35) ) );
        $text = $elementLink ;

        // make a link to user profile over the user full name
        $elementLink = html_writer::link($elementUrl,(fullname($USER,true)));
        $text .= ' '.$elementLink;
        $mform->addElement('link','currentuserlink',get_string('formheader','block_glsubs') . ' ' . $elementLink,$elementLink);
        // $text .= get_string('formheader','block_glsubs');
        $mform->addElement('header','glossaryuserlinks', get_string('formheader','block_glsubs'),array());
        $mform->setType('glossaryuserlinks',PARAM_TEXT);

        // Get user active subscriptions // TBD
        $current_subscriptions = $this->get_user_subscriptions();

        // Add Full Glossary Subscription Choice and Pointer Link
        $elementUrl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid));
        $elementLink = html_writer::link($elementUrl,'&#9658;');
        $label = $elementLink . '&emsp;' . $this->usersubscriptions->full->full->desc;

        // add # of concepts for the glossary
        $this->usersubscriptions->full->full->allglossaryentries = $DB->count_records('glossary_entries',array('glossaryid'=>$glossaryid));
        $label .= " (". $this->usersubscriptions->full->full->allglossaryentries .")";

        // add the full subscription option on the form
        $mform->addElement('advcheckbox',$this->usersubscriptions->full->full->elementname,$label,'',array('group'=>1,'margin'=>'0'),array(0,1));
        // add the default value to an array for the final stage of the form creation
        $this->usersubscriptions->defaults[$this->usersubscriptions->full->full->elementname] = $this->usersubscriptions->full->full->sub;
        $mform->setType($this->usersubscriptions->full->full->elementname,PARAM_INT);


        // Add Glossary Subscription on New Categories
        // add # of current categories
        $label = '&emsp; &emsp;' . get_string('newcategoriessubscription','block_glsubs'). " (".$DB->count_records('glossary_categories',array('glossaryid'=>$glossaryid)).")";
        // add the new categories subscription option on the form

        $mform->addElement('advcheckbox',$this->usersubscriptions->full->fullnewcat->elementname,$label,'',array('group'=>1),array(0,1));
        // add the default value to an array for the final stage of the form creation
        $this->usersubscriptions->defaults[$this->usersubscriptions->full->fullnewcat->elementname] = $this->usersubscriptions->full->fullnewcat->sub ;
        $mform->setType($this->usersubscriptions->full->fullnewcat->elementname,PARAM_INT);
        $mform->disabledIf($this->usersubscriptions->full->fullnewcat->elementname,$this->usersubscriptions->full->full->elementname,'checked');

        // Add Glossary Subscription on New Entries without Categories
        // count the uncategorised entries of this glossary
        $this->usersubscriptions->full->full->categorisedentries = $DB->count_records_sql('select count(distinct entryid) entries from {glossary_entries_categories}  where categoryid in (select id from {glossary_categories} where glossaryid=:glossaryid)',array('glossaryid'=>$glossaryid));
        $elementUrl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'cat','hook'=>'-1'));
        $elementLink = html_writer::link($elementUrl,'&#9658; ');
        $label = $elementLink . '&emsp;';
        $label .= get_string('newuncategorisedconceptssubscription','block_glsubs')." (". ($this->usersubscriptions->full->full->allglossaryentries - $this->usersubscriptions->full->full->categorisedentries).")";

        // add the new concepts without category option on the form
        $mform->addElement('advcheckbox',$this->usersubscriptions->full->fullnewconcept->elementname,$label,'',array('group'=>1),array(0,1));
        // add the default value to an array for the final stage of the form creation
        $this->usersubscriptions->defaults[$this->usersubscriptions->full->fullnewconcept->elementname] = $this->usersubscriptions->full->fullnewconcept->sub;
        $mform->setType($this->usersubscriptions->full->fullnewconcept->elementname,PARAM_INT);
        $mform->disabledIf($this->usersubscriptions->full->fullnewconcept->elementname,$this->usersubscriptions->full->full->elementname,'checked');

        // Add Glossary Authors Subscriptions and links
        foreach($this->usersubscriptions->authors as $key => & $author_record){
            $author_record->fullname = fullname(\core_user::get_user($author_record->id)); ;//$authorfullname;
            $author_record->user = \core_user::get_user($author_record->id);
            $author_record->entries = $author_record->entries;
            $author_record->elementname = 'glossary_author_' . $key;
        }

        // Add header for authors
        $mform->addElement('header','glsubs_authors_header',get_string('glossaryauthors','block_glsubs'),array('font-weight'=>'bold'));

        // Add authors
        foreach ($this->usersubscriptions->authors as $key => $author ){
            // create a link with image to the author's profile
            $text = '';
            $userpicture = $OUTPUT->user_picture($author->user, array('size'=>35));
            $elementUrl = new moodle_url('/user/view.php', array('id' => $key));
            $elementLink = html_writer::link($elementUrl,$userpicture);
            $text .= $elementLink ." ";

            // create a link to the author's list of entries in this glossary
            $elementUrl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'author','sortkey'=>'FIRSTNAME','hook'=>$author->fullname));
            $elementLink = html_writer::link($elementUrl,'&#9658;');
            $text .= $elementLink;

            // create a checkbox for author subscription
            $mform->addElement( 'advcheckbox' , $author->elementname, $text.' '.$author->fullname  ." (".$DB->count_records('glossary_entries',array('userid'=>$author->id,'glossaryid'=>$glossaryid)).")" );
            $mform->setType($author->elementname, PARAM_INT);
            // add the default value to an array for the final stage of the form creation
            $this->usersubscriptions->defaults[$author->elementname] = $author->sub ;
            $mform->disabledIf($author->elementname,$this->usersubscriptions->full->full->elementname,'checked');
            $text = '';
        }

        // add link to all authors
        $elementUrl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'author','sortkey'=>'FIRSTNAME','hook'=>'ALL'));
        $elementLink = html_writer::link($elementUrl,'&#9658; '. get_string('glossaryallauthors','block_glsubs'));
        $mform->addElement('link','allauthorslink','&emsp;&emsp;'.$elementLink,$elementLink);

        // show glossary categories

        // Add Categories header
        $mform->addElement('header','glsubs_categories_header',get_string('glossarycategories','block_glsubs'),array('font-weight'=>'bold'));

        // Add categories
        foreach ($this->usersubscriptions->categories as $key => & $category_entry) {
            if (! isset($category_entry->entries)) {
                $category_entry->entries = 0;
            }

            // create a link to the author's list of entries in this glossary
            $elementUrl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'cat','hook'=>$key));
            $elementLink = html_writer::link($elementUrl,'&#9658;');
            $text .= $elementLink ;

            // create a checkbox for author subscription
            $mform->addElement( 'advcheckbox' , $category_entry->elementname , $text.'&emsp;'.$this->ellipsisString($category_entry->name, 25)." (".$DB->count_records("glossary_entries_categories",array('categoryid'=>$key)).")");
            $mform->setType($category_entry->elementname, PARAM_INT);
            // add the default value to an array for the final stage of the form creation
            $this->usersubscriptions->defaults[$category_entry->elementname] = $category_entry->sub ;
            $mform->disabledIf($category_entry->elementname,$this->usersubscriptions->full->full->elementname,'checked');
            $text = '';
        }

        // Show uncategorised category of entries
        $elementUrl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'cat','hook'=>'-1'));
        $elementLink = html_writer::link($elementUrl,'&#9658;&emsp;'. get_string('glossaryuncategorisedconcepts','block_glsubs')." (".($this->usersubscriptions->full->full->allglossaryentries - $this->usersubscriptions->full->full->categorisedentries).")");
        $text .= $elementLink  ;

        // create a link for all authors
        $mform->addElement( 'link' , 'category__none', $text, $elementLink );
        $text = '';

        // Show  Glossary Entries header
        $mform->addElement('header','glsubs_concepts_header',get_string('glossaryconcepts','block_glsubs'),array('font-weight'=>'bold'));

        // Add concepts and comments checkboxes along with links to their associated categories
        $loop = $this->usersubscriptions->concepts ;
        // $timers[] = microtime(true);
        $commentslabel = get_string('glossarycommentson','block_glsubs');
        foreach ($loop as $key => & $concept_entry) {
            $concept_entry->elementname = 'glossary_concept_' . $key ;
            $concept_entry->comment_elementname = 'glossary_comment_' . $key ;
            $entryurl = new moodle_url("/mod/glossary/view.php",array('id'=>$cmid,'mode'=>'entry','hook'=>$key));
            $entrylink = html_writer::link($entryurl,'&#9658;');

            // add concept checkbox
            $mform->addElement('advcheckbox',$concept_entry->elementname , $entrylink . '&emsp;' . $this->ellipsisString($concept_entry->concept, 20),array('group'=>10),array(0,1));

            // add the default value to an array for the final stage of the form creation
            $this->usersubscriptions->defaults[$concept_entry->elementname] = $concept_entry->conceptactive ;
            $mform->disabledIf($concept_entry->elementname,$this->usersubscriptions->full->full->elementname,'checked');

            // Add comments checkbox
            $mform->addElement('advcheckbox',$concept_entry->comment_elementname , $commentslabel .  $this->ellipsisString( $concept_entry->concept , 20 ) . " (". $concept_entry->commentscounter . ")",array('group'=>10),array(0,1));

            // add the default value to an array for the final stage of the form creation
            $this->usersubscriptions->defaults[$concept_entry->comment_elementname] = $concept_entry->commentsactive ;
            $mform->disabledIf($concept_entry->comment_elementname,$this->usersubscriptions->full->full->elementname,'checked');

            // get concept's categories
            if(isset($concept_entry->categories) && count($concept_entry->categories) > 0) {
                $linkstext = '';
                foreach($concept_entry->categories as $categorykey => & $categoryrecord ) {
                    $cat_name = $this->usersubscriptions->categories[$categoryrecord]->name ;
                    $elementUrl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'cat','hook'=>$categoryrecord));
                    $categorylink = html_writer::link($elementUrl,$cat_name);
                    $linkstext .= '&emsp;<i>'. $categorylink .'</i>';
                }

                // add links to categories if they exist
                $linkstext = "(" . count($concept_entry->categories) . ")" . $linkstext;
                $mform->addElement('link', 'concept_' . $key . '_categories', $linkstext);
            }
        }

        // set the defaults in bulk step to avoid time waste
        $mform->setDefaults($this->usersubscriptions->defaults);

        // add form control buttons
        $this->add_action_buttons(true,'Save');
    }

    /**
     * Get database recorded subscriptions for the current user
     * @return array
     */
    protected function get_user_subscriptions(){
        global $DB ;
        // full subscription defaults
        $this->usersubscriptions->full->full->sub = 0;
        $this->usersubscriptions->full->full->desc = get_string('fullsubscription', 'block_glsubs');
        $this->usersubscriptions->full->full->elementname = get_string('glossaryformfullelementname','block_glsubs') ;
        $this->usersubscriptions->full->fullnewcat->sub = 0;
        $this->usersubscriptions->full->fullnewcat->desc = get_string('newcategoriessubscription', 'block_glsubs');
        $this->usersubscriptions->full->fullnewcat->elementname = get_string('glossaryformcategorieselementname','block_glsubs') ;
        $this->usersubscriptions->full->fullnewconcept->sub = 0;
        $this->usersubscriptions->full->fullnewconcept->desc = get_string('newuncategorisedconceptssubscription', 'block_glsubs');
        $this->usersubscriptions->full->fullnewconcept->elementname = get_string('glossaryformconceptselementname','block_glsubs');

        // short name user and glossary id
        $userid = (int)$this->usersubscriptions->userid;
        $glossaryid = (int)$this->usersubscriptions->glossaryid;

        // check for current full glossary subscription database entry and update the current data to be presented
        if( $DB->record_exists('block_glsubs_glossaries_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid ) ) ){
            $record = $DB->get_record('block_glsubs_glossaries_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid));
            $this->usersubscriptions->full->full->sub = $record->active ;
            $this->usersubscriptions->full->fullnewcat->sub = $record->newcategories;
            $this->usersubscriptions->full->fullnewconcept->sub = $record->newentriesuncategorised;
        }

        // get Authors
        $this->usersubscriptions->authors = $DB->get_records_sql('SELECT userid id, count(userid) entries FROM {glossary_entries} WHERE glossaryid = ? GROUP BY userid', array($glossaryid));
        $this->usersubscriptions->authorsSubs = $DB->get_records('block_glsubs_authors_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid),'authorid','authorid,active');

        foreach($this->usersubscriptions->authors as $key => & $author_record) {
            if ( isset( $this->usersubscriptions->authorsSubs["$key"] ) ) {
                $author_record->sub = (int) $this->usersubscriptions->authorsSubs["$key"]->active ;
            } else {
                $author_record->sub = 0;
            }
        }
        // clean up used authors subscription data
        $this->usersubscriptions->authorsSubs = NULL ;

        // get Categories
        $this->usersubscriptions->categories = $DB->get_records('glossary_categories', array('glossaryid' => $glossaryid),'id','id,glossaryid,name');
        $this->usersubscriptions->categoriesSubs = $DB->get_records('block_glsubs_categories_subs',array('userid'=>$userid,'glossaryid'=>$glossaryid),'categoryid','categoryid,active');

        // convert entries values to integers
        foreach ($this->usersubscriptions->categories as $key =>& $category_entry) {
            $category_entry->elementname = 'glossary_category_' . $key ;
            if( isset( $this->usersubscriptions->categoriesSubs["$key"] ) ){
                $category_entry->sub = $this->usersubscriptions->categoriesSubs["$key"]->active ;
            } else {
                $category_entry->sub = 0 ;
            }
        }
        // remove used data
        $this->usersubscriptions->categoriesSubs = NULL ;

        // Add the Glossary concepts
        $this->usersubscriptions->concepts = $DB->get_records('glossary_entries', array('glossaryid' => $glossaryid),'id','id,concept');

        // get the concepts' categories set
        $this->usersubscriptions->conceptsCategories = $DB->get_records_select('glossary_entries_categories', 'categoryid IN (SELECT id FROM {glossary_categories} WHERE glossaryid = :glossaryid ) ', array('glossaryid'=>$glossaryid), $sort='entryid', $fields='id, entryid, categoryid', $limitfrom=0, $limitnum=0);

        // store the categories ID set into the concept
        foreach ( $this->usersubscriptions->conceptsCategories as $key => $conceptsCategory) {
            $this->usersubscriptions->concepts[(int) $conceptsCategory->entryid]->categories[] = (int)$conceptsCategory->categoryid;
        }
        // release memory of concept categories
        $this->usersubscriptions->conceptsCategories = NULL ;

        // get the user subscriptions for the glossary
        $this->usersubscriptions->conceptsSubs = $DB->get_records('block_glsubs_concept_subs',array('userid' => $userid, 'glossaryid' => $glossaryid),'conceptid','conceptid,conceptactive,commentsactive');

        // get concept comments counters
        $sqlstmt = 'SELECT itemid, count(itemid) counter FROM {comments} c JOIN {glossary_entries} ge ON c.itemid = ge.id AND ge.glossaryid = :glossaryid WHERE commentarea = "glossary_entry" GROUP BY itemid ORDER BY itemid';
        $this->usersubscriptions->conceptCounters = $DB->get_records_sql( $sqlstmt , array('glossaryid' => $glossaryid ));
//echo '';
        // add comment counters to relevant concepts
        foreach ($this->usersubscriptions->conceptCounters as $key => $conceptCounter) {
            $this->usersubscriptions->concepts[$key]->commentscounter = $conceptCounter->counter ;
        }
        foreach ($this->usersubscriptions->concepts as $key => & $concept) {
            if( ! isset( $concept->commentscounter ) ){
                $concept->commentscounter = 0 ;
            }
        }
        // release memory of counters
        $this->usersubscriptions->conceptCounters = NULL ;

        // prepare the presentation values for the subscriptions
        foreach ($this->usersubscriptions->concepts as $key => & $concept_entry) {
            // take the db subscription values for the concept and its comments
            if ( isset( $this->usersubscriptions->conceptsSubs[$key] ) ) {
                $concept_entry->conceptactive = (int)$this->usersubscriptions->conceptsSubs[$key]->conceptactive ;
                $concept_entry->commentsactive = (int)$this->usersubscriptions->conceptsSubs[$key]->commentsactive ;
            } else {
                $concept_entry->conceptactive = 0 ;
                $concept_entry->commentsactive = 0 ;
            }
        }
        $this->usersubscriptions->conceptsSubs = NULL ;
    }

    /**
     * This method is called after definition(), data submission and set_data().
     * @param array $data
     * @param array $files
     *
     * @return array
     */
    public function validation($data, $files){
        global $USER;
        $usercontext = context_user::instance($USER->id);
        $errors = parent::validation($data,$files);

        return $errors;
    }

    /**
     *
     */
    public function validate_after_data(){
        $mform = & $this->_form;
    }

    /**
     * This function is called only after a cancel or a submit event, never on new forms presented on screen
     */
    public function definition_after_data() {
        global $glsub_state ;
        $mform = $this->_form;
/*        if( $mform->is_cancelled() ){
            $someElement = $mform->getElement($this->usersubscriptions->full->full->elementname);
            $value = $someElement->getValue();
            // Do whatever checking you need
            $someOtherValue = 0 ;
            $someElement->setValue( $someOtherValue );
        } else */
         if ( $mform->isSubmitted()) {
             $e = method_exists ( $mform , 'isSubmitted') ;
//             $someElement = $mform->getElement($this->usersubscriptions->full->full->elementname);
//             $value = $someElement->getValue();
             // Do whatever checking you need
//             $someOtherValue = 1 ;
//             $someElement->setValue( $someOtherValue );
             // etc.
             //  add some new elements...
        }

    }
    /**
     * Reduce text to the maximum parameterised length
     * @param $text
     * @param $size
     *
     * @return string
     */
    protected function ellipsisString( $text , $size){
        $retstr = $text ;
        if($size < 1) {
            $size = 3;
        }
        if ($this->is_multibyte( $text )){
            if( mb_strlen( $text ) > $size ) {
                $retstr = mb_substr( $retstr , 0 , $size - 3 ) . '...';
            }
        } else {
            if(strlen( $text ) > $size) {
                $retstr = substr( $retstr , 0 , $size - 3 ) . '...';
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
}