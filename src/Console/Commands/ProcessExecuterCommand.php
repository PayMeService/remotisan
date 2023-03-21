<?php
/**
 * @author Valentin Ruskevych <valentin.ruskevych@payme.io>
 * User: {valentin}
 * Date: {13/03/2023}
 * Time: {14:36}
 */

namespace PayMe\Remotisan\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use PayMe\Remotisan\Exceptions\RemotisanException;
use PayMe\Remotisan\Models\Execution;
use PayMe\Remotisan\ProcessExecutor;
use PayMe\Remotisan\Remotisan;
use Symfony\Component\Process\Process;

class ProcessExecuterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "remotisan:execute {uuid}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Remotisan's job killer";

    public function handle(ProcessExecutor $pe)
    {
        $executionRecord = Execution::getByJobUuid($this->argument("uuid"));

        $params  = $pe->escapeParamsString($executionRecord->parameters);

        return Artisan::call($executionRecord->command . " " . $params);
    }
}
