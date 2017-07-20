<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 14/10/2016
 * Time: 14:26
 *
 *
 * File         blocks/glsubs/db/unistall.php
 *
 * Purpose      Actions to take care when uninstalling this plugin block
 *
 * Input        N/A
 *
 * Output       N/A
 *
 * Notes        there are no formal requirements defined for the unistall process,
 *              keeping everything recorded in tact for the next installation of the plugin
 *
 * @package    glsubs*
 * @copyright  2013 QM+ Queen Mary University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *  This file is part of Moodle - http://moodle.org/
 *
 *  Moodle is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  Moodle is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 */

/****************************************************************

Function:     xmldb_block_glsubs_uninstall

Purpose:      Uninstall actions

Parameters:   N/A

Returns:      true in case of success, false in case of error

 ****************************************************************/
require_once(dirname(__FILE__).'/../../../config.php');

defined('MOODLE_INTERNAL') || die();


function xmldb_block_glsubs_uninstall() {
   global $CFG, $DB;

   $dbman = $DB->get_manager();
	
   $xmlds = $dbman->get_install_xml_schema();

   $xmlds->deleteTable('block_glsubs_authors_subs');
   $xmlds->deleteTable('block_glsubs_categories_subs');
   $xmlds->deleteTable('block_glsubs_concept_subs');
   $xmlds->deleteTable('block_glsubs_event_subs_log');
   $xmlds->deleteTable('block_glsubs_glossaries_subs');
   $xmlds->deleteTable('block_glsubs_messages_log');

   $DB->delete_records('events_handlers',array('component'=>'block_glsubs'));
   $DB->delete_records('block',array('name'=>'glsubs'));
   $DB->delete_records('config_plugins',array('plugin'=>'block_glsubs'));

   return true;
}
