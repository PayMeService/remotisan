<?php
/**
 * @author Valentin Ruskevych <valentin.ruskevych@payme.io>
 * User: {valentin}
 * Date: {09/03/2023}
 * Time: {12:29}
 */

namespace PayMe\Remotisan\Console\Commands;

use Illuminate\Console\Command;
use PayMe\Remotisan\Models\Audit;

class CompletionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "remotisan:complete {uuid}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Marks a remotisan job as completed.";

    /**
     * Command initialization
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * The command execution handler.
     *
     * @return void
     */
    public function handle()
    {
        $auditRecord = Audit::getByUuid($this->argument("uuid"));
        $auditRecord->markCompleted();
    }
}
