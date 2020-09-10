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
 * Date: 08/11/2016
 * Time: 09:19#
 *
 * File:     blocks/glsubs/classes/task/message_subscribers.php
 *
 * Purpose:  Identify undelivered messages for glossary subscribers
 *           and deliver the messages using the Moodle messaging API
 *
 * Input:    N/A
 *
 * Output:   N/A
 *
 * Notes:    Another task for moodle based on the cron/scheduler subsystem
 */


// Use this namespace also in the ./db/tasks.php.
namespace block_glsubs\task;


use Throwable;

class message_subscribers extends \core\task\scheduled_task {
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
        return get_string('messagesubscribers', 'block_glsubs');
    }

    /**
     * Method           execute
     *
     * Purpose          Main entry point for the task to message subscribers for the glossary events
     *
     * @param N/A
     *
     * @return          bool true in case of success, false in case of error
     */
    public function execute() {
        $systemuser = $this->system_user();
        $messageslog = $this->get_undelivered_log();
        mtrace('Found some ' . count($messageslog) . ' undelivered glossary subscription messages');
        foreach ($messageslog as $id => $messagelog) {
            $messageid = $this->send_message($systemuser, $messagelog);
            if ($messageid > 0) {
                if (!$this->update_message_log($id)) {
                    mtrace('Will try again next time to update the message log record with ID ' . (string)$id);
                    debugging('ERROR: glsubs failure to update the message log with ID'
                              . (string)$id, DEBUG_DEVELOPER);
                }
            } else {
                debugging('ERROR: glsubs while sending message for the record with ID '
                          . (string)$id . ' of the glossary subscriptions log ', DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Method           update_message_log
     *
     * Purpose          update the message log entry record to the current status of time delivered
     *
     * @param           $messageid , as the message log record id
     *
     * @return          bool true if sucessful, false in case of an error
     *
     */
    private function update_message_log($messageid) {
        global $DB;
        try {
            $record = $DB->get_record('block_glsubs_messages_log', array('id' => (int)$messageid));
            $record->timedelivered = time();
            $DB->update_record('block_glsubs_messages_log', $record, false); // Update immediately.
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs while updating the time delivered for the log with record ID '
                      . (string)$messageid . '  ' . $exception->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
        return true;
    }

    /**
     * Method           send_message
     *
     * Purpose          Create and deliver an HTML Moodle message
     *
     * @param           $systemuser  , as the sender
     * @param           $logmessage  , as the event log message object
     *                               containing the recipient and all relevant data
     *
     * @return          the message id in case of success or 0 in case of error
     * @internal param $moodle_message as the structure of setting up the new moodle message
     *
     */
    private function send_message($systemuser, $logmessage) {
        global $DB, $USER;

        $messagehtml = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $logmessage->eventtext);
        $messageid = 0;
        $user = null;
        try {
            $user = $DB->get_record('user', array('id' => (int)$logmessage->userid));
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs while accessing the database '
                      . $exception->getMessage(), DEBUG_DEVELOPER);
        }
        if ($user) {
            try {
                // Prepare the Moodle message.
                $moodlemessage = new \core\message\message();
                $moodlemessage->component = 'moodle';
                $moodlemessage->name = 'instantmessage';
                $moodlemessage->userfrom = $systemuser;
                $moodlemessage->userto = $user;
                $courserec = $DB->get_records_sql("
SELECT g.course
FROM {block_glsubs_messages_log} ml
JOIN {block_glsubs_event_subs_log} sl ON sl.id = ml.eventlogid
JOIN {glossary} g ON g.id = sl.glossaryid
WHERE ml.eventlogid = :eventlogid
", ['eventlogid' => (int)$logmessage->eventlogid], 0, 1);
                if ($courserec) {
                    $courserec = array_shift($courserec);
                    $courserec = (int)$courserec->course;
                } else {
                    $courserec = 0;
                }
                $moodlemessage->courseid = $courserec;
                $moodlemessage->subject = get_string('pluginname', 'block_glsubs');
                // Ignore this $moodlemessage->fullmessage = $messageText;.
                $moodlemessage->fullmessageformat = FORMAT_HTML;
                // Set only this field to send HTML, ignore fullmessage , smallmessage , contexturl and contexturlname.
                $moodlemessage->fullmessagehtml = $messagehtml;
                // Ignore this $ moodlemessage->smallmessage = get_string('messageprovider:glsubs_message','block_glsubs');.
                $moodlemessage->notification = get_config('block_glsubs', 'messagenotification');
                // Get the setting for this block.
                // Ignore this $ moodlemessage->contexturl = $log_message->elink ;.
                // Ignore this $ moodlemessage->contexturlname = get_string('pluginname','block_glsubs');.
                $moodlemessage->replyto = $USER->email;
                $content = array('*' => array('header' => ' ----- ', 'footer' => ' ---- '));
                // Extra content for specific processor.
                $moodlemessage->set_additional_content('email', $content);
            } catch (Throwable $exception) {
                debugging(implode("\r\n", (array)$exception->getTrace()), DEBUG_DEVELOPER);
                debugging('ERROR: glsubs message creation ' . $exception->getMessage(), DEBUG_DEVELOPER);
            }
            try {
                if ($moodlemessage) {
                    $messageid = message_send($moodlemessage);
                } else {
                    debugging('ERROR: glsubs No message was created for ' .
                              implode("\r\n", (array)$logmessage), DEBUG_DEVELOPER);
                }
            } catch (Throwable $exception) {
                debugging('ERROR: glsubs while sending a message ' . $exception->getMessage(), DEBUG_DEVELOPER);
                debugging(implode("\r\n", (array)$exception->getTrace()), DEBUG_DEVELOPER);
            }
        }
        return $messageid;
    }

    /**
     * Method          get_undelivered_log
     *
     * Purpose          get the next batch of the undelivered messages in the message log table
     *
     * @param N/A
     *
     * @return          array of message objects to be processed, limited to the amount set in the
     *                        block settings, to avoid overloading of the cron/scheduler subsystem
     *                        of Moodle, taking batche rounds until all messages are delivered
     *
     * Sends the unprocessed message log entries
     * Should put a limit to records retrieved in order to avoid large memory usage and processor overloads
     *
     */
    private function get_undelivered_log() {
        global $DB;
        $defconfig = (int)get_config('block_glsubs', 'messagebatchsize');
        // Keep a minimum batch size of 50 messages.
        $defconfig = ($defconfig < 100) ? 100 : $defconfig;
        $messagelogs = array();
        $sql = 'SELECT l.id , l.userid , l.eventlogid , l.timecreated , e.eventtext, e.eventlink AS elink
FROM {block_glsubs_messages_log} l
JOIN {block_glsubs_event_subs_log} e ON e.id = l.eventlogid
WHERE l.timedelivered IS NULL';
        try {
            $messagelogs = $DB->get_records_sql($sql, array(), 0, $defconfig);
        } catch (Throwable $exception) {
            debugging('ERROR: glsubs reading the glossary subscriptions log ' . $exception->getMessage(), DEBUG_DEVELOPER);
        }
        return $messagelogs;
    }

    /**
     * Method           system_user
     *
     * Purpose          The moodle messaging system requires a sender and a recipient of each message
     *                  This method sets the cron/scheduler subsustem user as the sender.
     *                  This function should return an object of the CRON user modified to
     *                  reflect the current activity
     *
     * @param N/A
     *
     * @return          \stdClass with minimum attributes of a User object
     *
     */
    private function system_user() {
        global $USER;
        $user = new \stdClass();
        $user->email = $USER->email; // Email address.
        $user->firstname = fullname($USER); // : You can put both first and last name in this field.
        $user->lastname = get_string('pluginname', 'block_glsubs'); //
        $user->maildisplay = false;
        // If you want the email to come from noreply@yourwebsite.com, set this from true parameter to false .
        $user->mailformat = 1; // Comment 0 (zero) text-only emails, 1 (one) for HTML/Text emails.
        $user->id = (int)$USER->id; // Moodle User ID. If it is for someone who is not a Moodle user, use an invalid ID like -99.
        $user->firstnamephonetic = '';
        $user->lastnamephonetic = '';
        $user->middlename = '';
        $user->alternatename = '';
        return $user;
    }
}
