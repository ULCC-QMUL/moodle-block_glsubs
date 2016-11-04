<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 24/10/2016
 * Time: 16:40
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
);
