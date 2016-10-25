<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 24/10/2016
 * Time: 16:40
 */
namespace block_glsubs\task ;

$tasks = array(
    array(
        'classname' => 'block_glsubs\task\find_subscribers' ,
        'blocking'  => 0 ,
        'minute'    => '*/5' ,
        'hour'      => '*' ,
        'day'       => '*' ,
        'dayofweek' => '*' ,
        'month'     => '*'
    ),
);
