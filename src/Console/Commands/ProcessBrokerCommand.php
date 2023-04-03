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
use PayMe\Remotisan\Exceptions\RecordNotFoundException;
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

    protected Execution $execution;
    protected ?Process $process = null;
    protected ?array $killInstructions = null;
    protected bool $isProcessErroneous = false;

    const SIGNAL_KILLING_NAME = "External kill";

    public function handle(Remotisan $remotisan)
    {
        $execution = Execution::getByJobUuid($uuid = $this->argument("uuid"));
        if (!$execution) {
            throw new RecordNotFoundException($uuid);
        }
        $this->execution = $execution;

        $commandArray = array_merge(
            explode(' ', "php artisan " . $this->execution->command),
            $remotisan->getProcessExecutor()->compileCmdAsEscapedArray($this->execution->parameters)
        );

        $this->runProcess($commandArray);

        if ($this->killInstructions) {
            $this->setKilled();
        } elseif ($this->isProcessErroneous || !$this->process->isSuccessful()) {
            $this->execution->markFailed();
        } else {
            $this->execution->markCompleted();
        }
    }

    /**
     * @param array $commandArray
     */
    protected function runProcess(array $commandArray): void
    {
        $pathToLog = FileManager::getLogFilePath($this->execution->job_uuid);

        $this->process = new Process($commandArray, base_path());
        $this->process->start();
        $sigTime = 0;
        $signals = $this->getSubscribedSignals();

        $this->process->wait(function ($type, $buffer) use ($pathToLog, &$sigTime, &$signals) {
            file_put_contents($pathToLog, $buffer, FILE_APPEND);
            if ($this->process->isRunning() && $this->killInstructions = CacheManager::getKillInstruction($this->execution->job_uuid)) {
                $nowTime = time();
                if($nowTime > $sigTime+5 || $sigTime === 0) {
                    $this->process->signal((!empty($signals) ? array_shift($signals) : SIGHUP));
                    $sigTime = $nowTime;
                }
            }

            if (Process::ERR === $type) {
                $this->isProcessErroneous = true;
            }
        });
    }

    protected function setKilled(): void
    {
        $this->execution->killed_by = $this->killInstructions["name"];

        $killNote = "\n=============
            \nPROCESS KILLED BY {$this->execution->killed_by} AT " . ((string)Carbon::parse()) . " \n";
        file_put_contents(FileManager::getLogFilePath($this->execution->job_uuid), $killNote, FILE_APPEND);

        CacheManager::removeKillInstruction($this->execution->job_uuid);

        $this->execution->markKilled();
    }

    public function getSubscribedSignals(): array
    {
        return [SIGQUIT, SIGINT, SIGTERM, SIGHUP];
    }

    public function handleSignal(int $signal): void
    {
        $this->killInstructions = [
            "name" => self::SIGNAL_KILLING_NAME,
            "time" => time()
        ];

        $this->process->signal($signal);

        $this->setKilled();
    }
}
