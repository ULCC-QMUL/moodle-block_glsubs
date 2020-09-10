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

/****************************************************************
 *
 * File:  blocks/glsubs/classes/block_glsubs.php
 *
 * Purpose:  A class handling the subscriptions web form for a
 * glossary for a specific user
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
 *
 ****************************************************************/

/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 27/09/2016
 * Time: 09:17
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


defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

// Required for assignment of roles to the block.
global $CFG;
require_once($CFG->libdir . '/formslib.php');

class block_glsubs_form extends moodleform {
    private $usersubscriptions;

    /****************************************************************
     *
     * Method:       definition
     *
     * Purpose:      Form definition
     *
     * Parameters:   N/A
     *
     * Returns:      N/A
     ****************************************************************/
    /**
     * Defining the Glossary Subscriptions Form
     *
     * @throws \HTML_QuickForm_Error
     */
    public function definition() {
        global $DB, $COURSE, $USER, $OUTPUT, $_GET;
        // Get course information.
        $courseinfo = get_fast_modinfo($COURSE);

        // Get course module id.
        $cmid = optional_param('id', 0, PARAM_INT);
        $cm = $courseinfo->get_cm($cmid);

        // Get the glossaryid for the database entries queries.
        $glossaryid = $cm->instance;

        // Define subscription structures.
        $this->usersubscriptions = new stdClass();
        $this->user = new stdClass();
        $this->usersubscriptions->full = new stdClass();
        $this->usersubscriptions->full->full = new stdClass();
        $this->usersubscriptions->full->fullnewcat = new stdClass();
        $this->usersubscriptions->full->fullnewconcept = new stdClass();
        $this->usersubscriptions->authors = new stdClass();
        $this->usersubscriptions->categories = new stdClass();
        $this->usersubscriptions->concepts = new stdClass();

        // Store the user id and the glossary id.
        $this->usersubscriptions->userid = $USER->id;
        $this->usersubscriptions->glossaryid = $glossaryid;

        // Initiate a Moodle QuickForm object.
        $mform = &$this->_form;

        // Add user id to the form.
        $mform->addElement('hidden', 'glossary_userid', (int)$this->usersubscriptions->userid);
        $mform->setType('glossary_userid', PARAM_INT);

        // Add glossary id to the form.
        $mform->addElement('hidden', 'glossary_glossaryid', (int)$this->usersubscriptions->glossaryid);
        $mform->setType('glossary_glossaryid', PARAM_INT);

        $mform->addElement('header', 'glossaryuserlinks', get_string('formheader', 'block_glsubs'), array());
        $mform->setType('glossaryuserlinks', PARAM_TEXT);

        // Get user active subscriptions // TBD.
        $this->get_user_subscriptions();

        // Add Full Glossary Subscription Choice and Pointer Link.
        $elementurl = new moodle_url('/mod/glossary/view.php', array('id' => $cmid));
        $elementlink = html_writer::link($elementurl, '&#9658;');
        $label = $elementlink . '&emsp;' . $this->usersubscriptions->full->full->desc;

        // Add # of concepts for the glossary.
        $this->usersubscriptions->full->full->allglossaryentries =
            $DB->count_records('glossary_entries', array('glossaryid' => $glossaryid, 'approved' => 1));
        $label .= " (" . $this->usersubscriptions->full->full->allglossaryentries . ")";

        // Add the full subscription option on the form.
        $mform->addElement('advcheckbox',
                           $this->usersubscriptions->full->full->elementname, $label, '',
                           array('group' => 1, 'margin' => '0'), array(0, 1));
        // Add the default value to an array for the final stage of the form creation.
        $this->usersubscriptions->defaults[$this->usersubscriptions->full->full->elementname] =
            $this->usersubscriptions->full->full->sub;
        $mform->setType($this->usersubscriptions->full->full->elementname, PARAM_INT);

        // Add Glossary Subscription on New Categories, add # of current categories.
        $label = '&emsp; &emsp;' . get_string('newcategoriessubscription', 'block_glsubs') .
            " (" . $DB->count_records('glossary_categories', array('glossaryid' => $glossaryid)) . ")";

        // Add the new categories subscription option on the form.
        $mform->addElement('advcheckbox',
                           $this->usersubscriptions->full->fullnewcat->elementname, $label, '', array('group' => 1), array(0, 1));
        // Add the default value to an array for the final stage of the form creation.
        $this->usersubscriptions->defaults[$this->usersubscriptions->full->fullnewcat->elementname] =
            $this->usersubscriptions->full->fullnewcat->sub;
        $mform->setType($this->usersubscriptions->full->fullnewcat->elementname, PARAM_INT);
        $mform->disabledIf($this->usersubscriptions->full->fullnewcat->elementname,
                           $this->usersubscriptions->full->full->elementname, 'checked');

        // Add Glossary Subscription on New Entries without Categories. Count the uncategorised entries of this glossary.
        $counterrecord = $DB->get_record_sql('SELECT count( DISTINCT id ) entries
FROM {glossary_entries}
WHERE glossaryid = :glossaryid
    AND approved = 1
    AND id NOT IN (SELECT entryid FROM {glossary_entries_categories} ) ', array('glossaryid' => $glossaryid));
        $this->usersubscriptions->full->full->categorisedentries =
            $this->usersubscriptions->full->full->allglossaryentries - (int)$counterrecord->entries;
        $elementurl = new moodle_url('/mod/glossary/view.php', array('id' => $cmid, 'mode' => 'cat', 'hook' => '-1'));
        $elementlink = html_writer::link($elementurl, '&#9658; ');
        $label = $elementlink . '&emsp;';
        $label .= get_string('newuncategorisedconceptssubscription', 'block_glsubs')
            . " (" . ($this->usersubscriptions->full->full->allglossaryentries
                - $this->usersubscriptions->full->full->categorisedentries) . ")";

        // Add the new concepts without category option on the form.
        $mform->addElement('advcheckbox', $this->usersubscriptions->full->fullnewconcept->elementname, $label, '',
                           array('group' => 1), array(0, 1));
        // Add the default value to an array for the final stage of the form creation.
        $this->usersubscriptions->defaults[$this->usersubscriptions->full->fullnewconcept->elementname] =
            $this->usersubscriptions->full->fullnewconcept->sub;
        $mform->setType($this->usersubscriptions->full->fullnewconcept->elementname, PARAM_INT);
        $mform->disabledIf($this->usersubscriptions->full->fullnewconcept->elementname,
                           $this->usersubscriptions->full->full->elementname, 'checked');

        // Add Glossary Authors Subscriptions and links.
        foreach ($this->usersubscriptions->authors as $key => & $authorrecord) {
            $authorrecord->fullname = fullname(\core_user::get_user($authorrecord->id));
            $authorrecord->user = \core_user::get_user($authorrecord->id);
            $authorrecord->entries = $authorrecord->entries;
            $authorrecord->elementname = 'glossary_author_' . $key;
        }

        // Add header for authors.
        $mform->addElement('header', 'glsubs_authors_header',
                           get_string('glossaryauthors', 'block_glsubs'), array('font-weight' => 'bold'));

        // Add authors.
        foreach ($this->usersubscriptions->authors as $key => $author) {
            if (isset($author->id)) {
                // Show only non deleted authors, hide all deleted ones.
                // Create a link with image to the author's profile.
                $text = '';
                $userpicture = $OUTPUT->user_picture($author->user, array('size' => 35));
                $elementurl = new moodle_url('/user/view.php', array('id' => $key));
                $elementlink = html_writer::link($elementurl, $userpicture);
                $text .= $elementlink . " ";

                // Create a link to the author's list of entries in this glossary.
                $elementurl = new moodle_url('/mod/glossary/view.php',
                                             array('id' => $cmid, 'mode' => 'author',
                                                 'sortkey' => 'FIRSTNAME', 'hook' => ($author->fullname)));
                $elementlink = html_writer::link($elementurl, '&#9658;');
                $elementlink = '<span title="' . $author->fullname . '">'
                    . $elementlink . ' ' . $this->ellipsisstring($author->fullname, 25) . '</span>';
                $text .= $elementlink;

                // Create a checkbox for author subscription.
                $mform->addElement('advcheckbox',
                                   $author->elementname,
                                   $text . " (" .
                                   $DB->count_records('glossary_entries',
                                                      array('userid' => $author->id, 'glossaryid' => $glossaryid)) . ")");
                $mform->setType($author->elementname, PARAM_INT);
                // Add the default value to an array for the final stage of the form creation.
                $this->usersubscriptions->defaults[$author->elementname] = $author->sub;
                $mform->disabledIf($author->elementname, $this->usersubscriptions->full->full->elementname, 'checked');
                $text = '';
            }
        }

        // Add link to all authors.
        $elementurl = new moodle_url('/mod/glossary/view.php',
                                     array('id' => $cmid, 'mode' => 'author', 'sortkey' => 'FIRSTNAME', 'hook' => 'ALL'));
        $elementlink = html_writer::link($elementurl,
                                         '&#9658; ' . get_string('glossaryallauthors', 'block_glsubs'));
        $mform->addElement('link', 'allauthorslink', '&emsp;&emsp;' . $elementlink, $elementlink);

        // Show glossary categories.

        // Add Categories header.
        $mform->addElement('header', 'glsubs_categories_header',
                           get_string('glossarycategories', 'block_glsubs'), array('font-weight' => 'bold'));

        // Add categories.
        foreach ($this->usersubscriptions->categories as $key => & $categoryentry) {
            if (isset($categoryentry->id)) {
                if (!isset($categoryentry->entries)) {
                    $categoryentry->entries = 0;
                }

                // Create a link to the author's list of entries in this glossary.
                $elementurl = new moodle_url('/mod/glossary/view.php', array('id' => $cmid, 'mode' => 'cat', 'hook' => $key));
                $elementlink = html_writer::link($elementurl, '&#9658;');
                $text .= $elementlink . '&emsp;' . $this->ellipsisstring($categoryentry->name, 25);
                $text = '<span title="' . $categoryentry->name . '">' . $text . '</span>';

                // Create a checkbox for author subscription.
                $mform->addElement('advcheckbox', $categoryentry->elementname, $text . " (" .
                                                $DB->count_records("glossary_entries_categories",
                                                                   array('categoryid' => $key)) . ")");
                $mform->setType($categoryentry->elementname, PARAM_INT);
                // Add the default value to an array for the final stage of the form creation.
                $this->usersubscriptions->defaults[$categoryentry->elementname] = $categoryentry->sub;
                $mform->disabledIf($categoryentry->elementname, $this->usersubscriptions->full->full->elementname, 'checked');
                $text = '';
            } else {
                // Deleted categories should not be shown.
                $categoryentry = null;
            }
        }

        // Show uncategorised category of entries.
        $elementurl = new moodle_url(
            '/mod/glossary/view.php', array('id' => $cmid, 'mode' => 'cat', 'hook' => '-1'));
        $elementlink = html_writer::link(
            $elementurl, '&#9658;&emsp;' .
            get_string('glossaryuncategorisedconcepts', 'block_glsubs') . " (" .
            ($this->usersubscriptions->full->full->allglossaryentries
                - $this->usersubscriptions->full->full->categorisedentries) . ")");
        if (!isset($text)) {
            $text = '';
        }
        $text .= $elementlink;

        // Create a link for all authors.
        $mform->addElement('link', 'category__none', $text, $elementlink);
        unset($text);

        // Show  Glossary Entries header.
        $mform->addElement(
            'header', 'glsubs_concepts_header',
            get_string('glossaryconcepts', 'block_glsubs'), array('font-weight' => 'bold'));

        // Add concepts and comments checkboxes along with links to their associated categories.
        $loop = $this->usersubscriptions->concepts;

        $commentslabel = get_string('glossarycommentson', 'block_glsubs');

        $pagemode = optional_param('mode', '', PARAM_ALPHANUM);
        $pagehook = optional_param('hook', '', PARAM_RAW);
        $authorids = array();
        if ($pagemode === 'author' && (int)$pagehook === 0) {
            try {
                $authorrecords = $DB->get_records_sql('SELECT id FROM {user}
WHERE concat(firstname , \' \' , lastname) = :fullname ', array('fullname' => $pagehook));
                foreach ($authorrecords as $key => $value) {
                    $pagehook = (int)$key;
                    $authorids[] = $key;
                }
            } catch (Throwable $exception) {
                debugging('ERROR: glsubs definition author records messages ' . $exception->getMessage(), DEBUG_DEVELOPER);
                $pagehook = 0;
            }
        }
        foreach ($loop as $key => & $conceptentry) {
            $conceptentry->elementname = 'glossary_concept_' . $key;
            if (isset($conceptentry->id)
                && ($pagemode == ''
                    || ($pagemode == 'author' && ((int)$pagehook == 0 || $pagehook === 'All'))
                    || ($pagemode == 'author' && in_array((int)$conceptentry->userid, $authorids))
                    || ($pagemode == 'cat' && (int)$pagehook == -1 && !isset($conceptentry->categories))
                    || ($pagemode == 'cat' && isset($conceptentry->categories)
                        && in_array((int)$pagehook, $conceptentry->categories))
                    || ($pagemode == 'entry' && $conceptentry->id == $pagehook)
                )
            ) {
                // Only existing concepts will be shown , all marked for deleteion will not.
                $conceptentry->comment_elementname = 'glossary_comment_' . $key;
                $entryurl = new moodle_url("/mod/glossary/view.php", array('id' => $cmid, 'mode' => 'entry', 'hook' => $key));
                $entrylink = html_writer::link($entryurl, '&#9658;');
                $entrylink .= '&emsp;' . $this->ellipsisstring($conceptentry->concept, 20);
                $entrylink = '<span title="' . $conceptentry->concept . '">' . $entrylink . '</span>';

                // Add concept checkbox.
                $mform->addElement('advcheckbox', $conceptentry->elementname, $entrylink, '', array('group' => 10), array(0, 1));

                // Add the default value to an array for the final stage of the form creation.
                $this->usersubscriptions->defaults[$conceptentry->elementname] = $conceptentry->conceptactive;
                $mform->disabledIf($conceptentry->elementname,
                                   $this->usersubscriptions->full->full->elementname, 'checked');

                // Add comments checkbox.
                $mform->addElement('advcheckbox', $conceptentry->comment_elementname,
                                   $commentslabel . " (" . $conceptentry->commentscounter . ")", '',
                                   array('group' => 10), array(0, 1));

                // Add the default value to an array for the final stage of the form creation.
                $this->usersubscriptions->defaults[$conceptentry->comment_elementname] = $conceptentry->commentsactive;
                $mform->disabledIf($conceptentry->comment_elementname,
                                   $this->usersubscriptions->full->full->elementname, 'checked');

                // Get concept's categories.
                if (isset($conceptentry->categories) && count($conceptentry->categories) > 0) {
                    $linkstext = '';
                    foreach ($conceptentry->categories as $categorykey => & $categoryrecord) {
                        $catname = $this->usersubscriptions->categories[$categoryrecord]->name;
                        $elementurl = new moodle_url('/mod/glossary/view.php',
                                                     array('id' => $cmid, 'mode' => 'cat', 'hook' => $categoryrecord));
                        $categorylink = html_writer::link($elementurl, $catname);
                        $linkstext .= '&ensp;<i>' . $categorylink . '</i>,';
                    }
                    $linkstext = rtrim($linkstext, ',');

                    // Add links to categories if they exist.
                    $linkstext = "(" . count($conceptentry->categories) . ")" . $linkstext;
                    $mform->addElement('link', 'concept_' . $key . '_categories', $linkstext);
                }
                $mform->addElement('html', '<hr style="height:1px!important;
border-color:inherit!important;
display:block !important;
visibility: visible!important;">');
            } else {
                $conceptentry = null;
            }
        }

        // Set the defaults in bulk step to avoid time waste.
        $mform->setDefaults($this->usersubscriptions->defaults);

        // Add form control buttons.
        $this->add_action_buttons(true, 'Save');
    }

    /**
     * Get database recorded subscriptions for the current user
     *
     * @return array
     */
    protected function get_user_subscriptions() {
        global $DB;
        // Full subscription defaults.
        $this->usersubscriptions->full->full->sub = 0;
        $this->usersubscriptions->full->full->desc = get_string('fullsubscription', 'block_glsubs');
        $this->usersubscriptions->full->full->elementname = get_string('glossaryformfullelementname', 'block_glsubs');
        $this->usersubscriptions->full->fullnewcat->sub = 0;
        $this->usersubscriptions->full->fullnewcat->desc = get_string('newcategoriessubscription', 'block_glsubs');
        $this->usersubscriptions->full->fullnewcat->elementname = get_string('glossaryformcategorieselementname', 'block_glsubs');
        $this->usersubscriptions->full->fullnewconcept->sub = 0;
        $this->usersubscriptions->full->fullnewconcept->desc = get_string('newuncategorisedconceptssubscription', 'block_glsubs');
        $this->usersubscriptions->full->fullnewconcept->elementname = get_string('glossaryformconceptselementname', 'block_glsubs');

        // Short name user and glossary id.
        $userid = (int)$this->usersubscriptions->userid;
        $glossaryid = (int)$this->usersubscriptions->glossaryid;

        // Check for current full glossary subscription database entry and update the current data to be presented.
        if ($DB->record_exists('block_glsubs_glossaries_subs', array('userid' => $userid, 'glossaryid' => $glossaryid))) {
            $record = $DB->get_record('block_glsubs_glossaries_subs', array('userid' => $userid, 'glossaryid' => $glossaryid));
            $this->usersubscriptions->full->full->sub = $record->active;
            $this->usersubscriptions->full->fullnewcat->sub = $record->newcategories;
            $this->usersubscriptions->full->fullnewconcept->sub = $record->newentriesuncategorised;
        }

        // Get Authors.
        $this->usersubscriptions->authors =
            $DB->get_records_sql('SELECT userid id, count(userid) entries
FROM {glossary_entries} WHERE glossaryid = ? GROUP BY userid', array($glossaryid));

        // Get user subscriptions to the glossary authors.
        $this->usersubscriptions->authorsSubs =
            $DB->get_records('block_glsubs_authors_subs',
                             array('userid' => $userid, 'glossaryid' => $glossaryid), 'authorid', 'authorid,active');

        foreach ($this->usersubscriptions->authors as $key => & $authorrecord) {
            if (isset($this->usersubscriptions->authorsSubs["$key"])) {
                $authorrecord->sub = (int)$this->usersubscriptions->authorsSubs["$key"]->active;
            } else {
                $authorrecord->sub = 0;
            }
        }
        // Clean up used authors subscription data.
        $this->usersubscriptions->authorsSubs = null;

        // Get Categories.
        $this->usersubscriptions->categories =
            $DB->get_records('glossary_categories', array('glossaryid' => $glossaryid), 'name', 'id,glossaryid,name');

        // Get User Subscriptions to the glossary categories.
        $this->usersubscriptions->categoriesSubs =
            $DB->get_records('block_glsubs_categories_subs',
                             array('userid' => $userid, 'glossaryid' => $glossaryid), 'categoryid', 'categoryid,active');

        // Convert entries values to integers.
        foreach ($this->usersubscriptions->categories as $key => & $categoryentry) {
            $categoryentry->elementname = 'glossary_category_' . $key;
            if (isset($this->usersubscriptions->categoriesSubs["$key"])) {
                $categoryentry->sub = $this->usersubscriptions->categoriesSubs["$key"]->active;
            } else {
                $categoryentry->sub = 0;
            }
        }
        // Remove used data.
        $this->usersubscriptions->categoriesSubs = null;

        // Add the Glossary concepts.
        $this->usersubscriptions->concepts = $DB->get_records('glossary_entries',
                                                              array('glossaryid' => $glossaryid),
                                                              'concept', 'id,concept,userid');

        // Get the concepts' categories set.
        $this->usersubscriptions->conceptsCategories = $DB->get_records_select(
            'glossary_entries_categories',
            'categoryid IN (SELECT id FROM {glossary_categories} WHERE glossaryid = :glossaryid ) ',
            array('glossaryid' => $glossaryid), $sort = 'entryid', $fields = 'id, entryid, categoryid',
            $limitfrom = 0, $limitnum = 0);

        // Store the categories ID set into the concept.
        foreach ($this->usersubscriptions->conceptsCategories as $key => $conceptscategory) {
            $this->usersubscriptions->concepts[(int)$conceptscategory->entryid]->categories[] = (int)$conceptscategory->categoryid;
        }
        // Release memory of concept categories.
        $this->usersubscriptions->conceptsCategories = null;

        // Get the user subscriptions for the glossary.
        $this->usersubscriptions->conceptsSubs = $DB->get_records('block_glsubs_concept_subs',
                                                                  array('userid' => $userid, 'glossaryid' => $glossaryid),
                                                                  'conceptid', 'conceptid,conceptactive,commentsactive');

        // Get concept comments counters.
        $sqlstmt = 'SELECT itemid, count(itemid) counter
FROM {comments} c
JOIN {glossary_entries} ge ON c.itemid = ge.id AND ge.glossaryid = :glossaryid
WHERE commentarea = "glossary_entry"
GROUP BY itemid
ORDER BY itemid';
        $this->usersubscriptions->conceptCounters = $DB->get_records_sql($sqlstmt, array('glossaryid' => $glossaryid));

        // Add comment counters to relevant concepts.
        foreach ($this->usersubscriptions->conceptCounters as $key => $conceptcounter) {
            $this->usersubscriptions->concepts[$key]->commentscounter = $conceptcounter->counter;
        }
        foreach ($this->usersubscriptions->concepts as $key => & $concept) {
            if (!isset($concept->commentscounter)) {
                $concept->commentscounter = 0;
            }
        }
        // Release memory of counters.
        $this->usersubscriptions->conceptCounters = null;

        // Prepare the presentation values for the subscriptions.
        foreach ($this->usersubscriptions->concepts as $key => & $conceptentry) {
            // Take the db subscription values for the concept and its comments.
            if (isset($this->usersubscriptions->conceptsSubs[$key])) {
                $conceptentry->conceptactive = (int)$this->usersubscriptions->conceptsSubs[$key]->conceptactive;
                $conceptentry->commentsactive = (int)$this->usersubscriptions->conceptsSubs[$key]->commentsactive;
            } else {
                $conceptentry->conceptactive = 0;
                $conceptentry->commentsactive = 0;
            }
        }
        $this->usersubscriptions->conceptsSubs = null;
    }

    /****************************************************************
     *
     * Method:       validation
     *
     * Purpose:      Make some default form data vaildation
     *               As there are only advanced check boxes there is
     *              only a need for the basic validation
     *
     * Parameters:   the list of the form check boxes
     *
     * Returns:      true if all check boxes are valid values (0 or 1)
     ****************************************************************/
    /**
     * This method is called after definition(), data submission and set_data().
     *
     * @param array $data
     * @param array $files
     *
     * @return array
     */
    public function validation($data, $files) {
        global $USER;
        $usercontext = context_user::instance($USER->id);
        $errors = parent::validation($data, $files);

        return $errors;
    }

    /****************************************************************
     *
     * Method:       validate_after_data
     *
     * Purpose:      Not Used
     *
     *
     *
     * Parameters:   N/A
     *
     * Returns:      N/A
     ****************************************************************/
    /**
     * Not used
     */
    public function validate_after_data() {
        $mform = &$this->_form;
    }

    /****************************************************************
     *
     * Method:       ellipsisString
     *
     * Purpose:      Create short form of a string including ellipsis (...)
     *              if required. Used for making use in narrow elements of
     *              the web screens
     *
     * Parameters:   the text (any bytes form), the legnth to cut off
     *
     * Returns:      the shortened text version plus optional ellipsis
     ****************************************************************/
    /**
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
     * Parameters:   the text (any bytes form)
     *
     * Returns:      true if at least one character is multi byte
     ****************************************************************/
    /**
     * check for multibyte strings
     *
     * @param $s
     *
     * @return bool
     */
    protected function is_multibyte($s) {
        return mb_strlen($s, 'utf-8') < strlen($s);
    }
}
