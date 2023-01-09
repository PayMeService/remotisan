<?php
namespace PayMe\Remotisan\Models;
use Illuminate\Database\Eloquent\Model;

/**
 * Class RemotisanAudit
 *
 * @property integer    id
 * @property integer    pid
 * @property string     uuid
 * @property integer    user_name
 * @property string     command
 * @property string     parameters
 * @property integer    executed_at
 */
class RemotisanAudit extends Model
{
    protected $table = "remotisan_audit";
    protected $fillable = [
        "id",
        "pid",
        "uuid",
        "user_name",
        "command",
        "parameters",
        "executed_at",
    ];
    protected $guarded = [];
    public $timestamps = false;
}
