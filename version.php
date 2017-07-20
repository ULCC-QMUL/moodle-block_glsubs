<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 19/09/2016
 * Time: 10:28
 *
 * File:     blocks/glsubs/version.php
 *
 * Purpose:  version control of required Moodle platform, othe plugins and its current version
 *
 * Input:    N/A
 *
 * Output:   N/A
 *
 * Notes:    Keeping track of block version, and platform and other plugins required versions
 *          it is not using the old cron style tasks, so $plugin->cron is set to 0
 *
 * Class block_glsubs
 * @package     block
 * @subpackage  vstest
 * @copywrite   qmul
 * @author      Vasileios Sotiras
 * @license     GNU GPL v3
 */

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

$plugin = new \stdClass();
$plugin->version    = 2017072000;
$plugin->requires   = 2015111604; // Moodle 3.0.4 is required.
$plugin->component  = 'block_glsubs'; // Full name of the plugin (used for diagnostics)
$plugin->maturity   = MATURITY_STABLE;
$plugin->cron = 0 ; // not using the old method, but the tasks new method
