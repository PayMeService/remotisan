<?php
/**
 * @author Valentin Ruskevych <valentin.ruskevych@payme.io>
 * User: {valentin}
 * Date: {13/03/2023}
 * Time: {14:36}
 */

namespace PayMe\Remotisan\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use PayMe\Remotisan\CacheManager;
use PayMe\Remotisan\FileManager;
use PayMe\Remotisan\Models\Execution;
use PayMe\Remotisan\Remotisan;
use Symfony\Component\Process\Process;

class ProcessBrokerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "remotisan:broker {uuid}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Remotisan's jobs broker";

    public function handle(Remotisan $remotisan)
    {
        $isProcessKilled = false;
        $executionRecord = Execution::getByJobUuid($this->argument("uuid"));
        $commandArray = array_merge(
            explode(' ', $executionRecord->command),
            $remotisan->getProcessExecutor()->compileCmdAsEscapedArray($executionRecord->parameters)
        );

        $process = new Process($commandArray);
        $process->start();

        while ($process->isRunning()) {
            $killInstruction = CacheManager::hasKillInstruction($executionRecord->job_uuid);
            if($killInstruction) {
                $process->signal(9);
                $isProcessKilled = true;
            }
            sleep(5);
        }

        $executionRecord->refresh();

        if ($isProcessKilled) {
            $killNote = "\nPROCESS KILLED BY {$executionRecord->killed_by} AT " . ((string)Carbon::parse()) . " \n";
            $remotisan->getProcessExecutor()->appendInputToFile(FileManager::getLogFilePath($executionRecord->job_uuid), $killNote);
            CacheManager::removeKillInstruction($executionRecord->job_uuid);
            $executionRecord->markKilled();

            return;
        }

        (!$process->isSuccessful() ? $executionRecord->markFailed() : $executionRecord->markCompleted());
    }
}
