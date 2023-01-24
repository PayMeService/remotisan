<?php
/**
 * @author Valentin Ruskevych <valentin.ruskevych@payme.io>
 * User: {valentin}
 * Date: {24/01/2023}
 * Time: {16:22}
 */

namespace PayMe\Remotisan\Models;

class ProcessStatuses
{
    const RUNNING = 1;
    const COMPLETED = 2;
    const FAILED = 3;
    const KILLED = 4;

    static public function getNotRunningStatusesArray(): array
    {
        return [
            ProcessStatuses::COMPLETED,
            ProcessStatuses::FAILED,
            ProcessStatuses::KILLED,
        ];
    }
}
