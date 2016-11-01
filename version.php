<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 19/09/2016
 * Time: 10:28
 *
 * Class block_glsubs
 * @package     block
 * @subpackage  vstest
 * @copywrite   qmul
 * @author      Vasileios Sotiras
 * @license     GNU GPL v3
 */
$plugin = new \stdClass();
$plugin->version    = 2016102813;
$plugin->requires   = 2014051200; // Moodle 2.7.0 is required.
$plugin->component  = 'block_glsubs'; // Full name of the plugin (used for diagnostics)
$plugin->maturity   = MATURITY_ALPHA;
$plugin->cron = 0 ; // not using the old method, but the tasks new method
