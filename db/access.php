<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 26/07/2016
 * Time: 10:35
 */
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

global $CFG;

$capabilities = [
    'block/glsubs:addinstance' => [
        'riskbitmask' => RISK_PERSONAL | RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'user' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
        , 'clonepermissionsfrom' => 'moodle/course:manageblocks'
    ],
    'block/glsubs:myaddinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'user' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ],

        'clonepermissionsfrom' => 'moodle/my:manageblocks'
    ],
];