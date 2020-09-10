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

/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 24/10/2016
 * Time: 16:51
 *
 * File         blocks/glsubs/classes/task/find_subscribers.php
 *
 * Purpose         This plugin manages glossary subscriptions on full scale, new categories and new uncategorised
 * concepts monitoring, new categorised concepts and their associated comments activities. The subscriptions panel is
 * only visible while you are in the view mode of the glossary. In all other pages there only an informing part of the
 * plugin showing its presence in the course or the course module. The next development step is to add settings to this
 * plugin to keep universal configuration values which will be integrated into the plugin logic. Another stp is to
 * create a visual part for some of the user messages, and possibly a page to be able to see the individual messages.
 *
 *                 The messaging system tries to avoid the creation of duplicate messages for the users, by checking
 *                 the
 *                 existence of previous event log id being served for the specific user id, so even if the user is
 *                 qualifying for multiple conditions for the same event (category, author, concept or full
 *                 subscription), only one message is created for the event. The message content is always the same for
 *                 the same event, so there is no risk of failure to deliver all relevant information for the event to
 *                 the subscribing user.
 */

// For tasks the namespace is based on the frankenstyle of plugin type.
// Plus underscore plus the plugin name plus backslash plus task.
// Use this namespace also in the ./db/tasks.php.
namespace block_glsubs\task;

// Use this when the category of the event is generic.
use stdClass;
use Throwable;

define('CATEGORY_GENERIC', get_string('CATEGORY_GENERIC', 'block_glsubs'));
// Define the catered limit of user IDs in the system for assisting in creating unique query ids, one billion I think is fine.
define('MAX_USERS', 1000000000);

/**
 * Class find_subscribers
 *
 * @usage   This plugin manages glossary subscriptions on full scale, new categories and new uncategorised concepts
 *        monitoring, new categorised concepts and their associated comments activities. The subscriptions panel
 *        is only visible while you are in the view mode of the glossary. In all other pages there only an
 *        informing part of the plugin showing its presence in the course or the course module.
 *        The next development step is to add settings to this plugin to keep universal configuration values which
 *        will be integrated into the plugin logic.
 *        Another stp is to create a visual part for some of the user messages, and possibly a page to be able to see
 *        the individual messages.
 *
 *        The messaging system tries to avoid the creation of duplicate messages for the users, by checking the
 *        existence of previous event log id being served for the specific user id, so even if the user is qualifying
 *        for multiple conditions for the same event (category, author, concept or full subscription), only one
 *        message is created for the event. The message content is always the same for the same event, so there is
 *        no risk of failure to deliver all relevant information for the event to the subscribing user.
 *
 * @author  vasileios
 *
 * @package block_glsubs\task
 */
class find_subscribers extends \core\task\scheduled_task {
    /**
     * Method           get_name
     *
     * Purpose          returns the name of the task to be used in the administration pages
     *
     * Parameters       N/A
     *
     * @return          string defined in the language locale
     *
     */
    public function get_name() {
        return get_string('findsubscribers', 'block_glsubs');
    }

    /**
     * Method           delete_invalid_glossary_entries
     *
     * Purpose          We must clean up subscriptions pointing to invalid entries
     *                  delete entries with 0 as glossaryid as they are not valid to process
     *
     * Parameters       N/A
     *
     * @return         true in case of success, false in case of error
     */
    protected function delete_invalid_glossary_entries() {
        global $DB;
        $return = false;
        // Delete entries with 0 as glossaryid as they are not valid to process.
        try {
            $DB->delete_records('block_glsubs_event_subs_log', array('glossaryid' => 0));
            $return = true;
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs There was a database access error while deleting invalid glossary subscriptions '
                      . $exception->getMessage(), DEBUG_DEVELOPER);
        }
        return $return;
    }


    /**
     *
     * Method           find_full_subscriptions
     *
     * Purpose          find full glossary subscriber users to these glossaries
     *                  and register the user id , log entry id pairs
     *                  to be used for message deliveries
     *
     * @param           $timenow   current time stamp, used to restrict processing up to the records
     *                             inserted just up to the initiation of the execution of this script
     *
     * @return          bool true in case of success, false in case of error
     *
     */
    protected function find_full_subscriptions($timenow) {
        global $DB;
        // Get the list of the glossary IDs in these log entries for the full subscribers.
        // Find full glossary subscriber users to these glossaries and register the.
        // User id , log entry id pairs to be used for message deliveries.
        mtrace('Fetching log IDs for the Full Glossary event subscriptions');
        $sql = ' SELECT min(id) AS logid, glossaryid, timecreated, 0 AS full
FROM {block_glsubs_event_subs_log}
WHERE glossaryid > 0 AND processed = 0 AND timecreated < :timenow
GROUP BY userid,glossaryid,eventlink, timecreated ORDER BY timecreated,glossaryid';

        try {
            $fulllogglossaryids = $DB->get_records_sql($sql, array('timenow' => $timenow));
            foreach ($fulllogglossaryids as $logid => & $fulllogglossaryid) {

                $fullglossarysubscriberids =
                    $DB->get_records('block_glsubs_glossaries_subs',
                                     array('glossaryid' => (int)$fulllogglossaryid->glossaryid, 'active' => 1),
                                     'userid', 'userid');
                $records = array();
                foreach ($fullglossarysubscriberids as $key => $glossarysubscriberid) {
                    $filters = array('userid' => (int)$glossarysubscriberid->userid, 'eventlogid' => $logid);

                    // Avoid duplicate messages logged in the system.
                    if (!$DB->record_exists('block_glsubs_messages_log', $filters)) {
                        $record = new stdClass();
                        $record->userid = (int)$glossarysubscriberid->userid;
                        $record->eventlogid = (int)$logid;
                        $record->timecreated = time();
                        $record->timedelivered = null;
                        // Check if we have valid IDs in order to avoid faulty records.
                        if ($record->userid * $record->eventlogid > 0) {
                            $records[] = $record;
                        }
                    }
                }
                if (count($records) > 0) {
                    // Store the new records into the messages log.
                    $DB->insert_records('block_glsubs_messages_log', $records);
                    mtrace('Added ' . count($records) . ' new full subscription messages');
                }

                // Release the memory of the records.
                $records = null;

                // Mark this event as processed for full subscriptions.
                $fulllogglossaryid->full = 1;
            }
            return true;
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs There was a database access error while processing the new full glossary subscriptions '
                      . $exception->getMessage(), DEBUG_DEVELOPER);
        }
        return false;
    }

    /**
     *
     * Method           find_new_uncategorised_subscriptions
     *
     * Purpose          find new uncategorised concept subscriber users to these glossaries
     *                  and register the user id , log entry id pairs
     *                  to be used for message deliveries
     *
     * @param           $timenow   current time stamp, used to restrict processing up to the records
     *                             inserted just up to the initiation of the execution of this script
     *
     * @return          bool true in case of success, false in case of error
     *
     */
    protected function find_new_uncategorised_subscriptions($timenow) {
        global $DB;
        mtrace('Fetching log IDs for the New Glossary Uncategorised Concept subscriptions');
        $rowid = $DB->sql_concat_join("'_'", ['l.id', 'f.userid']);
        // Cater for one billion user IDs , to get unique query IDs.
        // Make sure all subscribers have an entry for the glossary in the main subscriptions table.
        $sql = " SELECT $rowid i, l.id logid , f.userid
FROM {block_glsubs_event_subs_log} l
JOIN {block_glsubs_glossaries_subs} f ON f.glossaryid = l.glossaryid
WHERE l.processed = 0
    AND l.timecreated < :timenow
    AND f.active = 0
    AND f.newentriesuncategorised = 1
    AND l.eventtype = :eventtype
    AND ( l.conceptid IS NOT NULL )
ORDER BY l.id, l.glossaryid, l.conceptid , f.userid";

        try {
            $newuncategorisedconceptlogids = $DB->get_records_sql($sql, array('timenow' => $timenow, 'eventtype' => 'G'));
            $records = array();
            foreach ($newuncategorisedconceptlogids as $logid => $newuncategorisedconceptlogid) {

                $filter = array('userid' => (int)$newuncategorisedconceptlogid->userid,
                    'eventlogid' => (int)$newuncategorisedconceptlogid->logid);
                // Avoid duplicate messages logged in the system.
                if (!$DB->record_exists('block_glsubs_messages_log', $filter)) {
                    $record = new stdClass();
                    $record->userid = (int)$newuncategorisedconceptlogid->userid;
                    $record->eventlogid = (int)$newuncategorisedconceptlogid->logid;
                    $record->timecreated = time();
                    // Check if we have valid IDs in order to avoid faulty records.
                    if ($record->userid * $record->eventlogid > 0) {
                        $records[] = $record;
                    }
                }
            }

            // If there are any new records store them in the message log.
            if (count($records) > 0) {
                // Add the new messages to the message log.
                $DB->insert_records('block_glsubs_messages_log', $records);
                mtrace('Added ' . count($records) . ' new uncategorised concept subscription messages');
            }
            // Clear memory.
            $records = null;
            return true;
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs There was a database access error while processing the glossary new '
                      .'uncategorised concepts subscriptions ' . $exception->getMessage(), DEBUG_DEVELOPER);
        }
        return false;
    }

    /**
     *
     * Method           find_new_categories_subscriptions
     *
     * Purpose          find new categories subscriber users to these glossaries
     *                  and register the user id , log entry id pairs
     *                  to be used for message deliveries
     *
     * @param           $timenow   current time stamp, used to restrict processing up to the records
     *                             inserted just up to the initiation of the execution of this script
     *
     * @return          bool true in case of success, false in case of error
     *
     */
    protected function find_new_categories_subscriptions($timenow) {
        global $DB;
        mtrace('Fetching log IDs for the New Glossary Categories subscriptions');
        // Use the Moodle sql_concat_join function to get unique record IDs via combining IDs from the joining tables.
        $recid = $DB->sql_concat_join("'_'", ['l.id', 'f.userid']);
        $sql = "
SELECT $recid i , l.id logid , f.userid
FROM {block_glsubs_event_subs_log} l
JOIN {block_glsubs_glossaries_subs} f ON f.glossaryid = l.glossaryid
WHERE l.processed = 0
    AND l.categoryid > 0
    AND l.timecreated < :timenow
    AND l.eventtype = :eventtype
    AND ( l.categoryid IS NOT NULL )
    AND f.active = 0
    AND f.newcategories = 1
ORDER BY l.id, l.glossaryid, l.categoryid , f.userid";

        try {
            $newcategorylogids = $DB->get_records_sql($sql, array('timenow' => $timenow, 'eventtype' => 'G'));
            $records = array();
            foreach ($newcategorylogids as $logid => $newcategorylogid) {

                $filter = array('userid' => (int)$newcategorylogid->userid,
                    'eventlogid' => (int)$newcategorylogid->logid);
                // Avoid duplicate messages logged in the system.
                if (!$DB->record_exists('block_glsubs_messages_log', $filter)) {
                    $record = new stdClass();
                    $record->userid = (int)$newcategorylogid->userid;
                    $record->eventlogid = (int)$newcategorylogid->logid;
                    $record->timecreated = time();
                    // Check if we have valid IDs in order to avoid faulty records.
                    if ($record->userid * $record->eventlogid > 0) {
                        $records[] = $record;
                    }
                }
            }
            // If there are any new records store them in the message log.
            if (count($records) > 0) {
                // Add messages to the meesages log.
                $DB->insert_records('block_glsubs_messages_log', $records);
                mtrace('Added ' . count($records) . ' new categories subscription messages');
            }
            // Clear memory.
            $records = null;
            return true;
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs There was a database access error while processing the glossary new categories subscriptions '
                      . $exception->getMessage(), DEBUG_DEVELOPER);
        }
        return false;
    }

    /**
     *
     * Method           find_author_subscriptions
     *
     * Purpose          find author subscriber users to these glossaries
     *                  and register the user id , log entry id pairs
     *                  to be used for message deliveries
     *
     * @param           $timenow   current time stamp, used to restrict processing up to the records
     *                             inserted just up to the initiation of the execution of this script
     *
     * @return          bool true in case of success, false in case of error
     *
     */
    protected function find_author_subscriptions($timenow) {
        global $DB;
        mtrace('Fetching log IDs for the Glossary Author subscriptions');
        // Cater for a lot of user IDs to get unique query IDs.
        $recid = $DB->sql_concat_join("'_'", ['l.id', 'f.userid']);
        $sql = " SELECT $recid AS i , l.id AS logid , a.userid
FROM {block_glsubs_event_subs_log} l
JOIN {block_glsubs_glossaries_subs} f ON f.glossaryid = l.glossaryid
JOIN {block_glsubs_authors_subs} a  ON a.glossaryid = l.glossaryid AND a.authorid = l.authorid
WHERE l.processed = 0
    AND l.authorid > 0
    AND f.active = 0
    AND l.timecreated < :timenow
GROUP BY l.categoryid, l.conceptid
ORDER BY i";

        try {
            $newauthorsubmessages = $DB->get_records_sql($sql, array('timenow' => $timenow));
            $records = array();
            foreach ($newauthorsubmessages as $id => $newauthorsubmessage) {

                $filter = array('userid' => (int)$newauthorsubmessage->userid, 'eventlogid' => (int)$newauthorsubmessage->logid);
                // Avoid duplicate messages logged in the system.
                if (!$DB->record_exists('block_glsubs_messages_log', $filter)) {
                    $record = new stdClass();
                    $record->userid = (int)$newauthorsubmessage->userid;
                    $record->eventlogid = (int)$newauthorsubmessage->logid;
                    $record->timecreated = time();
                    // Check if we have valid IDs in order to avoid faulty records.
                    if ($record->userid * $record->eventlogid > 0) {
                        $records[] = $record;
                    }
                }
            }

            // If there are any new records store them in the message log.
            if (count($records) > 0) {
                // Add messages to the meesages log.
                $DB->insert_records('block_glsubs_messages_log', $records);
                mtrace('Added ' . count($records) . ' authors subscription messages');
            }
            // Clear memory.
            $records = null;

            return true;
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs There was a database access error while processing the glossary authors subscriptions '
                      . $exception->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     *
     * Method           find_category_subscriptions
     *
     * Purpose          find category subscriber users to these glossaries
     *                  and register the user id , log entry id pairs
     *                  to be used for message deliveries
     *
     * @param           $timenow   current time stamp, used to restrict processing up to the records
     *                             inserted just up to the initiation of the execution of this script
     *
     * @return          bool true in case of success, false in case of error
     *
     */
    protected function find_category_subscriptions($timenow) {
        global $DB;
        mtrace('Fetching log IDs for the Glossary Category subscriptions');
        // Cater for a lot of user IDs to get unique query IDs.
        $recid = $DB->sql_concat_join("'_'", ['l.id', 'f.userid']);
        $sql = " SELECT $recid AS i , l.id AS logid , c.userid , l.categoryid , l.conceptid, l.eventtext
FROM {block_glsubs_event_subs_log} l
JOIN {block_glsubs_glossaries_subs} f ON f.userid = l.userid
JOIN {block_glsubs_categories_subs} c ON c.glossaryid = l.glossaryid AND c.categoryid = l.categoryid
WHERE l.processed = 0
    AND l.authorid > 0
    AND l.categoryid > 0
    AND f.active = 0
    AND l.timecreated < :timenow
GROUP BY c.userid , l.categoryid, l.conceptid
ORDER BY i";

        try {
            $newcategorysubmessages = $DB->get_records_sql($sql, array('timenow' => $timenow));
            $records = array();
            foreach ($newcategorysubmessages as $id => $newcategorysubmessage) {

                $filter = array('userid' => (int)$newcategorysubmessage->userid,
                    'eventlogid' => (int)$newcategorysubmessage->logid);
                // Avoid duplicate messages logged in the system.
                if (!$DB->record_exists('block_glsubs_messages_log', $filter)) {
                    $record = new stdClass();
                    $record->userid = (int)$newcategorysubmessage->userid;
                    $record->eventlogid = (int)$newcategorysubmessage->logid;
                    $record->timecreated = time();
                    // Check if we have valid IDs in order to avoid faulty records.
                    if ($record->userid * $record->eventlogid > 0) {
                        $records[] = $record;
                    }
                }
            }

            // If there are any new records store them in the message log.
            if (count($records) > 0) {
                // Add messages to the meesages log.
                $DB->insert_records('block_glsubs_messages_log', $records);
                mtrace('Added ' . count($records) . ' category subscription messages');
            }
            // Clear memory.
            $records = null;

            return true;
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs There was a database access error while processing the glossary category subscriptions '
                      . $exception->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     *
     * Method           find_concept_subscriptions
     *
     * Purpose          find concept subscriber users to these glossaries
     *                  and register the user id , log entry id pairs
     *                  to be used for message deliveries
     *
     * @param           $timenow   current time stamp, used to restrict processing up to the records
     *                             inserted just up to the initiation of the execution of this script
     *
     * @return          bool true in case of success, false in case of error
     *
     */
    protected function find_concept_subscriptions($timenow) {
        global $DB;
        mtrace('Fetching log IDs for the Glossary Concepts subscriptions');
        // Cater for a lot of user IDs to get unique query IDs.
        $recid = $DB->sql_concat_join("'_'", ['l.id', 'f.userid']);
        $sql = " SELECT $recid i , l.id logid , c.userid , c.conceptactive , c.commentsactive
FROM {block_glsubs_event_subs_log} l
JOIN {block_glsubs_glossaries_subs} f ON f.userid = l.userid
JOIN {block_glsubs_concept_subs} c ON c.glossaryid = l.glossaryid AND c.conceptid = l.conceptid
WHERE l.processed = 0
    AND l.authorid > 0
    AND f.active = 0
    AND l.conceptid > 0
    AND (c.conceptactive = 1 OR c.commentsactive = 1)
    AND l.timecreated < :timenow
GROUP BY c.userid , l.categoryid , l.conceptid
ORDER BY i";

        try {
            $newconceptsubmessages = $DB->get_records_sql($sql, array('timenow' => $timenow));
            $records = array();
            foreach ($newconceptsubmessages as $id => $newconceptsubmessage) {

                // Check if we have either concept or comment related active subscription.
                if ((int)$newconceptsubmessage->conceptactive === 1
                    || (int)$newconceptsubmessage->commentsactive === 1) {
                    $filter = array('userid' => (int)$newconceptsubmessage->userid,
                        'eventlogid' => (int)$newconceptsubmessage->logid);
                    // Avoid duplicate messages logged in the system.
                    if (!$DB->record_exists('block_glsubs_messages_log', $filter)) {
                        $record = new stdClass();
                        $record->userid = (int)$newconceptsubmessage->userid;
                        $record->eventlogid = (int)$newconceptsubmessage->logid;
                        $record->timecreated = time();
                        // Check if we have valid IDs in order to avoid faulty records.
                        if ($record->userid * $record->eventlogid > 0) {
                            $records[] = $record;
                        }
                    }
                }
            }
            // If there are any new records store them in the message log.
            if (count($records) > 0) {
                // Add messages to the meesages log.
                $DB->insert_records('block_glsubs_messages_log', $records);
                mtrace('Added ' . count($records) . ' concepts subscription messages');
            }
            // Clear memory.
            $records = null;

            return true;
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs There was a database access error while processing the glossary concept subscriptions '
                      . $exception->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     *
     * Method           remove_deleted_concepts_subscriptions
     *
     * Purpose          find and remove deleted concept subscriber users to these glossaries
     *
     * @param           $timenow   current time stamp, used to restrict processing up to the records
     *                             inserted just up to the initiation of the execution of this script
     *
     * @return          bool true in case of success, false in case of error
     *
     */
    protected function remove_deleted_concepts_subscriptions($timenow) {
        global $DB;
        mtrace('Removing deleted concepts subscriptions');
        try {
            $counter = $DB->count_records('block_glsubs_concept_subs', array('conceptid' => 0));
            // Delete records refering to a non existing concept ID like 0.
            $DB->delete_records('block_glsubs_concept_subs', array('conceptid' => 0));
            mtrace('Erased ' . $counter . ' subscriptions with invalid concept ID');
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs while erasing invalid concept subscriptions' . '  '
                      . $exception->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
        // Get the set of the latest erased glossary concept IDs.
        $sql = 'SELECT DISTINCT t.conceptid
FROM {block_glsubs_concept_subs} t
JOIN {block_glsubs_event_subs_log} l ON t.conceptid = l.conceptid
WHERE t.conceptid > 0
    AND l.processed = 0
    AND l.timecreated < :timenow
    AND t.conceptid NOT IN (SELECT id FROM {glossary_entries})';

        try {
            $deletedconceptids = $DB->get_records_sql($sql, array('timenow' => $timenow));
            $counter = 0;
            foreach ($deletedconceptids as $key => $deletedconceptid) {
                $counter += $DB->count_records('block_glsubs_concept_subs', array('conceptid' => (int)$key));
                $DB->delete_records('block_glsubs_concept_subs', array('conceptid' => (int)$key));
            }

        } catch (Throwable $exception) {
            debugging('ERROR: glsubs There was a database error while removing subscriptions on erased concept IDs '
                      . $exception->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
        return true;
    }

    /**
     *
     * Method           remove_deleted_category_subscriptions
     *
     * Purpose          find and remove deleted categories subscriber users to these glossaries
     *
     * @param           $timenow   current time stamp, used to restrict processing up to the records
     *                             inserted just up to the initiation of the execution of this script
     *
     * @return          bool true in case of success, false in case of error
     *
     */
    protected function remove_deleted_category_subscriptions($timenow) {
        global $DB;
        mtrace('Removing deleted category subscriptions');
        try {
            $counter = $DB->count_records('block_glsubs_categories_subs', array('categoryid' => 0));
            // Delete records refering to a non existing category ID like 0.
            $DB->delete_records('block_glsubs_categories_subs', array('categoryid' => 0));
            mtrace('Erased ' . $counter . ' subscriptions with invalid category ID');
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs while erasing invalid category subscriptions' . '  '
                      . $exception->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
        // Get the set of the latest erased glossary category IDs.
        $sql = ' SELECT DISTINCT t.categoryid
FROM {block_glsubs_categories_subs} t
JOIN {block_glsubs_event_subs_log} l ON t.categoryid = l.categoryid
WHERE t.categoryid > 0
    AND l.processed = 0
    AND l.timecreated < :timenow
    AND t.categoryid NOT IN (SELECT id FROM {glossary_categories})';

        try {
            $deletedcategoryids = $DB->get_records_sql($sql, array('timenow' => $timenow));
            $counter = 0;
            foreach ($deletedcategoryids as $key => $deletedcategoryid) {
                $counter += $DB->count_records('block_glsubs_categories_subs', array('categoryid' => (int)$key));
                $DB->delete_records('block_glsubs_categories_subs', array('categoryid' => (int)$key));
            }

        } catch (Throwable $exception) {
            debugging('ERROR: glsubs There was a database error while removing subscriptions on erased category IDs '
                      . $exception->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
        return true;
    }

    /**
     *
     * Method           remove_deleted_author_subscriptions
     *
     * Purpose          find and remove deleted authors subscriber users to these glossaries
     *
     * @param           $timenow   current time stamp, used to restrict processing up to the records
     *                             inserted just up to the initiation of the execution of this script
     *
     * @return          bool true in case of success, false in case of error
     *
     */
    protected function remove_deleted_author_subscriptions($timenow) {
        global $DB;
        mtrace('Removing deleted authors subscriptions');
        try {
            $counter = $DB->count_records('block_glsubs_authors_subs', array('authorid' => 0));
            // Delete records refering to a non existing author ID like 0.
            $DB->delete_records('block_glsubs_authors_subs', array('authorid' => 0));
            mtrace('Erased ' . $counter . ' subscriptions with invalid author ID');
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs while erasing invalid author subscriptions' . '  '
                      . $exception->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        // Get the set of the latest erased glossary author IDs.
        $sql = 'SELECT l.authorid
FROM {block_glsubs_event_subs_log} l
JOIN {user} u ON u.id = l.authorid
WHERE l.authorid > 0
    AND u.deleted = 1
    AND l.processed = 0
    AND l.timecreated < :timenow
GROUP BY l.authorid ';

        try {
            $deletedauthorids = $DB->get_records_sql($sql, array('timenow' => $timenow));
            $counter = 0;
            foreach ($deletedauthorids as $key => $deletedauthorid) {
                $counter += $DB->count_records('block_glsubs_authors_subs', array('authorid' => (int)$key));
                $DB->delete_records('block_glsubs_authors_subs', array('authorid' => (int)$key));
            }

        } catch (Throwable $exception) {
            debugging('ERROR: glsubs There was a database error while removing subscriptions on erased author IDs '
                      . $exception->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
        return true;
    }

    /**
     *
     * Method           remove_deleted_concept_subscriptions
     *
     * Purpose          find and remove deleted concepts subscriber users to these glossaries
     *
     * @param           $timenow   current time stamp, used to restrict processing up to the records
     *                             inserted just up to the initiation of the execution of this script
     *
     * @return          bool true in case of success, false in case of error
     *
     */
    protected function remove_deleted_concept_subscriptions($timenow) {
        global $DB;
        mtrace('Removing deleted concept subscriptions');
        try {
            // Delete records refering to a non existing concept ID like 0.
            $DB->delete_records('block_glsubs_concept_subs', array('conceptid' => 0));
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs while erasing invalid concept subscriptions' . '  '
                      . $exception->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        // Get the set of the latest erased glossary author IDs.
        $sql = ' SELECT DISTINCT l.conceptid
FROM {block_glsubs_event_subs_log} l
WHERE l.conceptid > 0
    AND l.processed = 0
    AND l.timecreated < :timenow
    AND l.conceptid NOT IN ( SELECT id FROM {glossary_entries} )
ORDER BY l.conceptid';

        try {
            $deletedconceptids = $DB->get_records_sql($sql, array('timenow' => $timenow));
            $counter = 0;
            foreach ($deletedconceptids as $key => $deletedconceptid) {
                $counter += $DB->count_records('block_glsubs_concept_subs', array('conceptid' => (int)$key));
                $DB->delete_records('block_glsubs_concept_subs', array('conceptid' => (int)$key));
            }

        } catch (Throwable $exception) {
            debugging('ERROR: glsubs There was a database error while removing subscriptions on erased concept IDs '
                      . $exception->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        return true;
    }

    /**
     * Method           execute
     *
     * Purpose          Main entry point for the task to find subscribers for the glossary events
     *
     * @param N/A
     *
     * @return          bool true in case of success, false in case of error
     */
    public function execute() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/config.php');
        require_login();
        if ($this->execute_condition()) {
            $errorstatus = false;
            $timenow = time();
            ini_set('max_execution_time', 0);

            // Delete invalid entries.
            $errorstatus = (!$this->delete_invalid_glossary_entries()) || $errorstatus;

            // Now it is clean to read the unprocessed entries.
            try {
                $neweventscounter = $DB->get_record_sql('SELECT COUNT(id) AS entries FROM {block_glsubs_event_subs_log}
WHERE processed = 0 AND timecreated < :timenow ', array('timenow' => $timenow));
                mtrace("There are $neweventscounter->entries unprocessed log entries ");
            } catch (Throwable $exception) {
                debugging('ERROR: glsubs There was a database access error while getting new glossary event log entries '
                          . $exception->getMessage(), DEBUG_DEVELOPER);
                $errorstatus = true;
                $neweventscounter = new stdClass();
                $neweventscounter->entries = 0;
            }
            if (!$errorstatus && $neweventscounter->entries > 0) {
                // Deal with full subscriptins first.
                $errorstatus = (!$this->find_full_subscriptions($timenow)) || $errorstatus;

                // Deal with new categories subscriptions.
                $errorstatus = (!$this->find_new_categories_subscriptions($timenow)) || $errorstatus;

                // Deal with new uncategorised concepts subscriptions.
                $errorstatus = (!$this->find_new_uncategorised_subscriptions($timenow)) || $errorstatus;

                // Get the list of the author IDs from these log entries.
                // Find the glossary authors subscribers that are not having full subscription and.
                // Register user id , log entry id pairs to be used for message deliveries.
                $errorstatus = (!$this->find_author_subscriptions($timenow)) || $errorstatus;

                // Get the list of the category IDs from these log entries.
                // Find the glossary categories subscribers that are not having full subscription and.
                // Register user id, log entry id pairs to be used for message deliveries.
                $errorstatus = (!$this->find_category_subscriptions($timenow)) || $errorstatus;

                // Get the list of the concept IDs from the latest loeg entries.
                // Find the users subscribing to these concepts and / or their comments that are not having full subscription.
                // Register user id , log entry id pairs to be used for message deliveries.
                $errorstatus = (!$this->find_concept_subscriptions($timenow)) || $errorstatus;

                if (!$errorstatus) {
                    mtrace('There were no errors, continuing to remove non existing target subscriptions');
                    // If no errors occured then.
                    // Delete all non existing concept subscriptions.
                    $errorstatus = (!$this->remove_deleted_concepts_subscriptions($timenow)) || $errorstatus;

                    // Delete all non existing category subscriptions.
                    $errorstatus = (!$this->remove_deleted_category_subscriptions($timenow)) || $errorstatus;

                    // Delete all non existing author subscriptions.
                    $errorstatus = (!$this->remove_deleted_author_subscriptions($timenow)) || $errorstatus;

                    // Delete all non existing glossary subscriptions.
                    $errorstatus = (!$this->remove_deleted_concept_subscriptions($timenow)) || $errorstatus;
                }

                // Continue if there is no error and there are unprocessed event log entries.
                if ($errorstatus) {
                    debugging('ERROR: glsubs There was an issue while erasing invalid subscriptions ' . '  ' .
                              $exception->getMessage(), DEBUG_DEVELOPER);
                } else {
                    // Update all events up to now as processed and add time stamp.
                    mtrace('All good, ready to mark all unprocessed events as done');

                    // Db update processed and timeprocessed.
                    try {
                        if ($DB->set_field_select('block_glsubs_event_subs_log', 'timeprocessed', time(),
                                                  ' processed = 0 AND timecreated < :timenow ', array('timenow' => $timenow))) {
                            $DB->set_field_select('block_glsubs_event_subs_log', 'processed', 1,
                                                  ' processed = 0 AND timecreated < :timenow ', array('timenow' => $timenow));
                        }
                        mtrace('Events up to ' . date('c', $timenow) . ' are marked as processed');
                    } catch (Throwable $exception) {
                        debugging('ERROR: glsubs An error occured on updating the glossary event logs as processed,'.
                                  ' will try again next time' . '  ' . $exception->getMessage(), DEBUG_DEVELOPER);
                        return false;
                    }
                }
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     *
     * Method           execute_condition
     *
     * Purpose          Control the execution mode of this task, set it to false to stop it
     *
     * @param N/A
     *
     * @return          bool true for enabled execution, false for disabled execution
     *
     */
    public function execute_condition() {
        return true;
    }

    /**
     * Method           send_messages
     *
     * Purpose          not used, as another task is set to send the messages
     *
     * @param N/A
     *
     * @return          bool true
     */
    public function send_messages() {
        return true;
    }
}
