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
 * @property integer    user_name
 * @property string     command
 * @property string     parameters
 * @property integer    executed_at
 */
class Audit extends Model
{
    protected $table = "remotisan_audit";
    protected $unguarded = true;
    public $timestamps = false;

    static public function updateProcessStatusByUuid(string $uuid, int $status): void
    {
        if(!in_array($status, ProcessStatuses::getValuesAsArray())) {
            throw new InvalidStatusException();
        }
        $record = static::getByUuid($uuid);
        $record->process_status = $status;
        $record->save();
    }

    static public function getByUuid(string $uuid)
    {
        return static::query()->where("uuid", $uuid)->get()->first();
    }
}
