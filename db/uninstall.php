<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 14/10/2016
 * Time: 14:26
 *
 * @package    glsubs
 * @copyright  2013 QM+ Queen Mary University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

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

 */


function xmldb_block_glsubs_uninstall() {
    // global $DB;

    // Switch the normal glsubs block back on
    // $DB->set_field('block', 'visible', '1', array('name' => 'glsubs'));

    return true;
}
