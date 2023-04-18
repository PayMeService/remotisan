<?php
namespace PayMe\Remotisan\Models;
use Illuminate\Database\Eloquent\Model;
use PayMe\Remotisan\Exceptions\InvalidStatusException;
use PayMe\Remotisan\ProcessStatuses;

/**
 * Class Execution
 * @package https://github.com/PayMeService/remotisan
 *
 * @property integer    $id
 * @property string     $job_uuid
 * @property string     $server_uuid
 * @property integer    $user_identifier
 * @property string     $command
 * @property string     $parameters
 * @property integer    $process_status
 * @property string     $killed_by
 * @property string     $intended_to_kill_by
 * @property integer    $executed_at
 * @property integer    $finished_at
 */
class Execution extends Model
{
    protected $table = "remotisan_executions";
    protected static $unguarded = true;
    public $timestamps = false;

    /**
     * Update process status with shadow update of finished_at.
     * @param int $status
     * @return void
     */
    public function updateProcessStatus(int $status, bool $save = true): void
    {
        if(!in_array($status, ProcessStatuses::getNotRunningStatusesArray())) {
            throw new InvalidStatusException();
        }

        $this->process_status = $status;
        $this->finished_at    = time();
        if ($save) {
            $this->save();
        }
    }

    /**
     * Facade to set status killed transparently to developers.
     * @return void
     */
    public function markKilled(bool $save = true): void
    {
        $this->killed_by = $this->intended_to_kill_by;
        $this->updateProcessStatus(ProcessStatuses::KILLED, $save);
    }

    /**
     * Facade to set status failed transparently to developers.
     * @return void
     */
    public function markFailed(bool $save = true): void
    {
        $this->updateProcessStatus(ProcessStatuses::FAILED, $save);
    }

    /**
     * Facade to set status killed transparently to developers.
     * @return void
     */
    public function markCompleted(bool $save = true): void
    {
        $this->updateProcessStatus(ProcessStatuses::COMPLETED, $save);
    }

    /**
     * Get execution record by JOB_UUID or null on not found.
     * @param string $uuid
     * @return Execution|null
     */
    public static function getByJobUuid(string $uuid): ?Execution
    {
        return static::query()->where("job_uuid", $uuid)->first();
    }
}
