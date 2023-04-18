<?php
namespace PayMe\Remotisan;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use PayMe\Remotisan\Exceptions\RecordNotFoundException;
use PayMe\Remotisan\Exceptions\UnauthenticatedException;
use PayMe\Remotisan\Models\Execution;

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
            throw new \RuntimeException("command '{$command}' not allowed");
        }

        $commandData->checkExecute($this->getUserGroup());
        $uuid = Str::uuid()->toString();
        Execution::create([
            "job_uuid"      => $uuid,
            "server_uuid"   => FileManager::getServerUuid(),
            "executed_at"   => time(),
            "command"       => $command,
            "parameters"    => $params,
            "user_identifier"=> $this->getUserIdentifier(),
            "process_status"=> ProcessStatuses::RUNNING,
        ]);

        $this->processExecutor->execute($uuid, FileManager::getLogFilePath($uuid));

        return $uuid;
    }

    /**
     * Send kill signal IF(!) the process belongs to different instance, otherwise - send SIGKILL directly.
     *
     * @param   string  $uuid
     * @return  string
     * @throws  \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function sendKillSignal(string $uuid): string
    {
        $executionRecord = null;

        if(config("remotisan.allow_process_kill", false) === true) {
            $executionRecord = Execution::getByJobUuid($uuid);
        }

        if (!$executionRecord) {
            throw new RecordNotFoundException("Action Not Allowed.", 404);
        }

        if ($executionRecord->user_identifier != $this->getUserIdentifier() && !$this->isSuperUser()) {
            throw new UnauthenticatedException("Action Not Allowed.", 401);
        }

        if ($executionRecord->process_status !== ProcessStatuses::RUNNING) {
            throw new UnauthenticatedException("Action Not Allowed.", 422);
        }

        $executionRecord->intended_to_kill_by = $this->getUserIdentifier();
        $executionRecord->save();

        CacheManager::addKillInstruction($uuid);

        return $uuid;
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
     * @return  string|null
     */
    public function getUserGroup(): ?string
    {
        $request = Request::instance();

        return collect(static::$authWith)
                   ->first(function (array $roleData) use ($request) {
                       return call_user_func_array($roleData["callable"], [$request]);
                   })["role"] ?? null;
    }

    /**
     * @return  void
     * @throws  UnauthenticatedException
     */
    public function requireAuthenticated(): void
    {
        if (! $this->getUserGroup()) {
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
