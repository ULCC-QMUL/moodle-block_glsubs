<?php
/**
 * Created by PhpStorm.
 * User: vasileios
 * Date: 24/10/2016
 * Time: 16:51
 */

namespace block_glsubs\task;

class find_subscribers extends \core\task\scheduled_task
{
    public function get_name()
    {
        return get_string('findsubscribers','block_glsubs');
    }

    public function execute()
    {
        // TODO: Implement execute() method.
        return false;

    }

}
