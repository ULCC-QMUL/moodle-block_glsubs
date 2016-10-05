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
echo '<link rel="stylesheet" type="text/css" href="'.$CFG->wwwroot.'/blocks/glsubs/style/style.css" />';

class glsubs_form extends moodleform
{
    var $usersubscriptions;
    /**
     * Defining the Glossary Subscriptions Form
     */
    public function definition()
    {
        global $DB, $COURSE, $PAGE, $USER , $OUTPUT ;
        // get course information
        $course_info = get_fast_modinfo($COURSE);
        // get course module id
        $cmid = optional_param('id',0, PARAM_INT);
        $cm = $course_info->get_cm($cmid);
        // get the current page full URL
        // $page_url = $PAGE->url->get_path();
        // get the glossaryid for the database entries queries
        $glossaryid = $cm->instance;

        // Define subscription structures
        $this->usersubscriptions = new stdClass();
        $this->user = new stdClass();

        $this->usersubscriptions->full = new stdClass();
        $this->usersubscriptions->authors = new stdClass();
        $this->usersubscriptions->categories = new stdClass();
        $this->usersubscriptions->concepts = new stdClass();

        // full subscription defaults
        $this->usersubscriptions->full->full->sub = false;
        $this->usersubscriptions->full->full->desc = get_string('fullsubscription', 'block_glsubs');
        $this->usersubscriptions->full->fullnewcat->sub = false;
        $this->usersubscriptions->full->fullnewcat->desc = get_string('newcategoriessubscription', 'block_glsubs');
        $this->usersubscriptions->full->fullnewconcept->sub = false;
        $this->usersubscriptions->full->fullnewconcept->desc = get_string('newuncategorisedconceptssubscription', 'block_glsubs');

        // initiate a Moodle QuickForm object
        $mform = $this->_form;

        // make a link to the user profile over a picture link
        $userurl = new moodle_url('/user/view.php',array('id'=>$USER->id,'course'=>$COURSE->id));
        $userlink = html_writer::link($userurl, $OUTPUT->user_picture($USER, array('size'=>35) ) );
        $text = $userlink ;
        // make a link to user profile over the user full name
        $userlink = html_writer::link($userurl,(fullname($USER,true)));
        $text .= ' '.$userlink;
        $mform->addElement('link','currentuserlink',get_string('formheader','block_glsubs') . ' ' . $userlink,$userlink);
        // $text .= get_string('formheader','block_glsubs');
        $mform->addElement('header','glossaryuserlinks', get_string('formheader','block_glsubs'),array());
        $mform->setType('glossaryuserlinks',PARAM_TEXT);

        // Get user active subscriptions // TBD
        $current_subscriptions = $this->get_user_subscriptions();

        // Add Full Glossary Subscription Choice and Pointer Link
        $fullurl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid));
        $fulllink = html_writer::link($fullurl,'&#9658;');
        $label = $fulllink . '&emsp;' . $this->usersubscriptions->full->full->desc;
        // add # of concepts for the glossary
        $allglossaryentries = $DB->count_records('glossary_entries',array('glossaryid'=>$glossaryid));
        $label .= " (". $allglossaryentries .")";
        $mform->addElement('advcheckbox','glossary_full_subscription',$label,'',array('group'=>1,'margin'=>'0'),array(0,1));
        $mform->setDefault('glossary_full_subscription',0);

        // Add Glossary Subscription on New Categories
        // add # of current categories
        $label = '&emsp; &emsp;' . get_string('newcategoriessubscription','block_glsubs'). " (".$DB->count_records('glossary_categories',array('glossaryid'=>$glossaryid)).")";
        $mform->addElement('advcheckbox','glossary_full_newcategoriessubscription',$label,'',array('group'=>1),array(0,1));
        $mform->setDefault('glossary_full_newcategoriessubscription',0);

        // Add Glossary Subscription on New Entries without Categories
        // count the uncategorised entries of this glossary
        $categorisedentries = $DB->count_records_sql('select count(distinct entryid) entries from {glossary_entries_categories}  where categoryid in (select id from {glossary_categories} where glossaryid=:glossaryid)',array('glossaryid'=>$glossaryid));
        $userurl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'cat','hook'=>'-1'));
        $userlink = html_writer::link($userurl,'&#9658; ');

        $label = $userlink . '&emsp;';
        $label .= get_string('newuncategorisedconceptssubscription','block_glsubs')." (". ($allglossaryentries - $categorisedentries).")";
        $mform->addElement('advcheckbox','glossary_full_newuncategorisedconceptssubscription',$label,'',array('group'=>1),array(0,1));
        $mform->setDefault('glossary_full_newuncategorisedconceptssubscription',0);

        // Add Glossary Authors Subscriptions and links
        // get Authors
        $glossary_authors = $DB->get_records_sql('SELECT userid id, count(userid) entries FROM {glossary_entries} WHERE glossaryid = ? GROUP BY userid', array($glossaryid));
        foreach($glossary_authors as $key => & $record){
            $record->fullname = fullname(\core_user::get_user($record->id)); ;//$authorfullname;
            $record->user = \core_user::get_user($record->id);
            $record->entries = $record->entries;
        }

        // add header for authors
        $mform->addElement('header','glsubs_authors_header',get_string('glossaryauthors','block_glsubs'),array('font-weight'=>'bold'));
        foreach ($glossary_authors as $key => $author ){
            // create a link with image to the author's profile
            $text = "";
            $userpicture = $OUTPUT->user_picture($author->user, array('size'=>35));
            $userurl = new moodle_url('/user/view.php', array('id' => $key));
            $userlink = html_writer::link($userurl,$userpicture);
            $text .= $userlink ." ";

            // create a link to the author's list of entries in this glossary
            $userurl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'author','sortkey'=>'FIRSTNAME','hook'=>$author->fullname));
            $userlink = html_writer::link($userurl,'&#9658;');
            $text .= $userlink;

            // create a checkbox for author subscription
            $mform->addElement( 'advcheckbox' , 'glossary_author_'.$key, $text.' '.$author->fullname  ." (".$DB->count_records('glossary_entries',array('userid'=>$author->id,'glossaryid'=>$glossaryid)).")" );
            $text = "";
        }
        // add link to all authors
        $userurl =  new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'author','sortkey'=>'FIRSTNAME','hook'=>'ALL'));
        $userlink =  html_writer::link($userurl,'&#9658; '. get_string('glossaryallauthors','block_glsubs'));
        $mform->addElement('link','allauthorslink',$userlink,$userlink);


        // show glossary categories
        $glossary_categories = $DB->get_records('glossary_categories', array('glossaryid' => $glossaryid));
        $glossary_categories_entries = $DB->get_records_sql('SELECT categoryid, count(categoryid) entries FROM {glossary_entries_categories} WHERE categoryid in (SELECT id FROM {glossary_categories} WHERE glossaryid = ?) GROUP BY categoryid ', array($glossaryid));
        foreach ($glossary_categories_entries as $key => $value) {
            $glossary_categories[$key]->entries = (float)$value->entries;
        }
        $mform->addElement('header','glsubs_categories_header',get_string('glossarycategories','block_glsubs'),array('font-weight'=>'bold'));
        foreach ($glossary_categories as $key => $value) {
            if (!isset($value->entries)) {
                $glossary_categories[$key]->entries = 0;
            }

            // create a link to the author's list of entries in this glossary
            $userurl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'cat','hook'=>$key));
            $userlink = html_writer::link($userurl,'&#9658;');
            $text .= $userlink ;

            // create a checkbox for author subscription
            $mform->addElement( 'advcheckbox' , 'glossary_category_'.$key, $text.' '.$this->ellipsisString($value->name, 25)." (".$DB->count_records("glossary_entries_categories",array('categoryid'=>$key)).")");
            $text = "";
        }
        // Show uncategorised category of entries
        // /mod/glossary/view.php?id=573263&mode=cat&hook=-1
        $userurl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'cat','hook'=>'-1'));
        $userlink = html_writer::link($userurl,'&#9658; '. get_string('glossaryuncategorisedconcepts','block_glsubs')." (".($allglossaryentries - $categorisedentries).")");
        $text .= $userlink  ;

        // create a checkbox for author subscription
        $mform->addElement( 'link' , 'category__none', $text, $userlink );
        $text = "";

        // Show  Glossary Entries
        $mform->addElement('header','glsubs_concepts_header',get_string('glossaryconcepts','block_glsubs'),array('font-weight'=>'bold'));
        $glossaryentries = $DB->get_records('glossary_entries', array('glossaryid' => $glossaryid));
        foreach ($glossaryentries as $key => $entry) {
            $entryurl = new moodle_url("/mod/glossary/view.php",array('id'=>$cmid,'mode'=>'entry','hook'=>$key));
            $entrylink = html_writer::link($entryurl,'&#9658;');
            $mform->addElement('advcheckbox','glossary_concept_'.$key , $entrylink . '&emsp;' . $this->ellipsisString($entry->concept, 20));
            $mform->addElement('advcheckbox','glossary_comment_'.$key , get_string('glossarycommentson','block_glsubs') . $this->ellipsisString($entry->concept , 20) . " (".$DB->count_records('comments',array('itemid'=>$key,'commentarea'=>'glossary_entry')).")");
            // get entry's categories
            $linkstext = "";
            $entrycategories = $DB->get_records('glossary_entries_categories',array('entryid'=>$key));
            foreach($entrycategories as $categorykey => & $categoryrecord ) {
                $categoryrecord->description = $DB->get_record('glossary_categories', array('id' => $categoryrecord->categoryid));
                if( isset( $categoryrecord->description->name )){
                    $categorylink = html_writer::link($userurl,$categoryrecord->description->name);
                    $linkstext .= '&emsp;<i>'. $categorylink .'</i>';
                }
            }
            if(count($entrycategories) > 0) {
                $linkstext = "(" . count($entrycategories) . ")" . $linkstext;
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
        global $USER, $DB ;
        return array();
    }

    /**
     * This method is called after definition(), data submission and set_data().
     * @param array $data
     * @param array $files
     *
     * @return array
     */
    public function validation($data, $files){
//        return array();
    }
    public function validate_after_data(){

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