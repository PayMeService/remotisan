<?php
namespace PayMe\Remotisan\Models;
use Illuminate\Database\Eloquent\Model;
use PayMe\Remotisan\Exceptions\InvalidStatusException;
use PayMe\Remotisan\ProcessStatuses;

/**
 * Class Audit
 * @package https://github.com/PayMeService/remotisan
 *
 * @property integer    id
 * @property integer    pid
 * @property string     uuid
 * @property string     instance_uuid
 * @property integer    user_identifier
 * @property string     command
 * @property string     parameters
 * @property integer    process_status
 * @property integer    executed_at
 * @property integer    finished_at
 */
class Audit extends Model
{
    protected $table = "remotisan_audit";
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
     * Get audit record by UUID or null on not found.
     * @param string $uuid
     * @return Audit|null
     */
    public static function getByUuid(string $uuid): ?Audit
    {
        return static::query()->where("uuid", $uuid)->first();
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getInstanceUuid(): string
    {
        return $this->instance_uuid;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getProcessStatus(): int
    {
        return $this->process_status;
    }
}
