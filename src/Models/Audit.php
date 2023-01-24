<?php
namespace PayMe\Remotisan\Models;
use Illuminate\Database\Eloquent\Model;
use PayMe\Remotisan\Exceptions\InvalidStatusException;

/**
 * Class Audit
 * @package https://github.com/PayMeService/remotisan
 *
 * @property integer    id
 * @property integer    pid
 * @property string     uuid
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
    protected $unguarded = true;
    public $timestamps = false;

    /**
     * Update process status with shadow update of finished_at.
     * @param int $status
     * @return void
     */
    public function updateProcessStatus(int $status): void
    {
        if(!in_array($status, ProcessStatuses::getNotRunningStatusesArray())) {
            throw new InvalidStatusException();
        }

        $this->process_status = $status;
        $this->finished_at    = time();
        $this->save();
    }

    /**
     * Get audit record by UUID or null on not found.
     * @param string $uuid
     * @return Audit|null
     */
    static public function getByUuid(string $uuid): ?Audit
    {
        return static::query()->where("uuid", $uuid)->first();
    }
}
