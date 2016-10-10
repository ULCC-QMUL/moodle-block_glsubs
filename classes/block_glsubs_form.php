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

        // full subscription defaults
        $this->usersubscriptions->userid = $USER->id;
        $this->usersubscriptions->glossaryid = $glossaryid;
        $this->usersubscriptions->full->full->sub = 0;
        $this->usersubscriptions->full->full->desc = get_string('fullsubscription', 'block_glsubs');
        $this->usersubscriptions->full->full->elementname = get_string('glossaryformfullelementname','block_glsubs') ;
        $this->usersubscriptions->full->fullnewcat->sub = 0;
        $this->usersubscriptions->full->fullnewcat->desc = get_string('newcategoriessubscription', 'block_glsubs');
        $this->usersubscriptions->full->fullnewcat->elementname = get_string('glossaryformcategorieselementname','block_glsubs') ;
        $this->usersubscriptions->full->fullnewconcept->sub = 0;
        $this->usersubscriptions->full->fullnewconcept->desc = get_string('newuncategorisedconceptssubscription', 'block_glsubs');
        $this->usersubscriptions->full->fullnewconcept->elementname = get_string('glossaryformconceptselementname','block_glsubs');

        // Initiate a Moodle QuickForm object
        $mform = & $this->_form;

        // add user id to the form
        $mform->addElement('hidden','glossary_userid',    (int)$this->usersubscriptions->userid);
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
        $mform->setDefault($this->usersubscriptions->full->full->elementname,$this->usersubscriptions->full->full->sub);
        $mform->setType($this->usersubscriptions->full->full->elementname,PARAM_INT);


        // Add Glossary Subscription on New Categories
        // add # of current categories
        $label = '&emsp; &emsp;' . get_string('newcategoriessubscription','block_glsubs'). " (".$DB->count_records('glossary_categories',array('glossaryid'=>$glossaryid)).")";
        // add the new categories subscription option on the form

        $mform->addElement('advcheckbox',$this->usersubscriptions->full->fullnewcat->elementname,$label,'',array('group'=>1),array(0,1));
        $mform->setDefault($this->usersubscriptions->full->fullnewcat->elementname,$this->usersubscriptions->full->fullnewcat->sub);
        $mform->setType($this->usersubscriptions->full->fullnewcat->elementname,PARAM_INT);

        // Add Glossary Subscription on New Entries without Categories
        // count the uncategorised entries of this glossary
        $this->usersubscriptions->full->full->categorisedentries = $DB->count_records_sql('select count(distinct entryid) entries from {glossary_entries_categories}  where categoryid in (select id from {glossary_categories} where glossaryid=:glossaryid)',array('glossaryid'=>$glossaryid));
        $elementUrl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'cat','hook'=>'-1'));
        $elementLink = html_writer::link($elementUrl,'&#9658; ');
        $label = $elementLink . '&emsp;';
        $label .= get_string('newuncategorisedconceptssubscription','block_glsubs')." (". ($this->usersubscriptions->full->full->allglossaryentries - $this->usersubscriptions->full->full->categorisedentries).")";

        // add the new concepts without category option on the form
        $mform->addElement('advcheckbox',$this->usersubscriptions->full->fullnewconcept->elementname,$label,'',array('group'=>1),array(0,1));
        $mform->setDefault($this->usersubscriptions->full->fullnewconcept->elementname,$this->usersubscriptions->full->fullnewconcept->sub);
        $mform->setType($this->usersubscriptions->full->fullnewconcept->elementname,PARAM_INT);

        // Add Glossary Authors Subscriptions and links
        foreach($this->usersubscriptions->authors as $key => & $author_record){
            $author_record->fullname = fullname(\core_user::get_user($author_record->id)); ;//$authorfullname;
            $author_record->user = \core_user::get_user($author_record->id);
            $author_record->entries = $author_record->entries;
            $author_record->elementname = 'glossary_author_' . $key;
            if($DB->record_exists('block_glsubs_authors_subs',array('userid'=>(int)$USER->id,'glossaryid'=>$glossaryid,'authorid'=>$key))){
                $dbrec = $DB->get_record('block_glsubs_authors_subs',array('userid'=>(int)$USER->id,'glossaryid'=>$glossaryid,'authorid'=>$key));
                $author_record->sub = $dbrec->active;
            } else {
                $author_record->sub = 0 ;
            }
        }

        // Add header for authors
        $mform->addElement('header','glsubs_authors_header',get_string('glossaryauthors','block_glsubs'),array('font-weight'=>'bold'));

        // Add authors
        foreach ($this->usersubscriptions->authors as $key => $author ){
            // create a link with image to the author's profile
            $text = "";
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
            $mform->setDefault($author->elementname,$author->sub);
            $text = "";
        }

        // add link to all authors
        $elementUrl =  new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'author','sortkey'=>'FIRSTNAME','hook'=>'ALL'));
        $elementLink =  html_writer::link($elementUrl,'&#9658; '. get_string('glossaryallauthors','block_glsubs'));
        $mform->addElement('link','allauthorslink','&emsp;&emsp;'.$elementLink,$elementLink);

        // show glossary categories

        // Add Categories header
        $mform->addElement('header','glsubs_categories_header',get_string('glossarycategories','block_glsubs'),array('font-weight'=>'bold'));

        // Add categories
        foreach ($this->usersubscriptions->categories as $key => & $category_entry) {
            if (!isset($category_entry->entries)) {
                $category_entry->entries = 0;
            }

            // create a link to the author's list of entries in this glossary
            $elementUrl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'cat','hook'=>$key));
            $elementLink = html_writer::link($elementUrl,'&#9658;');
            $text .= $elementLink ;

            // create a checkbox for author subscription
            $mform->addElement( 'advcheckbox' , $category_entry->elementname , $text.'&emsp;'.$this->ellipsisString($category_entry->name, 25)." (".$DB->count_records("glossary_entries_categories",array('categoryid'=>$key)).")");
            $mform->setType($category_entry->elementname, PARAM_INT);
            if($DB->record_exists('block_glsubs_categories_subs',array('userid'=>(int)$USER->id,'glossaryid'=>$glossaryid,'categoryid'=>$key))){
                $dbrec = $DB->get_record('block_glsubs_categories_subs',array('userid'=>(int)$USER->id,'glossaryid'=>$glossaryid,'categoryid'=>$key));
                $category_entry->sub = $dbrec->active;
            } else {
                $category_entry->sub = 0 ;
            }

            $mform->setDefault($category_entry->elementname,$category_entry->sub);
            $text = "";
        }

        // Show uncategorised category of entries
        $elementUrl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'cat','hook'=>'-1'));
        $elementLink = html_writer::link($elementUrl,'&#9658;&emsp;'. get_string('glossaryuncategorisedconcepts','block_glsubs')." (".($this->usersubscriptions->full->full->allglossaryentries - $this->usersubscriptions->full->full->categorisedentries).")");
        $text .= $elementLink  ;

        // create a link for all authors
        $mform->addElement( 'link' , 'category__none', $text, $elementLink );
        $text = "";

        // Show  Glossary Entries header
        $mform->addElement('header','glsubs_concepts_header',get_string('glossaryconcepts','block_glsubs'),array('font-weight'=>'bold'));

        // Add conceps and comments checkboxes along with links to their associated categories
        foreach ($this->usersubscriptions->concepts as $key => & $concept_entry) {
            $concept_entry->elementname = 'glossary_concept_' . $key ;
            $concept_entry->comment_elementname = 'glossary_comment_' . $key ;
            $entryurl = new moodle_url("/mod/glossary/view.php",array('id'=>$cmid,'mode'=>'entry','hook'=>$key));
            $entrylink = html_writer::link($entryurl,'&#9658;');

            // add concept checkbox
            $mform->addElement('advcheckbox',$concept_entry->elementname , $entrylink . '&emsp;' . $this->ellipsisString($concept_entry->concept, 20));
            $mform->setType($concept_entry->elementname,PARAM_INT);
            $mform->setDefault($concept_entry->elementname,0);

            // Add comments checkbox
            $mform->addElement('advcheckbox',$concept_entry->comment_elementname , get_string('glossarycommentson','block_glsubs') . $this->ellipsisString($concept_entry->concept , 20) . " (".$DB->count_records('comments',array('itemid'=>$key,'commentarea'=>'glossary_entry')).")");
            $mform->setType($concept_entry->comment_elementname,PARAM_INT);
            $mform->setDefault($concept_entry->comment_elementname,0);

            // get entry's categories
            $linkstext = "";
            $concept_entry->categories = $DB->get_records('glossary_entries_categories',array('entryid'=>$key));
            foreach($concept_entry->categories as $categorykey => & $categoryrecord ) {
                $categoryrecord->description = $DB->get_record('glossary_categories', array('id' => $categoryrecord->categoryid));
                if( isset( $categoryrecord->description->name )){
                    $categorylink = html_writer::link($elementUrl,$categoryrecord->description->name);
                    $linkstext .= '&emsp;<i>'. $categorylink .'</i>';
                }
            }
            if(count($concept_entry->categories) > 0) {
                $linkstext = "(" . count($concept_entry->categories) . ")" . $linkstext;
                $mform->addElement('link', 'concept_' . $key . '_categories', $linkstext);
            }
        }

        // add form control buttons
        $this->add_action_buttons(true,'Save');
    }

    /**
     * Get database recorded subscriptions for the current user
     * @return array
     */
    protected function get_user_subscriptions(){
        global $DB ;
//        $userid = $this->usersubscriptions->userid;
        $glossaryid = $this->usersubscriptions->glossaryid;
        if($DB->record_exists('block_glsubs_glossaries_subs',array('userid'=>$this->usersubscriptions->userid,'glossaryid'=>$this->usersubscriptions->glossaryid))){
            $record = $DB->get_record('block_glsubs_glossaries_subs',array('userid'=>$this->usersubscriptions->userid,'glossaryid'=>$this->usersubscriptions->glossaryid));
        }
        if( $record ){
            $this->usersubscriptions->full->full->sub = $record->active ;
            $this->usersubscriptions->full->fullnewcat->sub = $record->newcategories;
            $this->usersubscriptions->full->fullnewconcept->sub = $record->newentriesuncategorised;
        }
        // get Authors
        $this->usersubscriptions->authors = $DB->get_records_sql('SELECT userid id, count(userid) entries FROM {glossary_entries} WHERE glossaryid = ? GROUP BY userid', array($glossaryid));


        // get Categories
        $this->usersubscriptions->categories = $DB->get_records('glossary_categories', array('glossaryid' => $glossaryid));

        $glossary_categories_entries = $DB->get_records_sql('SELECT categoryid, count(categoryid) entries FROM {glossary_entries_categories} WHERE categoryid in (SELECT id FROM {glossary_categories} WHERE glossaryid = ?) GROUP BY categoryid ', array($glossaryid));

        // convert entries values to integers
        foreach ($glossary_categories_entries as $key => $category_entry) {
            $this->usersubscriptions->categories[$key]->entries = (float)$category_entry->entries;
            $this->usersubscriptions->categories[$key]->elementname = 'glossary_category_' . $key ;
        }

        // add Concepts
        $this->usersubscriptions->concepts = $DB->get_records('glossary_entries', array('glossaryid' => $glossaryid));
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
}