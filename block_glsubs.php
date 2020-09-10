<?php
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

/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 28/10/2016
 * Time: 15:03
 *
 * File:     blocks/glsubs/block_glsubs.php
 *
 * Purpose:  A glossary subscriptions block plugin for Moodle
 *
 * Input:    N/A
 *
 * Output:   N/A
 *
 * Notes:    This is developed for the QM+ Moodle site of the Queen Mary university of London
 *
 * This file is part of Moodle - http://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');
require_once(__DIR__ . '/../../config.php');
global $CFG;
require_once($CFG->dirroot . '/blocks/glsubs/classes/block_glsubs_form.php');

/** @noinspection PhpIllegalPsrClassPathInspection */

class block_glsubs extends block_base {
    /**
     * Glossary Subscriptions Block Frontend Controller Presenter
     * Sept 2016
     * QM+
     * vasileios
     */

    public static $olderrorhandler;

    /**
     * Method           init
     *
     * Purpose          initalise the class, set its title
     *
     * Parameters            N/A
     *
     * Returns           N/A
     *
     * Notes            Method must be declared, as it is defined abstract
     *                  title should be defined as it used by the Moodle system for the administration pages
     */
    public function init() {
        // Set the title of this plugin.
        $this->title = get_string('pluginname', 'block_glsubs');
    }

    /**
     * Method               exception_error_handler
     *
     * Purpose              Used for debugging, capturing exceptions and throwing errors
     *                      It might not be compatible for the PHP 7 version, as it uses a Throwable parameter
     *
     * Parameters
     *
     * @param               $errno   ,  error number
     * @param               $errstr  , error message
     * @param               $errfile , error file
     * @param               $errline , error code line
     *
     * Returns              N/A
     *
     * Notes                Method must be declared, as it is defined abstract
     *                      title should be defined as it used by the Moodle system for the administration pages
     *
     *
     * @throws              ErrorException
     */
    public static function exception_error_handler($errno, $errstr, $errfile, $errline) {
        self::$olderrorhandler = set_error_handler(self::$olderrorhandler);
        throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
    }

    /**
     * Method               applicable_formats
     *
     * Purpose              Set the default block configuration of on which type of pages to be viewable
     *
     * Parameters           N/A
     *
     * Returns             array('all' => true)
     *
     * @return array
     * Notes                make this plugin available to all pages
     *                      avoid the configuration step of making it available to all glossary view pages manually
     */
    public function applicable_formats() {
        return array('all' => true);
    }

    /**
     * Method               currentPageURL
     *
     * Purpose              Get the glossary view page URL with its parameters, to use it as the form action target
     *                      Used to associate the current page with the form action target,
     *                      so it returns to the same page after submit
     *
     * Parameters           N/A
     *
     * Returns              Return the current page URL
     *
     * @return              string Get URL of the glossary view page
     *
     * Notes                It is the safest method to set the plugin form action target ,
     *                      so after submission, it will return to the same page
     *
     */
    private function currentpageurl() {
        $pageurl = 'http';
        if (array_key_exists('HTTPS', $_SERVER) && $_SERVER['HTTPS'] === 'on') {
            $pageurl .= 's';
        }
        $pageurl .= '://';
        if ($_SERVER['SERVER_PORT'] !== '80') {
            $pageurl .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
        } else {
            $pageurl .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        }

        $pageurlparts = explode('?', $pageurl);
        $pageurlparts['protocol'] = explode('://', $pageurlparts[0])[0];
        $pageurlparts['host'] = explode('/', explode('://', $pageurlparts[0])[1])[0];
        $pageurlparts['pagepath'] = explode($pageurlparts['host'], explode('://', $pageurlparts[0])[1])[1];
        if (isset($pageurlparts[1])) {
            foreach (explode('&', $pageurlparts[1]) as $key => $parameterset) {
                $keyvaluearray = explode('=', $parameterset);
                if (array_key_exists(0, $keyvaluearray)) {
                    $param = $keyvaluearray[0];
                } else {
                    $param = '';
                }
                if (array_key_exists(1, $keyvaluearray)) {
                    $value = $keyvaluearray[1];
                } else {
                    $value = '';
                }
                $pageurlparts['parameters'][$param] = $value;
            }
        }
        if (count($_REQUEST) > 0) {
            foreach ($_REQUEST as $param => $value) {
                $pageurlparts['parameters'][$param] = $value;
            }
        }

        $pageurlparts['fullurl'] = $pageurl;

        return $pageurlparts;
    }

    /**
     * Method               get_latest_messages
     *
     * Purpose              Get a batch of the latest messages for the user, to show at the top of the block
     *
     * Parameters
     *
     * @param               $glossaryid , the glossary id to identify the relevant messages for the user
     *
     * Returns
     *
     * @return              array , of message objects
     */
    protected function get_latest_messages($glossaryid) {
        global $DB, $USER;
        $messages = array();
        $glsubssettings = get_config('block_glsubs');
        $messagescount = (int)$glsubssettings->messagestoshow;
        try {
            $sql = 'SELECT l.id , l.userid , l.eventlogid , l.timecreated , l.timedelivered
FROM {block_glsubs_messages_log} l
JOIN {block_glsubs_event_subs_log} e ON l.eventlogid = e.id
WHERE l.userid = :userid AND e.glossaryid = :glossaryid AND l.timedelivered IS NULL
ORDER BY l.id';
            $messages = $DB->get_records_sql($sql,
                                             array('userid' => (int)$USER->id, 'glossaryid' => $glossaryid),
                                             0, $messagescount);
            // If there are no unread messages show the latest read.
            if (count($messages) === 0) {
                $sql = 'SELECT l.* FROM {block_glsubs_messages_log} l ';
                $sql .= 'JOIN {block_glsubs_event_subs_log} e ON e.id = l.eventlogid AND e.glossaryid = :glossaryid ';
                $sql .= ' WHERE l.userid = :userid ORDER BY l.id DESC ';
                $messages = $DB->get_records_sql($sql,
                                                 array('userid' => (int)$USER->id, 'glossaryid' => $glossaryid),
                                                 0, $messagescount);
            }
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs get_latest_messages ' . $exception->getMessage(), DEBUG_DEVELOPER);
            return $messages;
        }

        if (count($messages) > 0) {
            foreach ($messages as $key => & $message) {
                try {
                    $message->event = $DB->get_record('block_glsubs_event_subs_log',
                                                      array('id' => (int)$message->eventlogid));
                    $message->date = date('Y-m-d H:i:s', (int)$message->event->timecreated);
                    $message->user = $DB->get_record('user', array('id' => (int)$message->event->userid));
                    $message->author = $DB->get_record('user', array('id' => (int)$message->event->authorid));
                } catch (Throwable $exception) {
                    $this->content->text .= '<strong>' .
                        get_string('block_could_not_access', 'block_glsubs') .
                        $glsubssettings->messagestoshow .
                        get_string('block_most_recent', 'block_glsubs') . '</strong>';
                }
            }
        }
        return $messages;
    }

    /**
     * Method               show_messages
     *
     * Purpose              if enabled show the relevant glossary event messages for the user of the current glossary
     *
     * Parameters
     *
     * @param               $glossaryid , the glossary id to identify the relevant messages for the user
     *
     * Returns              outputs the message links directly to the block
     */
    private function show_messages($glossaryid) {
        global $DB, $USER;
        // Get the block settings from its configuration.
        $glsubssettings = get_config('block_glsubs');
        $messagescount = (int)$glsubssettings->messagestoshow;

        try {
            $msglink = html_writer::link(
                new moodle_url('/message/index.php',
                               array('user1' => $USER->id, 'viewing' => 'recentnotifications')),
                get_string('goto_messages', 'block_glsubs'));
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs show_messages ' . $exception->getMessage(), DEBUG_DEVELOPER);
            $msglink = '';
        }

        if ((int)$glsubssettings->messagestoshow > 0) {
            $messages = $this->get_latest_messages($glossaryid);
            try {
                // Read how many unread messages exist for this glossary.
                $sql = 'SELECT l.id , l.userid , l.eventlogid , l.timecreated ,l.timedelivered
FROM {block_glsubs_messages_log} l
JOIN {block_glsubs_event_subs_log} e ON l.eventlogid = e.id
WHERE l.userid = :userid AND e.glossaryid = :glossaryid AND l.timedelivered IS NULL
ORDER BY l.id';
                $cmessages = $DB->get_records_sql($sql,
                                                  array('userid' => (int)$USER->id, 'glossaryid' => $glossaryid),
                                                  0, $messagescount);
                $counter = count($cmessages);
                // Release memory now.
                $cmessages = null;
                $unread = ($counter > 0);
            } catch (Throwable $exception) {
                debugging('ERROR: glsubs show_messages ' . $exception->getMessage(), DEBUG_DEVELOPER);
                $unread = false;
            }

            // Create a toggle mechanism for showing or hiding the recent messages.
            if (count($messages) > 0) {
                $this->page->requires->jquery();
                $javascriptswitch = <<<JSC
<script>
    $( document ).ready(function(){
          $("#glossarymessagesshowhide").click(function(){
              $("#glossarymessagesblocktable").toggle();
              $("#glossarymessagesshowhide").toggle();
              $("#glossarymessagesshowhide_2").toggle();
         });
        $("#glossarymessagesshowhide_2").click(function(){
            $("#glossarymessagesblocktable").toggle();
            $("#glossarymessagesshowhide").toggle();
            $("#glossarymessagesshowhide_2").toggle();
        });
     });
</script>
JSC;
                $this->content->text .= '<div id="glossarymessagesblock">';
                $this->content->text .= '<span id="glossarymessagesshowhide">';
                $this->content->text .= get_string('view_show_hide', 'block_glsubs');
                $this->content->text .= '</span>'; // Id="glossarymessagesshowhide".
                $this->content->text .= '<span id="glossarymessagesshowhide_2" style="display: none ;">';
                $this->content->text .= get_string('view_show_hide_2', 'block_glsubs');
                $this->content->text .= '</span>'; // Id="glossarymessagesshowhide".
                $this->content->text .= get_string('block_found', 'block_glsubs') . count($messages);
                $link = html_writer::link(
                    new moodle_url('/message/index.php', array()),
                    $unread ? get_string('block_unread_messages', 'block_glsubs') :
                        get_string('block_read_messages', 'block_glsubs'));
                $this->content->text .= $link;
                $this->content->text .= '<br/>';
                $this->content->text .= '<div id="glossarymessagesblocktable" style="display: none ;">';
                $this->content->text .= '<table id="glossarymessagestable"><thead><tr>';
                $this->content->text .= '<th>' . get_string('view_when', 'block_glsubs') . '</th>';
                $this->content->text .= '<th>&nbsp;</th>';
                $this->content->text .= '</th><th>' .
                    get_string('view_by_user', 'block_glsubs') . '</th>';
                $this->content->text .= '<th>&nbsp;</th>';
                $this->content->text .= '<th>' . get_string('view_on_concept', 'block_glsubs')
                    . '</th></tr></thead><tbody>';
                foreach ($messages as $key => $message) {
                    // If there is a concept asociated with this et its name.
                    if ((int)$message->event->conceptid > 0) {
                        try {
                            $record = $DB->get_record('glossary_entries',
                                                      array('id' => (int)$message->event->conceptid));
                            $name = $record->concept;
                            // In case of deleted concept attempt to get it from the message.
                            if (is_null($name)) {
                                $name = $this->get_entry($message->event->eventtext);
                            }
                        } catch (Throwable $exception) {
                            debugging('ERROR: glsubs show_messages ' . $exception->getMessage(), DEBUG_DEVELOPER);
                            $name = '';
                        }
                    } else { // Else if there is a category associated with it get its name.
                        try {
                            $record = $DB->get_record('glossary_categories',
                                                      array('id' => (int)$message->event->categoryid));
                            $name = $record->name;
                            if (is_null($name)) {
                                $name = $this->get_category($message->event->eventtext);
                            }
                        } catch (Throwable $exception) {
                            debugging('ERROR: glsubs show_messages ' . $exception->getMessage(), DEBUG_DEVELOPER);
                            $name = '';
                        }
                    }
                    try {
                        $link = html_writer::link(new moodle_url('/blocks/glsubs/view.php', array('id' => $key)),
                                                  substr($message->date, 0, 10),
                                                  array('title' => $message->date . chr(13) .
                                                      strip_tags($message->event->eventtext), 'font-size' => '90%;'));
                    } catch (Throwable $exception) {
                        debugging('ERROR: glsubs show_messages ' . $exception->getMessage(), DEBUG_DEVELOPER);
                        $link = '';
                    }
                    $this->content->text .= '<tr><td>' . $link . '</td>';
                    $this->content->text .= '<td>&nbsp;</td>';
                    $this->content->text .= '<td title="' . fullname($message->user) . '"> '
                        . $this->ellipsisstring(fullname($message->user), 20) . '</td>';
                    $this->content->text .= '<td>&nbsp;</td>';
                    $this->content->text .= '<td title="' . $name . '">' . $this->ellipsisstring($name, 18) . '</td></tr>';
                }
                $this->content->text .= '</tbody></table><hr style="visibility: visible !important; display: inline !important;"/>';
                $this->content->text .= '</div>'; // Id="glossarymessagesblocktable".
                $this->content->text .= '</div>'; // Id="glossarymessagesblock".
                $this->content->text .= $javascriptswitch;
            }
        }
    }


    /**
     * Method               get_category
     *
     * Purpose              Identify and return the message category(ies) in the message text
     *
     * Parameters
     *
     * @param               $messagetext , the message text
     *
     * Returns
     *
     * @return              mixed|string, either an empty string , either the category(ies) identified as text
     *
     */
    private function get_category($messagetext) {
        $ret = '';
        $msgparts = explode('<br />', $messagetext);
        foreach ($msgparts as $key => & $msgpart) {
            $msgpart = strip_tags($msgpart);
            if (stripos($msgpart, 'Category(ies)  ') !== false) {
                $ret = str_ireplace('Category(ies)  ', '', $msgpart);
                $ret = str_ireplace("\n", '', $ret);
            }
        }
        return $ret;

    }

    /**
     * Method               get_entry
     *
     * Purpose              Identify and return the message concept in the message text
     *
     * Parameters
     *
     * @param               $messagetext , the message text
     *
     * Returns
     *
     * @return              mixed|string, either an empty string , either the concept as text
     */
    private function get_entry($messagetext) {
        $ret = '';
        $msgparts = explode('<br />', $messagetext);
        foreach ($msgparts as $key => & $msgpart) {
            $msgpart = strip_tags($msgpart);
            if (stripos($msgpart, 'Concept [') !== false) {
                $ret = str_ireplace('Concept [', '', $msgpart);
                $ret = str_ireplace(']', '', $ret);
                $ret = str_ireplace("\n", '', $ret);
            }
        }
        return $ret;
    }

    /**
     * Method               get_content
     *
     * Purpose              create all the block contents and present it
     *                      Subscriptions Block Contents creation function
     *
     * Parameters           N/A
     *
     * Returns
     *
     * @return              string, as HTML content for the block
     *
     */
    public function get_content() {
        // Define usage of global variables.
        global $COURSE;

        // Check if the page is referring to a glossary module view activity.
        if ('mod-glossary-view' !== $this->page->pagetype) {
            return $this->content;
        }

        if (null !== $this->title) {
            $this->title = get_string('blockheader', 'block_glsubs');
        }

        // If the contents are already set, just return them.
        if ($this->content !== null) {
            return $this->content;
        }

        // This is only for logged in users.
        if (!isloggedin() || isguestuser()) {
            return '';
        }

        // Get the current moodle configuration.
        require_once(__DIR__ . '/../../config.php');

        // This is only for logged in users.
        require_login();

        // Get the module information.
        $courseinfo = get_fast_modinfo($COURSE);

        // Prepare for contents.
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->text .= '<strong>' . $this->page->title . '</strong>';

        // Add a footer for the block.
        $this->content->footer = '<hr style="display: block!important;"/><div style="text-align:center;">'
            . get_string('blockfooter', 'block_glsubs') . '</div>';

        // Get the id parameter if exists.
        $cmid = optional_param('id', 0, PARAM_INT);

        // Check if there is a valid glossary view page.
        if ($cmid > 0) {
            // Set page context.
            $this->page->set_context(context_module::instance($cmid));
            try {
                if ($courseinfo->get_cm($cmid)) {
                    $cm = $courseinfo->get_cm($cmid);
                } else {
                    return $this->content;
                }
            } catch (Throwable $e) {
                debugging('ERROR: glsubs get_content glsubs messages ' . $e->getMessage(), DEBUG_DEVELOPER);
                return $this->content;
            }

            // Check if the course module is available and it is visible.
            // And it is visible to the user and it is a glossary module.
            if (!(true === $cm->available && '1' === $cm->visible
                && true === $cm->uservisible && 'glossary' === $cm->modname)) {
                return $this->content;
            }

            // Get glossary ID.
            $glossaryid = (int)$cm->instance;

            // Show unread messages.
            $this->show_messages($glossaryid);

            // Create a glossary subscriptions block form and assign its action to the original page.
            $subscriptionsform = new block_glsubs_form($this->currentpageurl()['fullurl']);
            // Test for the form status , do kee the order of cancelled, submitted, new.
            if ($subscriptionsform->is_cancelled()) {
                // Redirect to the original page where the Cancel button was pressed.
                // So use the $_SERVER['HTTP_REFERER'] variable.
                try {
                    $url = new moodle_url($_SERVER['HTTP_REFERER'], array());
                    redirect($url);
                } catch (Throwable $e) {
                    header('Location: ' . $_SERVER['HTTP_REFERER']);
                }
            } else if ($subscriptionsform->is_submitted()) {
                $subsdata = $subscriptionsform->get_data();
                if ($subsdata) {
                    // Store this data set.
                    try {
                        $errors = $this->store_data($subsdata);
                    } catch (Throwable $exception) {
                        $errors = new \stdClass();
                        $errors->messages[] = 'ERROR: glsubs get_content while attempting to save data '
                            . $exception->getMessage();
                    }

                    // If there were any errors, display them.
                    if (is_array($errors->messages)) {
                        foreach ($errors->messages as $key => $errmsg) {
                            $this->content->text .= '<p>ERROR: glsubs ' . $errmsg . '</p>';
                        }
                    }
                }
            }

            // Add the contents of the form to the block.
            $this->content->text .= $subscriptionsform->render();
        }
        // Finish and return contents.
        return $this->content;
    }

    /**
     * Method               store_data
     *
     * Purpose              Store the subscription preferences for the user on the current glossary
     *
     * Parameters
     *
     * @param               $dataset , the data of the Moodle plugin form
     *
     * Returns
     *
     * @return              null in case of success or stdClass of errors in case of errors
     */
    private function store_data($dataset) {
        global $DB;
        $error = new stdClass();
        $error->messages = [];
        $userid = $dataset->glossary_userid;
        $glossaryid = $dataset->glossary_glossaryid;
        $fullsubkey = get_string('glossaryformfullelementname', 'block_glsubs');
        $newcat = get_string('glossaryformcategorieselementname', 'block_glsubs');
        $newconcept = get_string('glossaryformconceptselementname', 'block_glsubs');
        $arrdata = (array)$dataset;
        try {
            foreach ($arrdata as $key => $value) {

                // If the data in the form is a glossary comment subscription instruction then.
                if ($key === $fullsubkey) {
                    if ($DB->record_exists('block_glsubs_glossaries_subs',
                                           array('userid' => $userid, 'glossaryid' => $glossaryid))) {
                        $oldrecord = $DB->get_record('block_glsubs_glossaries_subs',
                                                     array('userid' => $userid, 'glossaryid' => $glossaryid));
                        $oldrecord->active = $value;
                        $DB->update_record('block_glsubs_glossaries_subs', $oldrecord, false);
                    } else {
                        $newrecord = new stdClass();
                        $newrecord->userid = $userid;
                        $newrecord->glossaryid = $glossaryid;
                        $newrecord->active = $value;
                        $newrecord->newcategories = 0;
                        $newrecord->newentriesuncategorised = 0;
                        $newentryid = $DB->insert_record('block_glsubs_glossaries_subs',
                                                         $newrecord, true, true);
                        // Use bulk updates.
                        if (!$newentryid > 0) {
                            $error->messages[] = "Cannot create new full subscription record for $userid" .
                                " on glossary $glossaryid ";
                        }
                    }
                    // If the data in the form is a new categories subscription instruction then.
                } else if ($key === $newcat) {
                    if ($DB->record_exists('block_glsubs_glossaries_subs',
                                           array('userid' => $userid, 'glossaryid' => $glossaryid))) {
                        $oldrecord = $DB->get_record('block_glsubs_glossaries_subs',
                                                     array('userid' => $userid, 'glossaryid' => $glossaryid));
                        $oldrecord->newcategories = $value;
                        $DB->update_record('block_glsubs_glossaries_subs', $oldrecord, false);
                    } else {
                        $newrecord = new stdClass();
                        $newrecord->userid = $userid;
                        $newrecord->glossaryid = $glossaryid;
                        $newrecord->active = 0;
                        $newrecord->newcategories = $value;
                        $newrecord->newentriesuncategorised = 0;
                        $newentryid = $DB->insert_record('block_glsubs_glossaries_subs',
                                                         $newrecord, true, true); // Use bulk updates.
                        if (!$newentryid > 0) {
                            $error->messages[] = "Cannot create new new categories subscription record for $userid" .
                                " on glossary $glossaryid ";
                        }
                    }
                    // If the data in the form is a new uncategorised consepts sbscription instruction then.
                } else if ($key === $newconcept) {
                    if ($DB->record_exists('block_glsubs_glossaries_subs',
                                           array('userid' => $userid, 'glossaryid' => $glossaryid))) {
                        $oldrecord = $DB->get_record('block_glsubs_glossaries_subs',
                                                     array('userid' => $userid, 'glossaryid' => $glossaryid));
                        $oldrecord->newentriesuncategorised = $value;
                        $DB->update_record('block_glsubs_glossaries_subs', $oldrecord, false);
                    } else {
                        $newrecord = new stdClass();
                        $newrecord->userid = $userid;
                        $newrecord->glossaryid = $glossaryid;
                        $newrecord->active = 0;
                        $newrecord->newcategories = 0;
                        $newrecord->newentriesuncategorised = $value;
                        $newentryid = $DB->insert_record('block_glsubs_glossaries_subs',
                                                         $newrecord, true, true);
                        // Use bulk updates.
                        if (!$newentryid > 0) {
                            $error->messages[] = "Cannot create new uncategorised concepts subscription record for $userid" .
                                " on glossary $glossaryid ";
                        }
                    }
                    // If the data inthe form is a category subscription instruction then.
                } else if (0 === strpos($key, 'glossary_category')) {

                    $categoryid = (int)preg_replace('/[^0-9,.]/', '', $key);

                    if ($DB->record_exists('block_glsubs_categories_subs',
                                           array('userid' => $userid, 'glossaryid' => $glossaryid, 'categoryid' => $categoryid))) {
                        $oldcategoryrecord = $DB->get_record('block_glsubs_categories_subs',
                                                             array('userid' => $userid,
                                                                 'glossaryid' => $glossaryid, 'categoryid' => $categoryid));
                        $oldcategoryrecord->active = $value;
                        $DB->update_record('block_glsubs_categories_subs', $oldcategoryrecord, false);
                    } else {
                        $newrecord = new stdClass();
                        $newrecord->userid = $userid;
                        $newrecord->glossaryid = $glossaryid;
                        $newrecord->categoryid = (int)preg_replace('/[^0-9,.]/', '', $key);
                        $newrecord->active = $value;
                        $newentryid = $DB->insert_record('block_glsubs_categories_subs', $newrecord, true, true);
                        // Use bulk updates.
                        if (!$newentryid > 0) {
                            $error->messages[] = "Cannot create new category subscription record for $userid" .
                                " on glossary $glossaryid and category $key";
                        }
                    }

                    // If the data in the form is a concept subscription instruction then.
                } else if (0 === strpos($key, 'glossary_concept')) {

                    $conceptid = (int)preg_replace('/[^0-9,.]/', '', $key);
                    if ($DB->record_exists('block_glsubs_concept_subs',
                                           array('userid' => $userid, 'glossaryid' => $glossaryid, 'conceptid' => $conceptid))) {
                        $oldrecord = $DB->get_record('block_glsubs_concept_subs',
                                                     array('userid' => $userid,
                                                         'glossaryid' => $glossaryid, 'conceptid' => $conceptid));
                        $oldrecord->conceptactive = $value;
                        $DB->update_record('block_glsubs_concept_subs', $oldrecord, false);
                    } else {
                        $newrecord = new stdClass();
                        $newrecord->userid = $userid;
                        $newrecord->glossaryid = $glossaryid;
                        $newrecord->conceptid = $conceptid;
                        $newrecord->conceptactive = $value;
                        $newrecord->commentsactive = 0;
                        $newentryid = $DB->insert_record('block_glsubs_concept_subs', $newrecord, true, true);
                        // Use bulk updates.
                        if (!$newentryid > 0) {
                            $error->messages[] = "Cannot create new concept subscription record for $userid" .
                                " on glossary $glossaryid and concept $key ";
                        }
                    }
                    // If the data in the form is a concept comments subscription instruction then.
                } else if (0 === strpos($key, 'glossary_comment')) {

                    $conceptid = (int)preg_replace('/[^0-9,.]/', '', $key);
                    if ($DB->record_exists('block_glsubs_concept_subs',
                                           array('userid' => $userid, 'glossaryid' => $glossaryid, 'conceptid' => $conceptid))) {
                        $oldrecord = $DB->get_record('block_glsubs_concept_subs',
                                                     array('userid' => $userid, 'glossaryid' => $glossaryid,
                                                         'conceptid' => $conceptid));
                        $oldrecord->commentsactive = $value;
                        $DB->update_record('block_glsubs_concept_subs', $oldrecord, false);
                    } else {
                        $newrecord = new stdClass();
                        $newrecord->userid = $userid;
                        $newrecord->glossaryid = $glossaryid;
                        $newrecord->conceptid = $conceptid;
                        $newrecord->conceptactive = 0;
                        $newrecord->commentsactive = $value;
                        $newentryid = $DB->insert_record('block_glsubs_concept_subs', $newrecord, true, true); // Use bulk updates.
                        if (!$newentryid > 0) {
                            $error->messages[] = "Cannot create new concept comments subscription record for $userid" .
                                " on glossary $glossaryid and concept $key ";
                        }
                    }
                    // If the data in the form is an author subscription instruction then.
                } else if (0 === strpos($key, 'glossary_author')) {
                    $authorid = (int)preg_replace('/[^0-9,.]/', '', $key);
                    if ($DB->record_exists('block_glsubs_authors_subs',
                                           array('userid' => $userid, 'glossaryid' => $glossaryid, 'authorid' => $authorid))) {

                        $oldrecord = $DB->get_record('block_glsubs_authors_subs',
                                                     array('userid' => $userid,
                                                         'glossaryid' => $glossaryid, 'authorid' => $authorid));
                        $oldrecord->active = $value;
                        $DB->update_record('block_glsubs_authors_subs', $oldrecord, false);

                    } else {
                        $newrecord = new stdClass();
                        $newrecord->userid = $userid;
                        $newrecord->glossaryid = $glossaryid;
                        $newrecord->authorid = $authorid;
                        $newrecord->active = $value;
                        $newentryid = $DB->insert_record('block_glsubs_authors_subs', $newrecord, true, true); // Use bulk updates.
                        if (!$newentryid > 0) {
                            $error->messages[] = "Cannot create new author subscription record for $userid" .
                                " on glossary $glossaryid and author $authorid ";
                        }
                    }
                }
            }
        } catch (Throwable $exception) {
            $error->messages[] = 'ERROR: glsubs while attempting to store data ' . $exception->getMessage();
        }

        // What to report at the end ?
        if (count($error->messages) > 0) {
            return $error;
        } else {
            return null;
        }
    }

    /**
     * Method               instance_allow_multiple
     *
     * Purpose              enable only one instance per page to associate with the viewable glossary
     *
     * Parameters           N/A
     *
     * Returns
     *
     * @return              bool , false to disable multiple instances of the block on one page
     *
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Method               has_config
     *
     * Purpose              This function is required by Moodle to overide the default inherited function and to
     *                      return true in order to be able to store and use relevant universal plugin settings.
     *                      If you need more fine grained settings such as user or role or other classification
     *                      you must provide a set of database structures and their associated business logic
     *
     * Parameters           N/A
     *
     * Returns
     *
     * @return              bool, true as this block has an administration settings functionality
     */
    public function has_config() {
        return parent::has_config() || true;
    }

    /****************************************************************
     *
     * Method:       ellipsisString
     *
     * Purpose:      Create short form of a string including ellipsis (...)
     *              if required. Used for making use in narrow elements of
     *              the web screens
     *
     * Parameters:   $text , the text (any bytes form), $size , the legnth to cut off
     *
     * Returns:      the shortened text version plus optional ellipsis
     ****************************************************************
     * Reduce text to the maximum parameterised length
     *
     * @param $text
     * @param $size
     *
     * @return string
     */
    protected function ellipsisstring($text, $size) {
        $retstr = $text;
        if ($size < 1) {
            $size = 3;
        }
        if ($this->is_multibyte($text)) {
            if (mb_strlen($text) > $size) {
                $retstr = mb_substr($retstr, 0, $size - 3) . '...';
            }
        } else {
            if (strlen($text) > $size) {
                $retstr = substr($retstr, 0, $size - 3) . '...';
            }
        }
        return $retstr;
    }

    /****************************************************************
     *
     * Method:       is_multibyte
     *
     * Purpose:     checks if the text contains mutli byte characters
     *
     *
     *
     * Parameters:   $s , the text (any bytes form)
     *
     * Returns:      true if at least one character is multi byte
     ****************************************************************
     * check for multibyte strings
     */
    protected function is_multibyte($s) {
        return mb_strlen($s, 'utf-8') < strlen($s);
    }

}
