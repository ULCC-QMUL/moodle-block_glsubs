<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 01/11/2016
 * Time: 15:59
 *
 * File         blocks/glsubs/db/messages.php
 *
 * Purpose      Register this block as a message sender
 *
 * Input        N/A
 *
 * Output       N/A
 *
 * Notes        This file is used to register the block as message sender
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


/**
 * Defines message providers (types of messages being sent)
 *
 * @package block_glsubs
 * @copyright  2010 onwards  Aparup Banerjee  http://moodle.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$messageproviders = array (

/// Ordinary single forum posts
    'glsubs_message' => array (
        'defaults' => array(
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF,
            'email' => MESSAGE_PERMITTED,
        ),
    )

);
