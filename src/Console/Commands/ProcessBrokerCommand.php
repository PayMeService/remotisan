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
use Illuminate\Support\Str;
use PayMe\Remotisan\CacheManager;
use PayMe\Remotisan\Events\ExecutionCompleted;
use PayMe\Remotisan\Events\ExecutionFailed;
use PayMe\Remotisan\Events\ExecutionKilled;
use PayMe\Remotisan\FileManager;
use PayMe\Remotisan\Models\Execution;
use PayMe\Remotisan\Remotisan;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Process\Process;

class ProcessBrokerCommand extends Command implements SignalableCommandInterface
{

    const SHOULD_NOT_STOP_ERROR_MESSAGES = [
        "Xdebug:"
    ];

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
    protected ?string $errorMessage = null;

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

        $this->executeProcess();
        $this->postExecutionProcessing();
    }

    /**
     * Executes the command. Writes into log and returns exit code
     *
     * @return  void
     */
    protected function executeProcess(): void
    {
        $this->initializeProcess();

        try {
            $this->process->start(function ($type, $data) {
                if (Process::ERR === $type && !Str::startsWith($data, self::SHOULD_NOT_STOP_ERROR_MESSAGES)) {
                    $this->isErroneous = true;
                    $this->errorMessage = $data;
                }
            });

            while ($this->process->isRunning() && !$this->isErroneous) {
                file_put_contents($this->pathToLog, $this->process->getIncrementalOutput(), FILE_APPEND);

                if ($this->process->isRunning() && CacheManager::hasKillInstruction($this->executionRecord->job_uuid) && $this->recentSignalTime + 5 < time()) {
                    $this->isKilled = true;
                    $this->recentSignalTime = time();
                    $this->process->signal((!empty($this->killSignalsList) ? array_shift($this->killSignalsList) : SIGKILL));
                }
                usleep(100000); // Sleep for 100 milliseconds
            }

        } catch (\Throwable $e) {
            $this->isErroneous = true;
            $this->errorMessage = $e->getMessage();
        }
    }

    protected function initializeProcess(): Process
    {
        $this->process = new Process($this->buildCommandArray(), base_path());
        $this->process->setTimeout(null);
        return $this->process;
    }

    /**
     * Building array of command for Process to use.
     *
     * @return  array
     */
    protected function buildCommandArray(): array
    {
        return array_merge(
            explode(' ', "php artisan " . $this->executionRecord->command),
            $this->remotisan->getProcessExecutor()->compileCmdAsEscapedArray($this->executionRecord->parameters)
        );
    }

    /**
     * Post Kill actions.
     *      (1) Append kill note to log
     *      (2) remove instruction from cache
     *      (3) mark execution record killed
     *
     * @return  void
     */
    protected function postKill(): void
    {

        $killNote = "\n=============
            \nPROCESS KILLED BY {$this->executionRecord->intended_to_kill_by} AT " . ((string)Carbon::parse()) . " \n";
        file_put_contents($this->pathToLog, $killNote, FILE_APPEND);
        CacheManager::removeKillInstruction($this->executionRecord->job_uuid);
        $this->executionRecord->markKilled();
        event(new ExecutionKilled($this->executionRecord));
    }

    protected function postFailed(): void
    {
        $this->executionRecord->markFailed();
        event(new ExecutionFailed($this->executionRecord, $this->errorMessage));
    }

    protected function postCompleted(): void
    {
        $this->executionRecord->markCompleted();
        event(new ExecutionCompleted($this->executionRecord));
    }

    /**
     * Post execution processing responsible for processing exitCode and process results, then act upon it.
     * Flag record killed, failed and completed.
     *
     * @return void
     */
    protected function postExecutionProcessing(): void
    {
        $this->executionRecord->refresh();

        if ($this->isErroneous || !$this->process->isSuccessful()) {
            $this->postFailed();
        } else {
            $this->postCompleted();
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
        $this->postKill();
    }
}
