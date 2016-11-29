<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 14/10/2016
 * Time: 13:41
 *
 *
 * File         blocks/glsubs/db/events.php
 *
 * Purpose      Define the kind of events to monitor
 *              and their associated processing entry methods
 *
 * Input        N/A
 *
 * Output       N/A
 *
 * Notes        This file is used to register the events monitored
 *              by the plugin. Any change requires version upgrade
 *
 * // This file is part of Moodle - http://moodle.org/ * //
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


defined('MOODLE_INTERNAL') || die();


// state all the events required for monitoring in this glossary events subscriptions block
$observers = array(
// avoid monitoring of all events
    array(
        'eventname' =>  '\mod_glossary\event\category_created',
        'callback'  =>  'block_glsubs_observer::observe_all',
        'internal'  =>  true,
    ),
    array(
        'eventname' =>  '\mod_glossary\event\category_deleted',
        'callback'  =>  'block_glsubs_observer::observe_all',
        'internal'  =>  true,
    ),
    array(
        'eventname' =>  '\mod_glossary\event\category_updated',
        'callback'  =>  'block_glsubs_observer::observe_all',
        'internal'  =>  true,
    ),
    array(
        'eventname' =>  '\mod_glossary\event\entry_created',
        'callback'  =>  'block_glsubs_observer::observe_all',
        'internal'  =>  true,
    ),
    array(
        'eventname' =>  '\mod_glossary\event\entry_deleted',
        'callback'  =>  'block_glsubs_observer::observe_all',
        'internal'  =>  true,
    ),
    array(
        'eventname' =>  '\mod_glossary\event\entry_updated',
        'callback'  =>  'block_glsubs_observer::observe_all',
        'internal'  =>  true,
    ),
    array(
        'eventname' =>  '\mod_glossary\event\entry_approved',
        'callback'  =>  'block_glsubs_observer::observe_all',
        'internal'  =>  true,
    ),
    array(
        'eventname' =>  '\mod_glossary\event\entry_disapproved',
        'callback'  =>  'block_glsubs_observer::observe_all',
        'internal'  =>  true,
    ),
    array(
        'eventname' =>  '\mod_glossary\event\comment_created',
        'callback'  =>  'block_glsubs_observer::observe_all',
        'internal'  =>  true,
    ),
    array(
        'eventname' =>  '\mod_glossary\event\comment_deleted',
        'callback'  =>  'block_glsubs_observer::observe_all',
        'internal'  =>  true,
    ),
);