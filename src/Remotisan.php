<?php
namespace PayMe\Remotisan;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use PayMe\Remotisan\Exceptions\RecordNotFoundException;
use PayMe\Remotisan\Exceptions\RemotisanException;
use PayMe\Remotisan\Exceptions\UnauthenticatedException;
use PayMe\Remotisan\Interactive\PromptChannel;
use PayMe\Remotisan\Models\Execution;
use RuntimeException;

class Remotisan
{
    private CommandsRepository $commandsRepo;
    /** @var callable[] */

    private static array $authWith = [];

    private ProcessExecutor $processExecutor;

    protected static $userIdentifierGetter;


    /**
     * @param   CommandsRepository  $commandsRepo
     * @param   ProcessExecutor     $processExecutor
     */
    public function __construct(CommandsRepository $commandsRepo, ProcessExecutor $processExecutor)
    {
        $this->commandsRepo = $commandsRepo;
        $this->processExecutor = $processExecutor;
    }

    /**
     * @param   string  $command
     * @param   string  $params
     *
     * @return string
     */
    public function execute(string $command, string $params): string
    {
        if (!$commandData = $this->commandsRepo->find($command)) {
            throw new RuntimeException("command '{$command}' not allowed");
        }

        $commandData->checkExecute($this->getUserRoles());
        $uuid = Str::uuid()->toString();
        Execution::create([
            "job_uuid"        => $uuid,
            "server_uuid"     => FileManager::getServerUuid(),
            "executed_at"     => time(),
            "command"         => $command,
            "parameters"      => $params,
            "user_identifier" => $this->getUserIdentifier(),
            "process_status"  => ProcessStatuses::RUNNING,
        ]);

        $this->processExecutor->execute($uuid, FileManager::getLogFilePath($uuid));

        return $uuid;
    }

    /**
     * Send kill signal IF(!) the process belongs to different instance, otherwise - send SIGKILL directly.
     *
     * @param   string  $uuid
     * @return  string
     */
    public function sendKillSignal(string $uuid): string
    {
        $executionRecord = null;

        if(config("remotisan.allow_process_kill", false) === true) {
            $executionRecord = Execution::getByJobUuid($uuid);
        }

        if (!$executionRecord) {
            throw new RecordNotFoundException();
        }

        if ($executionRecord->user_identifier != $this->getUserIdentifier() && !$this->isSuperUser()) {
            throw new UnauthenticatedException("Action Not Allowed", 401);
        }

        if (!$executionRecord->isRunning()) {
            throw new RemotisanException("Already killed", 422);
        }

        if ($executionRecord->intended_to_kill_by !== null) { // already received signal.
            throw new RemotisanException("Kill already in process", 409);
        }

        $executionRecord->intended_to_kill_by = $this->getUserIdentifier();
        $executionRecord->save();

        CacheManager::addKillInstruction($uuid);

        return $uuid;
    }

    /**
     * The question a running execution is waiting on, as the browser should render it.
     *
     * @param   string  $uuid
     *
     * @return  array{prompt: array<string, mixed>|null, canAnswer: bool, isRunning: bool}
     * @throws  RecordNotFoundException
     */
    public function getPendingPrompt(string $uuid): array
    {
        $executionRecord = Execution::getByJobUuid($uuid);

        if (!$executionRecord) {
            throw new RecordNotFoundException();
        }

        $prompt = $executionRecord->isRunning() ? app(PromptChannel::class)->getPrompt($uuid) : null;

        return [
            "prompt"    => $prompt,
            "canAnswer" => $prompt !== null && $this->canAnswer($executionRecord),
            "isRunning" => $executionRecord->isRunning(),
        ];
    }

    /**
     * Answer the question a running execution is waiting on.
     *
     * Only the user who started the execution (or a super user) may answer: an answer is input to
     * their command, often a secret. The answer is handed to the broker encrypted through the cache
     * and is never persisted or logged here.
     *
     * @param   string  $uuid
     * @param   string  $promptId  must match the pending prompt, so a stale or replayed answer
     *                             can't land on a later question
     * @param   string  $answer
     *
     * @return  void
     * @throws  RecordNotFoundException
     * @throws  UnauthenticatedException
     * @throws  RemotisanException
     */
    public function submitInput(string $uuid, string $promptId, string $answer): void
    {
        $executionRecord = Execution::getByJobUuid($uuid);

        if (!$executionRecord) {
            throw new RecordNotFoundException();
        }

        if (!$this->canAnswer($executionRecord)) {
            throw new UnauthenticatedException("Action Not Allowed", 401);
        }

        if (!$executionRecord->isRunning()) {
            throw new RemotisanException("Execution is not running", 422);
        }

        $channel = app(PromptChannel::class);
        $prompt  = $channel->getPrompt($uuid);

        if (!$prompt || !hash_equals((string)$prompt["id"], $promptId)) {
            throw new RemotisanException("No such pending prompt", 409);
        }

        $channel->submitAnswer($uuid, $promptId, $answer);
    }

    /**
     * @param   Execution  $executionRecord
     *
     * @return  bool
     */
    protected function canAnswer(Execution $executionRecord): bool
    {
        return $executionRecord->user_identifier == $this->getUserIdentifier() || $this->isSuperUser();
    }

    /**
     * @param               $role
     * @param   callable    $callable
     *
     * @return  void
     */
    public function authWith($role, callable $callable): void
    {
        static::$authWith[] = ["role" => $role, "callable" => $callable];
    }

    /**
     * @return  string
     */
    public function getUserIdentifier(): ?string
    {
        return static::$userIdentifierGetter ? call_user_func_array(static::$userIdentifierGetter, [Request::instance()]) : null;
    }

    /**
     * @param   callable    $userIdentifierGetter
     * @return  void
     */
    public function setUserIdentifierGetter(callable $userIdentifierGetter):void
    {
        static::$userIdentifierGetter = $userIdentifierGetter;
    }

    /**
     * The first role the current user matches, in {@see authWith()} registration order.
     *
     * Kept for backwards compatibility; authorization uses {@see getUserRoles()}, since a user can
     * hold several roles.
     *
     * @return  string|null
     */
    public function getUserGroup(): ?string
    {
        return $this->getUserRoles()[0] ?? null;
    }

    /**
     * Every role the current user matches - one per {@see authWith()} registration whose callable
     * returns true - in registration order.
     *
     * @return  array<int, string|int>
     */
    public function getUserRoles(): array
    {
        $request = Request::instance();

        return collect(static::$authWith)
                   ->filter(fn(array $roleData) => (bool)call_user_func_array($roleData["callable"], [$request]))
                   ->pluck("role")
                   ->unique()
                   ->values()
                   ->all();
    }

    /**
     * @return  void
     * @throws  UnauthenticatedException
     */
    public function requireAuthenticated(): void
    {
        if (empty($this->getUserRoles())) {
            throw new UnauthenticatedException();
        }
    }

    /**
     * checks whether current user is super user according to user identifier.
     * Implementer have to be careful configuring super users used identifiers
     *
     * @return  bool
     */
    public function isSuperUser(): bool
    {
        $supers = Arr::wrap(config("remotisan.super_users", []));

        return in_array("*", $supers) || in_array($this->getUserIdentifier(), $supers);
    }

    /**
     * @return  ProcessExecutor
     */
    public function getProcessExecutor(): ProcessExecutor
    {
        return $this->processExecutor;
    }
}
