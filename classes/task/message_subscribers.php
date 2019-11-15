<?php
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
 *
 *
 * // This file is part of Moodle - http://moodle.org/
 * //
 * // Moodle is free software: you can redistribute it and/or modify
 * // it under the terms of the GNU General Public License as published by
 * // the Free Software Foundation, either version 3 of the License, or
 * // (at your option) any later version.
 * //
 * // Moodle is distributed in the hope that it will be useful,
 * // but WITHOUT ANY WARRANTY; without even the implied warranty of
 * // MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * // GNU General Public License for more details.
 * //
 * // You should have received a copy of the GNU General Public License
 * // along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 */


// use this namespace also in the ./db/tasks.php
namespace block_glsubs\task;


class message_subscribers extends \core\task\scheduled_task
{
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
    public function get_name()
    {
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
    public function execute()
    {
        $system_user = $this->system_user();
        $messages_log = $this->get_undelivered_log();
        mtrace('Found some ' . count($messages_log) . ' undelivered glossary subscription messages');
        foreach ($messages_log as $id => $message_log) {
            $message_id = $this->send_message($system_user, $message_log);
            if ($message_id > 0) {
                if (!$this->update_message_log($id)) {
                    mtrace('Will try again next time to update the message log record with ID ' . (string)$id);
                    error_log('ERROR: glsubs failure to update the message log with ID' . (string)$id);
                }
            } else {
                error_log('ERROR: glsubs while sending message for the record with ID ' . (string)$id . ' of the glossary subscriptions log ');
            }
        }
    }

    /**
     * Method           update_message_log
     *
     * Purpose          update the message log entry record to the current status of time delivered
     *
     * @param           $message_id , as the message log record id
     *
     * @return          bool true if sucessful, false in case of an error
     *
     */
    private function update_message_log($message_id)
    {
        global $DB;
        try {
            $record = $DB->get_record('block_glsubs_messages_log', array('id' => (int)$message_id));
            $record->timedelivered = time();
            $DB->update_record('block_glsubs_messages_log', $record, FALSE); // update immediately
        } catch (\Throwable $exception) {
            error_log('ERROR: glsubs while updating the time delivered for the log with record ID ' . (string)$message_id . '  ' . $exception->getMessage());
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Method           send_message
     *
     * Purpose          Create and deliver an HTML Moodle message
     *
     * @param           $system_user , as the sender
     * @param           $log_message , as the event log message object
     *                               containing the recipient and all relevant data
     *
     * @return          the message id in case of success or 0 in case of error
     * @internal param $moodle_message as the structure of setting up the new moodle message
     *
     */
    private function send_message($system_user, $log_message)
    {
        global $DB, $USER;

        $messageHtml = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $log_message->eventtext);
        $messageid = 0;
        $user = NULL;
        try {
            $user = $DB->get_record('user', array('id' => (int)$log_message->userid));
        } catch (\Throwable $exception) {
            error_log('ERROR: glsubs while accessing the database ' . $exception->getMessage());
        }
        if ($user) {
            try {
                // prepare the Moodle message
                $moodle_message = new \core\message\message();
                $moodle_message->component = 'moodle';
                $moodle_message->name = 'instantmessage';
                $moodle_message->userfrom = $system_user;
                $moodle_message->userto = $user;
                $course_rec = $DB->get_records_sql("
SELECT g.course 
FROM {block_glsubs_messages_log} AS ml
JOIN {block_glsubs_event_subs_log} AS sl ON sl.id = ml.eventlogid
JOIN {glossary} AS g ON g.id = sl.glossaryid
WHERE ml.eventlogid = :eventlogid
", ['eventlogid' => (int)$log_message->eventlogid], 0, 1);
                $course_rec = array_shift($course_rec);
                $course_rec = (int)$course_rec['course'];
                $moodle_message->courseid = 0;
                $moodle_message->subject = get_string('pluginname', 'block_glsubs');
                // ignore this $moodle_message->fullmessage = $messageText;
                $moodle_message->fullmessageformat = FORMAT_HTML;
                // set only this field to send HTML, ignore fullmessage , smallmessage , contexturl and contexturlname
                $moodle_message->fullmessagehtml = $messageHtml;
                // ignore this $moodle_message->smallmessage = get_string('messageprovider:glsubs_message','block_glsubs');
                $moodle_message->notification = get_config('block_glsubs', 'messagenotification'); // get the setting for this block
                // ignore this $moodle_message->contexturl = $log_message->elink ;
                // ignore this $moodle_message->contexturlname = get_string('pluginname','block_glsubs');
                $moodle_message->replyto = $USER->email;
                $content = array('*' => array('header' => ' ----- ', 'footer' => ' ---- ')); // Extra content for specific processor
                $moodle_message->set_additional_content('email', $content);
            } catch (\Throwable $exception) {
                error_log(implode("\r\n", (array)$exception->getTrace()));
                error_log('ERROR: glsubs message creation ' . $exception->getMessage());
            }
            try {
                if ($moodle_message) {
                    $messageid = message_send($moodle_message);
                } else {
                    error_log('ERROR: glsubs No message was created for ' . implode("\r\n", (array)$log_message));
                }
            } catch (\Throwable $exception) {
                error_log('ERROR: glsubs while sending a message ' . $exception->getMessage());
                error_log(implode("\r\n", (array)$exception->getTrace()));
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
    private function get_undelivered_log()
    {
        global $DB;
        $def_config = max(50, (int)get_config('block_glsubs', 'messagebatchsize'));
        $message_logs = array();
        $sql = 'SELECT l.id , l.userid , l.eventlogid , l.timecreated , e.eventtext, e.eventlink elink 
FROM {block_glsubs_messages_log} AS l 
JOIN {block_glsubs_event_subs_log} AS e ON e.id = l.eventlogid 
WHERE l.timedelivered IS NULL';
        try {
            $message_logs = $DB->get_records_sql($sql, array(), 0, $def_config);
        } catch (\Throwable $exception) {
            error_log('ERROR: glsubs reading the glossary subscriptions log ' . $exception->getMessage());
        }
        return $message_logs;
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
    private function system_user()
    {
        global $USER;
        $user = new \stdClass();
        $user->email = $USER->email; // : Email address
        $user->firstname = fullname($USER); // : You can put both first and last name in this field.
        $user->lastname = get_string('pluginname', 'block_glsubs'); //
        $user->maildisplay = FALSE; // If you want the email to come from noreply@yourwebsite.com, set this from true parameter to false .
        $user->mailformat = 1; // 0 (zero) text-only emails, 1 (one) for HTML/Text emails.
        $user->id = (int)$USER->id; // : Moodle User ID. If it is for someone who is not a Moodle user, use an invalid ID like -99.
        $user->firstnamephonetic = ''; //
        $user->lastnamephonetic = ''; //
        $user->middlename = ''; //
        $user->alternatename = ''; //
        return $user;
    }
}