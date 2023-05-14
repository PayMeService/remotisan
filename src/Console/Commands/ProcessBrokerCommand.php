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
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Process\Process;

class ProcessBrokerCommand extends Command implements SignalableCommandInterface
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

    protected bool $isKilled = false;

    protected bool $isErroneous = false;

    protected string $pathToLog;

    protected Remotisan $remotisan;

    protected Process $process;

    protected Execution $executionRecord;

    protected array $killSignalsList = [SIGQUIT, SIGINT, SIGTERM, SIGHUP];

    protected int $recentSignalTime = 0;

    public function handle(Remotisan $remotisan)
    {
        $this->remotisan = $remotisan;
        $this->executionRecord = Execution::getByJobUuid($this->argument("uuid"));
        $this->pathToLog = FileManager::getLogFilePath($this->executionRecord->job_uuid);

        $this->executeProcess($this->executionRecord);
        $this->postExecutionProcessing($this->executionRecord);
    }

    /**
     * Executes the command, first building using buildCommandArray. Writes into log and returns exit code
     *
     * @param   Execution   $executionRecord
     *
     * @return  void
     */
    protected function executeProcess(Execution $executionRecord): void
    {
        $this->initializeProcess($executionRecord);

        try {
            $this->process->start(function ($type) use ($executionRecord) {
                if (Process::ERR === $type) {
                    $this->isErroneous = true;
                }
            });

            while ($this->process->isRunning()) {
                file_put_contents($this->pathToLog, $this->process->getIncrementalOutput(), FILE_APPEND);

                if ($this->process->isRunning() && CacheManager::hasKillInstruction($executionRecord->job_uuid) && $this->recentSignalTime + 5 < time()) {
                    $this->isKilled = true;
                    $this->recentSignalTime = time();
                    $this->process->signal((!empty($this->killSignalsList) ? array_shift($this->killSignalsList) : SIGKILL));
                }
                usleep(100000); // Sleep for 100 milliseconds
            }

        } catch (\Throwable $e) {
            $this->isErroneous = true;
        }
    }

    protected function initializeProcess(Execution $executionRecord): Process
    {
        $this->process = new Process($this->buildCommandArray($executionRecord), base_path());
        $this->process->setTimeout(null);
        return $this->process;
    }

    /**
     * Building array of command for Process to use.
     *
     * @param   Execution   $executionRecord
     *
     * @return  array
     */
    protected function buildCommandArray(Execution $executionRecord): array
    {
        return array_merge(
            explode(' ', "php artisan " . $executionRecord->command),
            $this->remotisan->getProcessExecutor()->compileCmdAsEscapedArray($executionRecord->parameters)
        );
    }

    /**
     * Post Kill actions.
     *      (1) Append kill note to log
     *      (2) remove instruction from cache
     *      (3) mark execution record killed
     *
     * @param   Execution   $executionRecord
     *
     * @return  void
     */
    protected function postKill(Execution $executionRecord): void
    {
        $killNote = "\n=============
            \nPROCESS KILLED BY {$executionRecord->intended_to_kill_by} AT " . ((string)Carbon::parse()) . " \n";
        file_put_contents($this->pathToLog, $killNote, FILE_APPEND);
        CacheManager::removeKillInstruction($executionRecord->job_uuid);
        $executionRecord->markKilled();
    }

    /**
     * Post execution processing responsible for processing exitCode and process results, then act upon it.
     * Flag record killed, failed and completed.
     *
     * @param   Execution   $executionRecord
     *
     * @return void
     */
    protected function postExecutionProcessing(Execution $executionRecord)
    {
        $executionRecord->refresh();
        if ($this->isKilled) {
            $this->postKill($executionRecord);

            return;
        }

        if ($this->isErroneous || !$this->process->isSuccessful()) {
            $executionRecord->markFailed();
        } else {
            $executionRecord->markCompleted();
        }
    }

    /**
     * @return  array
     */
    public function getSubscribedSignals(): array
    {
        return $this->killSignalsList;
    }

    /**
     * @param   int     $signal
     *
     * @return  void
     */
    public function handleSignal(int $signal): void
    {
        $this->isKilled = true;
        $this->process->signal($signal);
        $this->postKill($this->executionRecord);
    }
}
