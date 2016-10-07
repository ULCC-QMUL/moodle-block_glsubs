<?php
// namespace moodle\blocks\glsubs;
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');
//require_once('../../config.php');
include_once($CFG->dirroot.'/blocks/glsubs/classes/block_glsubs_form.php');

class block_glsubs extends block_base {
    protected $glsub_state;
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

    /**
     * Subscriptions Block Contents creation function
     * @return string
     */
    public function get_content()
    {
        /** @var stdClass $this */
        // define usage of global variables
        global $PAGE, $COURSE ; // $USER, $DB, $SITE , $OUTPUT, $CFG, $THEME, $OUTPUT ;

        if (isset($this->title)) {
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

        // $querystring = http_build_query($_GET);
        // get the id parameter if exists
        $cmid = optional_param('id', 0, PARAM_INT);

        // check if there is a valid glossary view page
        if( $cmid > 0 ) {
            // Check if the page is referring to a glossary module view activity
            if( ! ( '/mod/glossary/view.php' == $PAGE->url->get_path() ) ){
                return $this->content ;
            }
            $PAGE->set_context(context_module::instance($cmid));
            $cm = $courseinfo->get_cm($cmid);

            // Check if the course module is available and it is visible and it is visible to the user and it is a glossary module
            if (!(TRUE == $cm->available && TRUE == $cm->visible && TRUE == $cm->uservisible && 'glossary' == $cm->modname)) {
                return $this->content;
            }
            // get the glossaryid for the database entries queries
            $glossaryid = $cm->instance;
            $test = $cm->url->get_path();

            // create a glossary subscriptions block form and assign its action to the original page
            $subscriptions_form = new block_glsubs_form($this->curPageURL()['fullurl']);
            // test for the form status , do kee the order of cancelled, submitted, new
            if ($subscriptions_form->is_cancelled()) {
                $this->content->text .= '<br/><u>Cancelled form</u><br/>';
                // flag the state
//                $subscriptions_form->glsub_state = 'Cancelled';
                // redirect to the original page where the cancel button was pressed, so use the $_SERVER['HTTP_REFERER'] variable
                $url = new moodle_url($_SERVER['HTTP_REFERER'],array());
                redirect($url);
            } elseif ($subscriptions_form->is_submitted()) {
                $this->content->text .= '<br/><u>Submitted form</u><br/>';
                if ($subs_data = $subscriptions_form->get_data()){
                    $this->store_data($subs_data);
                }
                // store this data set

                // flag the state
//                $subscriptions_form->glsub_state = 'Submitted';
            } else {
                $this->content->text .= '<br/><u>New form</u><br/>';
                $subscriptions_form->glsub_state = 'New';
            }
            // add the contents of the form to the block
            $this->content->text .= $subscriptions_form->render();

        }
        // Finish and return contents
        return $this->content ;

    }

    private function store_data($dataset){
        global $DB;
        $userid = $dataset->glossary_userid;
        $glossaryid = $dataset->glossary_glossaryid;
        $fullsubkey =   (string)get_string('glossaryformfullelementname','block_glsubs');
        $newcat =       get_string('glossaryformcategorieselementname','block_glsubs');
        $newconcept =   get_string('glossaryformconceptselementname','block_glsubs');
        $arrData = (array)$dataset;
        foreach ( $arrData as $key => $value ){
            $k = (string)$key;
            $v = $value;
            if ( substr($k ,0 ,16) == "glossary_comment") {
                $msg = 'concepts table for comments';
            } elseif( $k === $fullsubkey ) {
                $msg = 'glossaries table full subscription';
            } elseif ( $k === $newcat ){
                $msg = 'glossaries table new categories subscription';
            } elseif( $k === $newconcept ){
                $msg = 'glossaries table new concepts no category subscription';
            } elseif ( substr($k,0,17) === "glossary_category" ){
                $msg = 'categories table';
            } elseif ( substr($k,0,16) === "glossary_concept") {
                $msg = 'concepts table';
            } elseif ( substr($k ,0  ,16) === "glossary_comment") {
                $msg = 'concepts table for comments';
            }
            if(! is_null($msg)){
                $m[$k] = $msg . " $key = $value";
                $msg = NULL;
            }

        }
        return $m;
    }

}