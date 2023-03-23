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
        $isProcessErroneous = false;
        $executionRecord = Execution::getByJobUuid($this->argument("uuid"));
        $pathToLog = FileManager::getLogFilePath($executionRecord->job_uuid);
        $commandArray = array_merge(
            explode(' ', "php artisan " . $executionRecord->command),
            $remotisan->getProcessExecutor()->compileCmdAsEscapedArray($executionRecord->parameters)
        );

        $process = new Process($commandArray, base_path());
        $process->start();

        $exitCode = $process->wait(function ($type, $buffer) use (&$isProcessKilled, &$isProcessErroneous, $executionRecord, $pathToLog, &$process) {
            file_put_contents($pathToLog, $buffer, FILE_APPEND);
            if ($process->isRunning() && CacheManager::hasKillInstruction($executionRecord->job_uuid)) {
                $isProcessKilled = true;
                $process->signal(9);
            }

            if (Process::ERR === $type) {
                $isProcessErroneous = true;
            }
        });

        $executionRecord->refresh();

        if ($isProcessKilled) {

            $killNote = "\n=============
            \nPROCESS KILLED BY {$executionRecord->killed_by} AT " . ((string)Carbon::parse()) . " \n";
            file_put_contents($pathToLog, $killNote, FILE_APPEND);
            CacheManager::removeKillInstruction($executionRecord->job_uuid);
            $executionRecord->markKilled();

            return;
        }

        if (($isProcessErroneous && $exitCode != 0) || !$process->isSuccessful()) {
            $executionRecord->markFailed();
        } else {
            $executionRecord->markCompleted();
        }
    }
}
