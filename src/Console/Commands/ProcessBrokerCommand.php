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
use PayMe\Remotisan\Interactive\PromptChannel;
use PayMe\Remotisan\Interactive\PromptProtocol;
use PayMe\Remotisan\Models\Execution;
use PayMe\Remotisan\Remotisan;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class ProcessBrokerCommand extends Command implements SignalableCommandInterface
{

    const SHOULD_NOT_STOP_ERROR_MESSAGES_REMOVE = [
        "Xdebug: *.\n",
    ];

    const SHOULD_NOT_STOP_ERROR_MESSAGES = [
        "*/* [*] *%*", // Laravel Progress Bar
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

    /**
     * The child's stdin, kept open for the whole run so answers can be fed in live.
     */
    protected InputStream $stdin;

    protected PromptChannel $promptChannel;

    /**
     * @var array{id: string, question: string, secret: bool, multiline: bool, default: ?string, choices: ?array<int, string>}|null
     *      the question the child is blocked on, if any
     */
    protected ?array $pendingPrompt = null;

    /**
     * @param   Remotisan   $remotisan
     *
     * @return  int
     */
    public function handle(Remotisan $remotisan): int
    {
        $this->remotisan = $remotisan;
        $uuid            = (string)$this->argument("uuid");
        $executionRecord = Execution::getByJobUuid($uuid);

        // The broker is spawned by the request that created the record, so a missing record here
        // means the execution is unrunnable - there is nothing to report status on. Bail out with
        // a failing exit code instead of letting a null land on the typed property below, which
        // used to raise a TypeError and surface as a fatal error.
        if (!$executionRecord) {
            $this->error("Execution record not found for uuid {$uuid}");

            return self::FAILURE;
        }

        $this->executionRecord = $executionRecord;
        $this->pathToLog       = FileManager::getLogFilePath($this->executionRecord->job_uuid);

        $this->executeProcess();
        $this->postExecutionProcessing();

        return self::SUCCESS;
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
            // Start callback is being called only when new output arrived.
            // If no output for an hour because of long query, for example, we won't have the start callback called, and cannot kill inside.
            $this->process->start(function ($type, $data) {
                if (Process::ERR === $type) {
                    foreach (self::SHOULD_NOT_STOP_ERROR_MESSAGES_REMOVE as $remove) {
                        $remove = preg_quote($remove, '/');
                        $remove = str_replace('\*', '.*', $remove);

                        // Use regular expression to replace in the subject
                        $data = preg_replace('/' . $remove . '/', '', $data);
                    }

                    $trimData = trim($data);
                    if ($trimData != "") {
                        if (Str::is(self::SHOULD_NOT_STOP_ERROR_MESSAGES, Str::before($trimData, "\n"))) {
                            file_put_contents($this->pathToLog, $trimData."\n", FILE_APPEND);
                        } else {
                            $this->isErroneous  = true;
                            $this->errorMessage = $data;
                        }
                    }
                } else {
                    $this->handleOutput($data);
                }
            });

            // Iterates if its still running OR the process already stopped and has unhandled output
            while (($output = $this->process->getIncrementalOutput()) || $this->process->isRunning() && !$this->isErroneous) {
                if ($this->process->isRunning() && CacheManager::hasKillInstruction($this->executionRecord->job_uuid) && $this->recentSignalTime + 5 < time()) {
                    $this->isKilled = true;
                    $this->recentSignalTime = time();
                    $this->process->signal((!empty($this->killSignalsList) ? array_shift($this->killSignalsList) : SIGKILL));
                }
                $this->deliverAnswer();
                usleep(100000); // Sleep for 100 milliseconds
            }

        } catch (\Throwable $e) {
            $this->isErroneous = true;
            $this->errorMessage = $e->getMessage();
        } finally {
            // Whatever ended the run, nobody may answer a prompt that no process is waiting on.
            $this->stdin->close();
            $this->promptChannel->clearPrompt($this->executionRecord->job_uuid);
            $this->pendingPrompt = null;
        }
    }

    /**
     * Route one chunk of the child's stdout: prompt markers become a pending prompt, the rest
     * goes to the log file as before.
     *
     * @param   string  $data
     *
     * @return  void
     */
    protected function handleOutput(string $data): void
    {
        [$output, $prompts] = PromptProtocol::extractPrompts($data);

        // A chunk that carried only markers adds nothing to the log.
        if ($prompts === [] || trim($output) !== "") {
            file_put_contents($this->pathToLog, trim($output)."\n", FILE_APPEND);
        }

        foreach ($prompts as $prompt) {
            $this->openPrompt($prompt);
        }
    }

    /**
     * Show the question in the log and offer it to the browser.
     *
     * @param   array<string, mixed>  $prompt
     *
     * @return  void
     */
    protected function openPrompt(array $prompt): void
    {
        $line = "? " . $prompt["question"];

        if (!empty($prompt["choices"])) {
            $line .= " [" . implode("/", $prompt["choices"]) . "]";
        }

        if (($prompt["default"] ?? null) !== null) {
            $line .= " (default: {$prompt["default"]})";
        }

        file_put_contents($this->pathToLog, $line."\n", FILE_APPEND);

        $this->pendingPrompt = $prompt;
        $this->promptChannel->publishPrompt($this->executionRecord->job_uuid, $prompt);
    }

    /**
     * Feed the user's answer to the child once it arrives.
     *
     * The answer only ever exists in memory and on the child's stdin. The log gets an echo of it,
     * masked for a secret question - a fixed mask, so not even the length leaks.
     *
     * @return  void
     */
    protected function deliverAnswer(): void
    {
        if ($this->pendingPrompt === null || !$this->process->isRunning()) {
            return;
        }

        $answer = $this->promptChannel->pullAnswer($this->executionRecord->job_uuid, $this->pendingPrompt["id"]);

        if ($answer === null) {
            return;
        }

        $echo = $this->pendingPrompt["secret"] ? "********" : $answer;
        file_put_contents($this->pathToLog, "> {$echo}\n", FILE_APPEND);

        $this->promptChannel->clearPrompt($this->executionRecord->job_uuid);
        $this->pendingPrompt = null;

        $this->stdin->write(PromptProtocol::encodeAnswer($answer));
    }

    protected function initializeProcess(): Process
    {
        $this->promptChannel = app(PromptChannel::class);
        $this->stdin         = new InputStream();

        // The flag makes the child route its questions over this pipe (RemotisanOutputStyle);
        // the env is merged onto the inherited one, not replacing it.
        $this->process = new Process($this->buildCommandArray(), base_path(), [PromptProtocol::ENV_FLAG => "1"]);
        $this->process->setTimeout(null);
        $this->process->setInput($this->stdin);
        return $this->process;
    }

    /**
     * Building array of command for Process to use.
     *
     * @return array
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
            \nPROCESS KILLED BY {$this->executionRecord->intended_to_kill_by} AT " . (now()->toString()) . " \n";
        file_put_contents($this->pathToLog, $killNote, FILE_APPEND);
        CacheManager::removeKillInstruction($this->executionRecord->job_uuid);
        $this->executionRecord->markKilled();
        event(new ExecutionKilled($this->executionRecord));
    }

    protected function postFailed(): void
    {
        $this->executionRecord->markFailed();
        event(new ExecutionFailed(
            $this->executionRecord,
            $this->errorMessage ?? ($this->process->getExitCodeText() . " - " . trim($this->process->getOutput()))
        ));
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

        if ($this->process->hasBeenSignaled()) {
            $this->postKill();
        } elseif ($this->isErroneous || !$this->process->isSuccessful()) {
            $this->postFailed();
        } else {
            $this->postCompleted();
        }

        app()->terminating(function () {
            $this->executionRecord->refresh();

            if ($this->executionRecord->isRunning()) {
                $this->errorMessage = app()->has('lastException') ? app('lastException')->getMessage() : "Unknown error";
                $this->postFailed();
            }
        });
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
     * @param   int|false $previousExitCode
     *
     * @return  int|false
     */
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->isKilled = true;

        // Signals are subscribed before handle() runs, so a signal may arrive while the process
        // and the execution record are not set up yet. Nothing to kill or flag in that case.
        if (!isset($this->process) || !isset($this->executionRecord)) {
            return false;
        }

        $this->process->signal($signal);
        $this->postKill();

        return false;
    }
}
