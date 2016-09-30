<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 27/09/2016
 * Time: 09:17
 */
// namespace moodle\blocks\glsubs;
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');
//require_once('../../config.php');
// required for assignment of roles to the block
require_once($CFG->libdir.'/formslib.php');
echo '<link rel="stylesheet" type="text/css" href="'.$CFG->wwwroot.'/blocks/glsubs/style/block_glsubs_form_styles.css" />';

class glsubs_form extends moodleform
{
    /**
     * Defining the Glossary Subscriptions Form
     */
    public function definition()
    {
        global $DB, $COURSE, $PAGE, $USER , $OUTPUT ; // $CFG,
        // get course information
        $course_info = get_fast_modinfo($COURSE);
        // get course module id
        $cmid = optional_param('id',0, PARAM_INT);
        $cm = $course_info->get_cm($cmid);
        // get the current page full URL
        $page_url = $PAGE->url->get_path();
        // get the glossaryid for the database entries queries
        $glossaryid = $cm->instance;

        // Define arrays to use for storing the subscription data
        $full_glossary_subscription = ['desc' => get_string('fullsubscription', 'block_glsubs'), 'enabled' => FALSE , 'newcategoriesdesc' => get_string('newcategoriessubscription', 'block_glsubs') ,'newcategories' => FALSE , 'newentriesuncategoriseddesc'=> get_string('newuncategorisedconceptssubscription', 'block_glsubs'), 'newentriesuncategorised' => FALSE ];
        $glossary_authors = array();
        $glossary_categories = array();
        $glossary_concepts = array();
        $mform = $this->_form;

        // make a link to the user profile over a picture link
        $userurl = new moodle_url('/user/view.php',array('id'=>$USER->id));
        $userlink = html_writer::link($userurl, $OUTPUT->user_picture($USER, array('size'=>35) ) );
        $text = $userlink ;
        // make a link to user profile over the user full name
        $userlink = html_writer::link($userurl,(fullname($USER,true)));
        $text .= ' '.$userlink.'<br/>';
        // $text .= get_string('formheader','block_glsubs');
        $mform->addElement('header','glossaryuserlinks',$text,array('text-align'=>'left!important'));
        $mform->setType('glossaryuserlinks',PARAM_TEXT);

        // Get user active subscriptions
        $current_subscriptions = $this->get_user_subscriptions();

        // Add Full Glossary Subscription Choice and Pointer Link
        $fullurl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid));
        $fulllink = html_writer::link($fullurl,'&#9658;');
        $label = $fulllink . '&emsp;' . get_string('fullsubscription','block_glsubs');
        $mform->addElement('advcheckbox','fullsubscription',$label,'',array('group'=>1,'margin'=>'0'),array(0,1));
        $mform->setDefault('fullsubscription',0);

        // Add Glossary Subscription on New Categories
        $label = get_string('newcategoriessubscription','block_glsubs');
        $mform->addElement('advcheckbox','newcategoriessubscription',$label,'',array('group'=>1),array(0,1));
        $mform->setDefault('newcategoriessubscription',0);

        // Add Glossary Subscription on New Entries without Categories
        $label = get_string('newuncategorisedconceptssubscription','block_glsubs');
        $mform->addElement('advcheckbox','newuncategorisedconceptssubscription',$label,'',array('group'=>1),array(0,1));
        $mform->setDefault('newuncategorisedconceptssubscription',0);

        // Add Glossary Authors Subscriptions and links
        $glossary_authors = $DB->get_records_sql('SELECT userid id, count(userid) entries FROM {glossary_entries} WHERE glossaryid = ? GROUP BY userid', array($glossaryid));
        foreach($glossary_authors as $key => & $record){
            $record->fullname = fullname(\core_user::get_user($record->id)); ;//$authorfullname;
            $record->user = \core_user::get_user($record->id);
            $record->entries = $record->entries;
//            $glossary_authors[$key]->fullname = fullname(\core_user::get_user($record->id)); ;//$authorfullname;
//            $glossary_authors[$key]->user = \core_user::get_user($record->id);
//            $glossary_authors[$key]->entries = $record->entries;
        }

        $mform->addElement('header','glsubs_authors_header','Glossary Authors',array('font-weight'=>'bold'));
        foreach ($glossary_authors as $key => $author ){
//            $checkbox = "";
//            if($_REQUEST["user_".$USER->id."_glossary_".$glossaryid."_author_".$key] == "on"){
//                $checkbox = "checked='checked'" ;
//            }
            // create a link with image to the author's profile
            $text = "";
            $userurl = "";
            $userlink = "";
            $userpicture = $OUTPUT->user_picture($author->user, array('size'=>35));
            $userurl = new moodle_url('/user/view.php', array('id' => $key));
            $userlink = html_writer::link($userurl,$userpicture);
            $text .= $userlink ." ";

            // create a link to the author's list of entries in this glossary
            $userurl = "";
            $userlink = "";
            $userurl = new moodle_url('/mod/glossary/view.php',array('id'=>$cmid,'mode'=>'author','sortkey'=>'FIRSTNAME','hook'=>$author->fullname));
            $userlink = html_writer::link($userurl,'&#9658;');
            $text .= $userlink ;

//            $this->content->text .= $userlink. ' ';
            // create a checkbox for author subscription
            $mform->addElement( 'advcheckbox' , 'author_'.$key, $text.' '.$author->fullname);
//            $this->content->text .= '<input type="checkbox" name="user_'.$USER->id.'_glossary_'.$glossaryid.'_author_'.$key.'" id="user_'.$USER->id.'_glossary_'.$glossaryid.'_author_'.$key.'" '.$checkbox.'>';
//            $this->content->text .= '<label title="'.$author->fullname.'" for="user_'.$USER->id.'_glossary_'.$glossaryid.'_author_'.$key.'">'. $this->ellipsisString($author->fullname,25) .' (' .$author->entries. ')</label>';

//            $this->content->text .= '<br/>';
        }


        // add form control buttons
//        $mform->addElement('submit','form_submit_button','Submit',array('class'=>'submitclass')); // alternative save only
        $this->add_action_buttons(true,'Save');
/*        $buttonarray=array();
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        $buttonarray[] = &$mform->createElement('reset', 'resetbutton', get_string('revert'));
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);*/
//        $mform->closeHeaderBefore('buttonar');
//        $mform->addElement('text','id',$cmid,'Glossary');
        // return $mform;

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

}