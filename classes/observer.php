<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by.
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
/****************************************************************
 *
 * File:  blocks/glsubs/classes/observer.php
 *
 * Purpose:  A class handling the subscription events for all the
 * glossaries and their subscriptions, storing the event and its
 * associated data in a database table log
 *
 * Input:    N/A
 *
 *
 *
 * Output:   N/A
 *
 *
 *
 * Notes:   The block should be installed, added to the course
 *          and configured to be available for all course pages
 *          It does not matter if the moodle event subscriptions
 *          are configured to work for the general events
 *
 ****************************************************************/

/**
 * Created by PhpStorm.
 * User: vasileios
 * Queen Mary University of London
 * Date: 14/10/2016
 * Time: 14:42
 */

// Define event types.
// Each event may be split into more than one log entries as some activities have more aspects than one.
define('EVENT_GENERIC', 'G'); // New Category or New Concept without category.
define('EVENT_AUTHOR', 'A'); // Entry activity related to the Concept Author.
define('EVENT_CATEGORY', 'C'); // Category Updated Or Deleted.
define('EVENT_ENTRY', 'E'); // Any Concept activity.
define('CATEGORY_GENERIC', get_string('CATEGORY_GENERIC', 'block_glsubs'));

// Use this when the category of the event is generic.

class block_glsubs_observer {
    /****************************************************************
     *
     * Method:       static observe_all
     *
     * Purpose:      the entry point of all glossary subscription events
     *              as it is defined in the blocks/glsubs/db/events.php
     *              for every event type. The reason is to create a single
     *              entry point to classify and reduce duplicate code
     *
     *
     * Parameters:   a core event object
     *
     * Returns:      true on succesful execution
     ****************************************************************/
    /**
     * @param \core\event\base $event
     *
     * @return bool|null
     */
    static public function observe_all(\core\event\base $event) {

        switch($event->eventname) {
            case '\mod_glossary\event\category_created':
                self::category_event($event);
                break;
            case '\mod_glossary\event\category_updated':
                self::category_event($event);
                break;
            case '\mod_glossary\event\category_deleted':
                self::category_event($event);
                break;
            case '\mod_glossary\event\entry_created':
                self::entry_event($event);
                break;
            case '\mod_glossary\event\entry_updated':
                self::entry_event($event);
                break;
            case '\mod_glossary\event\entry_deleted':
                self::entry_event($event);
                break;
            case '\mod_glossary\event\entry_approved':
                self::entry_event($event);
                break;
            case '\mod_glossary\event\entry_disapproved':
                self::entry_event($event);
                break;
            case '\mod_glossary\event\comment_created':
                self::comment_event($event);
                break;
            case '\mod_glossary\event\comment_deleted':
                self::comment_event($event);
                break;
            default:
                return null;
        }
        return true;
        // Save the event in the glossary events log..
    }

    /****************************************************************
     *
     * Method:       static entry_event
     *
     * Purpose:      the entry point of glossary concept events
     *
     * Parameters:   a core event object
     *
     * Returns:      the new log entry id or false/null in case of error
     ****************************************************************/
    /**
     * @param \core\event\base $event
     *
     * @return bool|int|null
     */
    private static function entry_event(\core\event\base $event) {
        global $DB;

        // Get the autosubscription setting.
        $autosubscribe = ('1' === get_config('block_glsubs', 'autoselfsubscribe'));

        // Get the event data array.
        $eventdata = $event->get_data();

        // Define the default return value in case of error.
        $logid = null;

        // Get glossary concept id.
        $glossaryconceptid = (int)$eventdata['objectid'];

        try {
            // Get glossary concept.
            if ('created' === $eventdata['action']) {
                $glossaryconcept = $DB->get_record($eventdata['objecttable'], array('id' => $glossaryconceptid));
            } else {
                $glossaryconcept = $event->get_record_snapshot($eventdata['objecttable'], $glossaryconceptid);
            }

            // Get concept category IDs.
            $conceptcategories = $DB->get_records('glossary_entries_categories', array('entryid' => $glossaryconceptid));

            // Store categories as names.
            $categories = '';

            // Store categories as array of objects.
            if (count($conceptcategories) > 0) {
                foreach ($conceptcategories as $key => & $myownconceptcategory) {
                    $category = $DB->get_record('glossary_categories', array('id' => (int)$myownconceptcategory->categoryid));
                    $myownconceptcategory->name = $category->name;
                    $categories .= '[' . $category->name . '] ';
                }
            } else { // Add a generic category for the event.
                $conceptcategories[0] = new stdClass();
                $conceptcategories[0]->id = 0;
                $conceptcategories[0]->categoryid = null;
                $conceptcategories[0]->entryid = $glossaryconceptid;
                $conceptcategories[0]->name = CATEGORY_GENERIC;
                $categories = $conceptcategories[0]->name;
            }

            // Get author of the concept where the comment is created.
            $authorid = (int)$glossaryconcept->userid;

            // Get the glossary id.
            $glossaryid = (int)$glossaryconcept->glossaryid;

            // Get the user.
            $user = $DB->get_record('user', array('id' => (int)$eventdata['userid']));

            // Get the course.
            $course = $DB->get_record('course', array('id' => (int)$eventdata['courseid']));

            // Get course module.
            $coursemodule = $DB->get_record('course_modules', array('id' => (int)$eventdata['contextinstanceid']));

            // Get module.
            $module = $DB->get_record('modules', array('id' => (int)$coursemodule->module));

            // Get the module database table name.
            $moduletable = $module->name;

            // Get the module  table entry.
            $coursemoduleentry = $DB->get_record($moduletable, array('id' => (int)$coursemodule->instance));

            // Get the module name.
            $modulename = $coursemoduleentry->name;

            // Get the event url.
            // Comment $ event_url = $ event->get_url(); .

            if ('created' === $eventdata['action'] || 'updated' === $eventdata['action']) {
                // Add a subscription for this concept comment for this user/creator.
                $userid = (int)$user->id;
                $glossaryid = (int)$glossaryid;
                $glossaryconceptid = (int)$glossaryconceptid;
                $filters = array('userid' => $userid, 'glossaryid' => $glossaryid, 'conceptid' => $glossaryconceptid);

                // Check if the user is registered into the glossary subscriptions main table.
                self::check_user_subscription($autosubscribe, $userid, $glossaryid);

                // Save the subscription to this glossary concept comments for this user/creator.
                // Check if the subscription to the concept/comments already exists first.
                if ($DB->record_exists('block_glsubs_concept_subs', $filters)) {
                    $conceptsubscription = $DB->get_record('block_glsubs_concept_subs', $filters);
                    $conceptsubscription->conceptactive = 1;  // Mark active the concept subscription.
                    $conceptsubscription->commentsactive = 1; // Mark active the concept comments subscription.

                    // Check if an automatic subscription should be created.
                    if ($autosubscribe) {
                        $DB->update_record('block_glsubs_concept_subs', $conceptsubscription, false);
                    }
                } else {
                    $conceptsubscription = new stdClass();
                    $conceptsubscription->userid = (int)$user->id;
                    $conceptsubscription->glossaryid = $glossaryid;
                    $conceptsubscription->conceptid = $glossaryconceptid;
                    $conceptsubscription->conceptactive = 1;
                    $conceptsubscription->commentsactive = 1;
                    if ($autosubscribe) {
                        $logid = $DB->insert_record('block_glsubs_concept_subs', $conceptsubscription, true);
                    }
                }
            }
            // Save the log entry for this glossary event.

            // Make the text request object.
            $textrequest = array();
            $textrequest['event_type'] = 'entry';
            $textrequest['event'] = $event;
            $textrequest['event_item'] = $glossaryconcept;
            $textrequest['event_course'] = $course;
            $textrequest['event_module'] = $modulename;
            $textrequest['event_comment'] = null;
            $textrequest['event_categories'] = $categories;
            $textrequest['event_author'] = $authorid;
            // Get the event text.
            $eventtext = self::get_event_text($textrequest);

            // Clean record.
            $cleanrecord = new stdClass();
            // Create log entries for each concept category or one generic.
            foreach ($conceptcategories as $key => $myconceptcategory) {
                // Build an event record to add to the subscriptions log for each category or the generic category.
                $record = clone $cleanrecord;
                $record->userid = (int)$eventdata['userid']; // Get the user id for the event.
                $record->glossaryid = $glossaryid; // Get the glossary id.
                $record->categoryid = ((int)$myconceptcategory->categoryid === 0) ? null : (int)$myconceptcategory->categoryid; // Get each of the category id from the event.
                $record->conceptid = $glossaryconceptid; // The concept id of the comment created.
                $record->authorid = $authorid; // Get the user id of the concept.
                $record->processed = 0; // Mark it to be processed.
                $record->useremail = $user->email; // Get user's email at the time of the event.
                $record->eventlink = html_writer::link($event->get_url(), 'LINK'); // Create a link to the event.
                $record->eventtext = $eventtext;
                // Concept Entry comment related event.
                $record->eventtype =
                    ($myconceptcategory->name === CATEGORY_GENERIC) ? EVENT_GENERIC : EVENT_ENTRY;
                $record->timecreated = $eventdata['timecreated'];
                $record->timeprocessed = null;
                $record->contextinstanceid = (int)$eventdata['contextinstanceid'];
                $record->crud = $eventdata['crud'];
                $record->edulevel = (int)$eventdata['edulevel'];

                // Store the event record for this category.
                $logid = $DB->insert_record('block_glsubs_event_subs_log', $record, true);
            }
        } catch (Throwable $e) {
            debugging('ERROR: glsubs entry event messages ' . $e->getMessage(), DEBUG_DEVELOPER);
            // There was an error creating the event entry for this category in this glossary.
            return false;
        }

        // Return the log id.
        return $logid;
    }

    /****************************************************************
     *
     * Method:       static comment_event
     *
     * Purpose:      the entry point of glossary comment events
     *
     * Parameters:   a core event object
     *
     * Returns:      the new log entry id or false/null in case of error
     ****************************************************************/
    /**
     * @param \core\event\base $event
     *
     * @return bool|int
     */
    private static function comment_event(\core\event\base $event) {
        global $DB;

        // Get the autosubscription setting.
        $autosubscribe = ('1' === get_config('block_glsubs', 'autoselfsubscribe'));

        // Get the event data array.
        $eventdata = $event->get_data();

        // Define the default return value in case of error.
        $logid = null;

        // Get the category id.
        $commentid = (int)$eventdata['objectid'];

        try {
            // Get the comment record.
            if ('created' === $eventdata['action']) {
                $commentrecord = $DB->get_record($eventdata['objecttable'], array('id' => $commentid));
            } else {
                $commentrecord = $event->get_record_snapshot($eventdata['objecttable'], $commentid);
            }

            // Get comment content.
            $commentcontent = $commentrecord->content;

            // Get glossary concept id.
            if ('created' === $eventdata['action']) {
                $glossaryconceptid = (int)$commentrecord->itemid;
            } else {
                $glossaryconceptid = (int)$eventdata['other']['itemid'];
            }

            // Get glossary concept.
            $glossaryconcept = $DB->get_record('glossary_entries', array('id' => $glossaryconceptid));

            // Get concept category IDs.
            $conceptcategories = $DB->get_records('glossary_entries_categories', array('entryid' => $glossaryconceptid));

            // Store categories as names.
            $categories = '';

            // Store categories as array of objects.
            if (count($conceptcategories) > 0) {
                foreach ($conceptcategories as $key => & $conceptcategory) {
                    $category = $DB->get_record('glossary_categories', array('id' => (int)$conceptcategory->categoryid));
                    $conceptcategory->name = $category->name;
                    $categories .= '[' . $category->name . '] ';
                }
            } else { // Add a generic category for the event.
                $conceptcategories[0] = new stdClass();
                $conceptcategories[0]->id = 0;
                $conceptcategories[0]->categoryid = null;
                $conceptcategories[0]->entryid = $glossaryconceptid;
                $conceptcategories[0]->name = CATEGORY_GENERIC;
                $categories = $conceptcategories[0]->name;
            }

            // Get author of the concept where the comment is created.
            $authorid = (int)$glossaryconcept->userid;

            // Get the glossary id.
            $glossaryid = (int)$glossaryconcept->glossaryid;

            // Get the user.
            $user = $DB->get_record('user', array('id' => (int)$eventdata['userid']));

            // Get the course.
            $course = $DB->get_record('course', array('id' => (int)$eventdata['courseid']));

            // Get course module.
            $coursemodule = $DB->get_record('course_modules', array('id' => (int)$eventdata['contextinstanceid']));

            // Get module.
            $module = $DB->get_record('modules', array('id' => (int)$coursemodule->module));

            // Get the module database table name.
            $moduletable = $module->name;

            // Get the module  table entry.
            $coursemoduleentry = $DB->get_record($moduletable, array('id' => (int)$coursemodule->instance));

            // Get the module name.
            $modulename = $coursemoduleentry->name;

            // Make the text request object.
            $textrequest = array();
            $textrequest['event_type'] = 'comment';
            $textrequest['event'] = $event;
            $textrequest['event_item'] = $glossaryconcept;
            $textrequest['event_course'] = $course;
            $textrequest['event_module'] = $modulename;
            $textrequest['event_comment'] = $commentcontent;
            $textrequest['event_categories'] = $categories;
            $textrequest['event_author'] = $authorid;
            // Get the event text.
            $eventtext = self::get_event_text($textrequest);

            // Add a subscription for this concept comment for this user/creator.
            $userid = (int)$user->id;
            $glossaryid = (int)$glossaryid;
            $glossaryconceptid = (int)$glossaryconceptid;
            $filters = array('userid' => $userid, 'glossaryid' => $glossaryid, 'conceptid' => $glossaryconceptid);

            // Check if the user is registered into the glossary subscriptions main table.
            self::check_user_subscription($autosubscribe, $userid, $glossaryid);

            // Save the subscription to this glossary concept comments for this user/creator.
            // Check if the subscription to the concept/comments already exists first.
            if ($DB->record_exists('block_glsubs_concept_subs', $filters)) {
                // Activate the subscriptions for this user on this concept and its comments.
                // After the events recorder and messages are sent over to the users.
                $conceptsubscription = $DB->get_record('block_glsubs_concept_subs', $filters);
                $conceptsubscription->conceptactive = 1;
                $conceptsubscription->commentsactive = 1;
                if ($autosubscribe) {
                    $DB->update_record('block_glsubs_concept_subs', $conceptsubscription, false);
                }
            } else {
                // Create a subscription as this activity should be reported to them by a message.
                $conceptsubscription = new stdClass();
                $conceptsubscription->userid = (int)$user->id;
                $conceptsubscription->glossaryid = $glossaryid;
                $conceptsubscription->conceptid = $glossaryconceptid;
                $conceptsubscription->conceptactive = 1;
                $conceptsubscription->commentsactive = 1;
                if ($autosubscribe) {
                    $logid = $DB->insert_record('block_glsubs_concept_subs', $conceptsubscription, true);
                }
            }

            $cleanrecord = new stdClass();
            // Save the log entries for this glossary event, one for each category or a generic one.
            foreach ($conceptcategories as $key => $conceptcategorya) {
                // Build an event record to add to the subscriptions log.
                $record = clone $cleanrecord;
                $record->userid = (int)$eventdata['userid']; // Get the user id for the event.
                $record->glossaryid = $glossaryid; // Get the glossary id.
                $record->categoryid = ((int)$conceptcategorya->categoryid === 0) ? null :
                    (int)$conceptcategorya->categoryid; // Get the category id.
                $record->conceptid = $glossaryconceptid; // The concept id of the comment created.
                $record->authorid = $authorid; // Get the user id of the concept.
                $record->processed = 0; // Mark it to be processed.
                $record->useremail = $user->email; // Get user's email at the time of the event.
                $record->eventlink = html_writer::link($event->get_url(), 'LINK'); // Create a link to the event.
                $record->eventtext = $eventtext;
                $record->eventtype = EVENT_ENTRY; // Concept Entry comment related event.
                $record->timecreated = $eventdata['timecreated'];
                $record->timeprocessed = null;
                $record->contextinstanceid = (int)$eventdata['contextinstanceid'];
                $record->crud = $eventdata['crud'];
                $record->edulevel = (int)$eventdata['edulevel'];

                // Store the event record for this category.
                $logid = $DB->insert_record('block_glsubs_event_subs_log', $record, true);
            }

        } catch (Throwable $e) {
            debugging('ERROR: glsubs comment event messages ' . $e->getMessage(), DEBUG_DEVELOPER);
            // There was an error creating the event entry for this category in this glossary.
            return false;
        }

        // Return the log id.
        return $logid;
    }

    /****************************************************************
     *
     * Method:       static category_event
     *
     * Purpose:      the entry point of glossary category events
     *
     * Parameters:   a core event object
     *
     * Returns:      the new log entry id or false/null in case of error
     ****************************************************************/
    /**
     * @param \core\event\base $event
     *
     * @return bool|int
     */
    private static function category_event(\core\event\base $event) {
        global $DB;

        // Get the autosubscription setting.
        $autosubscribe = ('1' === get_config('block_glsubs', 'autoselfsubscribe'));

        // Get the event data array.
        $eventdata = $event->get_data();

        // Get the category id.
        $categoryid = (int)$eventdata['objectid'];

        try {
            // Get the glossary category.
            if ('created' === $eventdata['action']) {
                $glossarycategory = $DB->get_record($eventdata['objecttable'], array('id' => $categoryid));
            } else {
                $glossarycategory = $event->get_record_snapshot($eventdata['objecttable'], $categoryid);
            }

            // Get the glossary id.
            $glossaryid = (int)$glossarycategory->glossaryid;

            // Get the user.
            $user = $DB->get_record('user', array('id' => (int)$eventdata['userid']));

            // Get the course.
            $course = $DB->get_record('course', array('id' => (int)$eventdata['courseid']));

            // Get course module.
            $coursemodule = $DB->get_record('course_modules', array('id' => (int)$eventdata['contextinstanceid']));

            // Get module.
            $module = $DB->get_record('modules', array('id' => (int)$coursemodule->module));

            // Get the module database table name.
            $moduletable = $module->name;

            // Get the module  table entry.
            $coursemoduleentry = $DB->get_record($moduletable, array('id' => (int)$coursemodule->instance));

            // Get the module name.
            $modulename = $coursemoduleentry->name;

            // Make the text request object.
            $textrequest = array();
            $textrequest['event_type'] = 'category';
            $textrequest['event'] = $event;
            $textrequest['event_item'] = $glossarycategory;
            $textrequest['event_course'] = $course;
            $textrequest['event_module'] = $modulename;
            $textrequest['event_comment'] = null;
            $textrequest['event_categories'] = $glossarycategory->name;
            $textrequest['event_author'] = $user->id;
            // Get the event text.
            $eventtext = self::get_event_text($textrequest);

            // Check if the user is registered into the glossary subscriptions main table.
            self::check_user_subscription($autosubscribe, (int)$user->id, $glossaryid);

            // Build an event record to add to the subscriptions log.
            $record = new stdClass();
            $record->userid = (int)$eventdata['userid']; // Get the user id for the event.
            $record->glossaryid = (int)$glossaryid; // Get the glossary id.
            $record->categoryid = $categoryid; // Get the category id from the event.
            $record->conceptid = null; // There is no concept id related to new category events.
            $record->authorid = (int)$eventdata['userid']; // Get the user id as the author id for creating this category.
            $record->processed = 0; // Mark it to be processed.
            $record->useremail = $user->email; // Get user's email at the time of the event.
            $record->eventlink = html_writer::link($event->get_url(), 'LINK'); // Create a link to the event.
            $record->eventtext = $eventtext;
            // Conditionally set the event type.
            $record->eventtype = 'created' === $eventdata['action'] ? EVENT_GENERIC : EVENT_CATEGORY;
            $record->timecreated = $eventdata['timecreated'];
            $record->timeprocessed = null;
            $record->contextinstanceid = (int)$eventdata['contextinstanceid'];
            $record->crud = $eventdata['crud'];
            $record->edulevel = (int)$eventdata['edulevel'];

            // Check if this is a category created action and initialise a subscription for the creator/user.
            if ('created' === $eventdata['action']) {
                // Add a subscription for this category for this user/creator.
                $categorysubscription = new stdClass();
                $categorysubscription->userid = (int)$user->id;
                $categorysubscription->glossaryid = (int)$glossaryid;
                $categorysubscription->categoryid = $categoryid;
                $categorysubscription->active = 1;

                // Save the subscription to this glossary category for this user/creator.
                if ($autosubscribe) {
                    $DB->insert_record('block_glsubs_categories_subs', $categorysubscription, false);
                }
            } else if ('updated' === $eventdata['action']) {
                // Activate the subscription to this category for the user.
                $filters['userid'] = (int)$user->id;
                $filters['glossaryid'] = (int)$glossaryid;
                $filters['categoryid'] = (int)$categoryid;
                $categorysubscription = $DB->get_record('block_glsubs_categories_subs', $filters);
                $categorysubscription->active = 1;
                if ($autosubscribe) {
                    $DB->update_record('block_glsubs_categories_subs', $categorysubscription, false);
                }
            }

            // Save the log entry for this glossary event.
            $logid = $DB->insert_record('block_glsubs_event_subs_log', $record, true);
        } catch (Throwable $e) {
            debugging('ERROR: glsubs category event messages ' . $e->getMessage(), DEBUG_DEVELOPER);
            // There was an error creating the event entry for this category in this glossary.
            return false;
        }
        // Return the log id.
        return $logid;
    }

    /****************************************************************
     *
     * Method:       static get_event_text
     *
     * Purpose:      Create an event related message in HTML format
     *
     * Parameters:   an event object based on the event type
     * (category, entry, comment)
     *
     * Returns:      the HTML text for the event
     ****************************************************************/
    /**
     * @param array $textevent
     * $text_event['event_type']
     * $text_event['event']
     * $text_event['event_item']
     * $text_event['event_course']
     * $text_event['event_module']
     * $text_event['event_comment']
     * $text_event['event_categories']
     * $text_event['event_author']
     *
     * @return string
     */
    protected static function get_event_text(array $textevent) {

        $eventtext = '';
        // Get event data.
        $eventdata = $textevent['event']->get_data();

        // Get the autosubscription setting.
        $autosubscribe = ('1' === get_config('block_glsubs', 'autoselfsubscribe'));

        // Get event user link to use it in the message.
        if ((int)$eventdata['userid'] > 0) {
            try {
                $userurl = new moodle_url('/user/view.php', array('id' => (int)$eventdata['userid']));
                $userlink = html_writer::link($userurl, fullname(\core_user::get_user((int)$eventdata['userid'])));
            } catch (Throwable $exception) {
                debugging('ERROR: glsubs get event text messages ' . $exception->getMessage(), DEBUG_DEVELOPER);
                $userlink = '';
            }
        } else {
            $userlink = '';
        }

        // Get the author link to use it in the message.
        if ((int)$textevent['event_author'] > 0) {
            try {
                $authorurl = new moodle_url('/user/view.php', array('id' => (int)$textevent['event_author']));
                $authorlink = html_writer::link($authorurl, fullname(\core_user::get_user((int)$textevent['event_author'])));
            } catch (Throwable $exception) {
                debugging('ERROR: glsubs get event text messages ' . $exception->getMessage(), DEBUG_DEVELOPER);
                $authorlink = '';
            }
        } else {
            $authorlink = '';
        }

        $eventarray = str_replace(array("\\", '_', 'mod'), array(' ', ' ', 'module'), $textevent['event']->eventname);
        $eventarray = explode(' ', $eventarray);
        $eventactivity = $eventarray[4]; // What it was.
        $eventaction = $eventarray[5]; // What happened.
        $eventdatetime = date('l d/F/Y G:i:s', (int)$textevent['event']->timecreated); // When it happened.

        // New version Message.
        // Course.
        $eventtext .= PHP_EOL . $textevent['event_course']->fullname;

        // Module.
        $eventtext .= PHP_EOL . $textevent['event_module'];

        // User + action + indefinite article + activity.
        $eventtext .= PHP_EOL . $userlink;
        $eventtext .= get_string('message_' . $eventaction, 'block_glsubs');
        $eventtext .= get_string('message_singular_indefinite_article', 'block_glsubs');
        $eventtext .= get_string('message_' . $eventactivity, 'block_glsubs');

        // If this event is a comment or an entry.
        if ($textevent['event_type'] === 'entry' || $textevent['event_type'] === 'comment') {
            // For + indefinite article + activity.
            $eventtext .= get_string('message_for', 'block_glsubs');
            $eventtext .= get_string('message_singular_indefinite_article', 'block_glsubs');
            if ($textevent['event_type'] === 'comment' || $eventaction === 'updated') {
                $eventtext .= get_string('message_entry', 'block_glsubs');
            } else {
                $eventtext .= get_string('message_glossary', 'block_glsubs');
            }
            if ((int)$textevent['event_author'] > 0) {
                // Written by + author.
                $eventtext .= get_string('message_written_by', 'block_glsubs');
                $eventtext .= $authorlink;
            }
            // On + date.
            $eventtext .= get_string('message_on', 'block_glsubs');
            $eventtext .= $eventdatetime;
        }

        // Add two lines of separation.
        $eventtext .= PHP_EOL . PHP_EOL;

        // If exists, add the comment with the link to the concept.
        if ($textevent['event_type'] === 'comment') {
            $eventtext .= PHP_EOL . get_string('message_' . $eventactivity, 'block_glsubs');
            $eventtext .= ' ';
            $eventtext .= get_string('message_' . $eventaction, 'block_glsubs') . ' : ';
            // Add the comment with the Moodle link.
            $eventurl = $textevent['event']->get_url();
            $eventurl->param('mode', 'entry');
            $eventurl->param('hook', (int)$eventdata['other']['itemid']);
            $eventtext .= html_writer::link($eventurl, $textevent['event_comment']);
        }

        // If exists, add concept.
        if ($textevent['event_type'] === 'entry' || $textevent['event_type'] === 'comment') {
            $eventtext .= PHP_EOL . get_string('message_entry', 'block_glsubs') . '[';
            if ($textevent['event_type'] === 'comment') {
                $eventtext .= $textevent['event_item']->concept;
            } else {
                $eventtext .= html_writer::link($textevent['event']->get_url(), $textevent['event_item']->concept);
            }
            $eventtext .= ']';
        }

        // Add categories.
        if ($textevent['event_categories'] > '') {
            $eventtext .= PHP_EOL . get_string('message_category', 'block_glsubs') . ' ';
            if ($textevent['event_type'] !== 'category') {
                $eventtext .= $textevent['event_categories'];
            } else {
                $eventurl = $textevent['event']->get_url();
                $eventurl->param('mode', 'cat');
                $eventurl->param('hook', 'ALL');
                $eventtext .= html_writer::link($eventurl, $textevent['event_categories']);
            }

            $eventtext .= ' ';
        }

        // If exists, add definition.
        if ($textevent['event_type'] === 'entry' || $textevent['event_type'] === 'comment') {
            $eventtext .= PHP_EOL . get_string('message_definition', 'block_glsubs') . '[';
            $eventtext .= $textevent['event_item']->definition . ']';
        }

        // If exists, add author.
        if ($authorlink > '') {
            $eventtext .= PHP_EOL . get_string('message_author', 'block_glsubs');
            $eventtext .= $authorlink;
        }

        // Show activity of the event.
        $eventtext .= PHP_EOL;
        if ('created' === $eventdata['action']) {
            // If there is an auto subscription choice , inform about it.
            if ($autosubscribe) {
                $eventtext .= get_string('glossarysubscriptionon', 'block_glsubs');
            }
            // Else state the subscribers will be informed.
        } else if ('updated' === $eventdata['action'] || ($textevent['event_type'] === 'comment'
                && 'deleted' === $eventdata['action'])) {
            $eventtext .= get_string('glossarysubscriptionsupdated', 'block_glsubs');
        } else if ('deleted' === $eventdata['action']) {
            $eventtext .= get_string('glossarysubscriptionsdeleted', 'block_glsubs');
        }
        $eventtext .= $eventdata['target'];

        // Add HTML line breaks.
        $eventtext = nl2br($eventtext);

        // Send it back.
        return $eventtext;
    }


    /****************************************************************
     *
     * Method:       static check_user_subscription
     *
     * Purpose:      Create a main subscription record for the event user
     *               based on the block settings for
     *               automated subscriptions
     *               The default is to create user subscription records
     *
     * Parameters:   autosubscribe setting, user id , glossary id
     * (category, entry, comment)
     *
     * Returns:      the HTML text for the event
     ****************************************************************/
    /**
     * @param $autosubscribe
     * @param $userid
     * @param $glossaryid
     */
    protected static function check_user_subscription($autosubscribe, $userid, $glossaryid) {
        global $DB;
        try {
            $recexists =
                $DB->record_exists('block_glsubs_glossaries_subs', array('userid' => (int)$userid, 'glossaryid' => $glossaryid));
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs check user subscription record exists ' . $exception->getMessage(), DEBUG_DEVELOPER);
            $recexists = true; // Trigger an error condition to disable creation of a record while the database is not responding.
        }
        // Check if the user is registered into the glossary subscriptions main table.
        if ($autosubscribe && (!$recexists)) {
            // You must add a subscription record for the user in this main table.
            // In order for the subscriptions logic to work.
            $record = new stdClass();
            $record->userid = (int)$userid; // Specify user id.
            $record->glossaryid = $glossaryid; // Specify glossary id.
            $record->active = 0;               // Specify full subscription.
            $record->newcategories = 0;        // Specify new categories subscription.
            $record->newentriesuncategorised = 0; // Specify new concepts without categories subscription.
            // Insert the main record for th user on the specific glossary.
            try {
                $DB->insert_record('block_glsubs_glossaries_subs', $record);
            } catch (Throwable $exception) {
                debugging('ERROR: glsubs check user subscription ' . $exception->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
}
