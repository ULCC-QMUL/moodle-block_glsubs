<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 14/10/2016
 * Time: 13:41
 */

defined('MOODLE_INTERNAL') || die();


// state all the events required for monitoring in this glossary events subscriptions block
$observers = array(
// avoid monitoring of all events
//    array(
//        'eventname'   => '*', // all events
//        'callback'    => 'block_glsubs_observer::observe_all', // class name in the ./classes/observer.php
//        // 'includefile' => null, // no include file is required
//        // 'internal'    => false, // after the transaction is committed
//        // 'priority'    => 9999,  // high priority
//    ),
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