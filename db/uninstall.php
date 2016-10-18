<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 14/10/2016
 * Time: 14:26
 *
 * @package    glsubs
 * @copyright  2013 QM+ Queen Mary University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_block_glsubs_uninstall() {
    // global $DB;

    // Switch the normal glsubs block back on
    // $DB->set_field('block', 'visible', '1', array('name' => 'glsubs'));

    return true;
}
