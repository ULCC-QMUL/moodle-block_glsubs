<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 24/10/2016
 * Time: 16:40
 *
 *
 * File         blocks/glsubs/db/tasks.php
 *
 * Purpose      Define block related cron/scheduler subsustem tasks to run every two minutes
 *
 * Input        N/A
 *
 * Output       N/A
 *
 * Notes        This file is used to register the block cron/scheduler subsystem tasks
 *              frequencies and entry classes * of execution
 *              Make sure this file and the associated classes are using the same namespace
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
 *
 */


// for tasks the namespace is based on the frankenstyle of plugin type plus underscore plus the plugin name plus backslash plus task
// use this namespace also in the ./classes/task/classname.php
namespace block_glsubs\task ;

$tasks = array(
    array(
        'classname' => 'block_glsubs\task\find_subscribers' ,
        'blocking'  => 0 ,
        'minute'    => '*/2' ,
        'hour'      => '*' ,
        'day'       => '*' ,
        'dayofweek' => '*' ,
        'month'     => '*'
    ),

    array(
        'classname' => 'block_glsubs\task\message_subscribers' ,
        'blocking'  => 0 ,
        'minute'    => '*/2' ,
        'hour'      => '*' ,
        'day'       => '*' ,
        'dayofweek' => '*' ,
        'month'     => '*'
    ),
);
