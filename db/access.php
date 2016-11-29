<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 26/07/2016
 * Time: 10:35
 * File:     blocks/glsubs/db/access.php
 *
 * Purpose:  definitions of capabilities for the glossary subscriptions
 *
 * Input:    N/A
 *
 * Output:   N/A
 *
 * Notes:    Any user can add/see the block


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