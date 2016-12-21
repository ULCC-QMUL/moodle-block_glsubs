<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 28/10/2016
 * Time: 15:03
 *
 * File:     blocks/glsubs/lang/en/block_glsubs.php
 *
 * Purpose:  Define the locale language strings used in the plugin code
 *
 * Input:    N/A
 *
 * Output:   N/A
 *
 * Notes:    Any language message for any type of output should be defined here
 *          So it will be easy to be translated in other languages
 *
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

$string['pluginname'] = 'QM+ Glossary Subscriptions';
$string['blockheader'] = 'Glossary Subscriptions';
$string['blockfooter'] = 'User Glossary Subscriptions Preferences Panel';
$string['glsubs:addinstance'] = 'Add a new Glossary Subscriptions block';
$string['glsubs:myaddinstance'] = 'Add a new Glossary Subscriptions block to the Course';

$string['fullsubscription'] = 'Full Subscription';
$string['newcategoriessubscription'] = 'New Categories';
$string['newuncategorisedconceptssubscription'] = 'Uncategorised Concepts';
$string['formheader'] = 'Subscription options';
$string['glossaryauthors'] = 'Authors';
$string['glossarycategories'] = 'Categories';
$string['glossaryconcepts'] = 'Concepts';
$string['glossaryallauthors'] = 'All Authors';
$string['glossaryuncategorisedconcepts'] = 'Uncategorised Concepts';
$string['glossarycommentson'] = '&emsp;Comments ';

$string['glossaryformfullelementname'] = 'glossary_full_subscription';
$string['glossaryformcategorieselementname'] = 'glossary_full_newcat_subscription';
$string['glossaryformconceptselementname'] = 'glossary_full_new_uncategorised_concept';

$string['glossarysubscriptionon'] = 'This user is automatically subscribed to this ';
$string['glossarysubscriptionsdeleted'] = 'All users will automatically be unsubscribed from this ';
$string['glossarysubscriptionsupdated'] = 'All subscribers will automatically be informed for this ';

$string['findsubscribers'] = 'Find Glossary Event Subscribers Task';
$string['messagesubscribers'] = 'Messaging Glossary Event Subscribers Task';

$string['glossary_user'] = ' User ';
$string['glossary_author'] = ' Author ';
$string['glossary_concept'] = ' Concept ';
$string['glossary_concept_definition'] = ' Concept Definition ';
$string['glossary_comment'] = ' Comment ';
$string['glossary_category'] = ' Category ';

$string['CATEGORY_GENERIC'] = '[--generic--] ';

$string['settings_headerconfig'] = 'QM+ Glossary Subscriptions Configuration';
$string['settings_descconfig'] = 'QM+ Glossary Subscription Settings<br/>It is advised to keep the default values.<br/>Only where there is an impact on performance you could try to alter the default behaviour';
$string['settings_autoselfsubscribe'] = 'Auto Self Subscribe';
$string['settings_autoselfsubscribe_desc'] = 'Automatically subscribe oneself when creating any category, concept or comment ?';
$string['settings_messagestoshow'] = 'Recent Messages';
$string['settings_messagestoshow_details'] = 'Choose how many Recent Subscription Messages to present on the block';
$string['settings_pagelayout'] = 'Message Layout';
$string['settings_pagelayout_details'] = 'Choose whether you like a course style page or a pop up window for the messages';
$string['settings_messagebatchsize'] = 'Messaging Batch Size';
$string['settings_messagebatchsize_details'] = 'Set the maximum Messaging Batch Size in records to send on every run';
$string['settings_messagenotification'] = 'Message Notification';
$string['settings_messagenotification_desc'] = 'Choose if you want to send a Message Notification';
$string['settings_no_messages'] = 'No messages shown';
$string['settings_no_deliveries'] = 'No message deliveries';
$string['settings_deliveriesnotification'] = 'Message Notification';
$string['settings_deliveriesnotification_desc'] = 'Choose if you want to send a Message Notification';

$string['view_the_user'] = 'The user ';
$string['view_the_author'] = 'The author ';
$string['view_created'] = ' created ';
$string['view_updated'] = ' updated ';
$string['view_deleted'] = ' deleted ';
$string['view_generic'] = ' a generic ';
$string['view_category'] = ' a category ';
$string['view_concept'] = ' a concept ';
$string['view_on'] = ' for a concept written by ';
$string['view_message_at'] = 'Message delivered at ';
$string['view_acted'] = ' acted on ';
$string['view_when'] = '<strong>When</strong>';
$string['view_by_user'] = '<strong>By user</strong>';
$string['view_on_concept'] = '<strong>On concept</strong>';
$string['view_show_hide'] = '&#9658;&emsp;';
$string['view_show_hide_2'] = '&#9660;&emsp;';

$string['block_found'] = 'Your subscription has ';
$string['block_unread_messages'] = ' new messages';
$string['block_read_messages'] = ' sent messages';
$string['block_could_not_access'] = 'Could not access the ';
$string['block_most_recent'] = ' most recent messages';

$string['messageprovider:glsubs_message'] = ' Glossary Event Message ';
$string['message_singular_indefinite_article'] = ' a ';
$string['message_singular_definite_article'] = ' the ';
$string['message_written_by'] = ' written by ';
$string['message_on'] = ' on ';
$string['message_glossary'] = ' Glossary ';
$string['message_course'] = 'Course ';
$string['message_module'] = 'Module ';
$string['message_entry'] = 'Concept ';
$string['message_category'] = 'Category(ies) ';
$string['message_comment'] = 'Comment ';
$string['message_created'] = ' added ';
$string['message_deleted'] = ' deleted ';
$string['message_updated'] = ' updated ';
$string['message_for'] = ' for ';
$string['message_definition'] = 'Definition ';
$string['message_author'] = 'Author ';

$string['goto_messages'] = 'Go to your messages';
