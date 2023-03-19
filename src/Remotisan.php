<?php
namespace PayMe\Remotisan;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use PayMe\Remotisan\Exceptions\RecordNotFoundException;
use PayMe\Remotisan\Exceptions\UnauthenticatedException;
use PayMe\Remotisan\Models\Audit;

class Remotisan
{

    const INSTANCE_VIOLATION_MSG    = "Instance violation";
    const RIGHT_VIOLATION_MSG       = "Rights violation";
    const KILL_FAILED_MSG           = "Kill failed";
    const INSTANCE_UUID_FILE_NAME   = "remotisan_server_guid";

    private CommandsRepository $commandsRepo;
    /** @var callable[] */
    private static array $authWith = [];
    private ProcessExecutor $processExecutor;

    protected static $userIdentifierGetter;

    protected string $instance_uuid = "";

    /**
     * @param CommandsRepository $commandsRepo
     * @param ProcessExecutor    $processExecutor
     */
    public function __construct(CommandsRepository $commandsRepo, ProcessExecutor $processExecutor)
    {
        $this->commandsRepo = $commandsRepo;
        $this->processExecutor = $processExecutor;
    }

    /**
     * @param string $command
     * @param string $params
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
        $pid = $this->processExecutor->execute($command, $params, $uuid, $this->getFilePath($uuid));
        Audit::create([
            "pid"           => (int)$pid,
            "job_uuid"      => $uuid,
            "instance_uuid" => $this->getInstanceUuid(),
            "executed_at"   => time(),
            "command"       => $command,
            "parameters"    => $params,
            "user_identifier"=> $this->getUserIdentifier(),
            "process_status"=> ProcessStatuses::RUNNING,
        ]);

        return $uuid;
    }

    /**
     * Send kill signal IF(!) the process belongs to different instance, otherwise - send SIGKILL directly.
     * @param string $uuid
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function sendKillSignal(string $uuid): string
    {
        $auditRecord = null;

        if(config("remotisan.allow_process_kill", false) === true) {
            $auditRecord = Audit::getByUuid($uuid);
        }

        if (!$auditRecord) {
            throw new RecordNotFoundException("Action Not Allowed.", 404);
        }

        if ($auditRecord->user_identifier != $this->getUserIdentifier() && !$this->isSuperUser()) {
            throw new UnauthenticatedException("Action Not Allowed.", 401);
        }

        if ($auditRecord->process_status !== ProcessStatuses::RUNNING) {
            throw new UnauthenticatedException("Action Not Allowed.", 422);
        }

        if ($auditRecord->getInstanceUuid() == $this->getInstanceUuid()) { // if same instance, kill right away.

            return $this->killProcess($uuid);
        }

        $values = collect($this->getKillUuids());
        $values->push($uuid);
        $this->storeKillUuids($values->all());

        return $uuid;
    }

    /**
     * Process killer.
     * @param string $uuid
     * @return int
     */
    public function killProcess(string $uuid): string
    {
        $auditRecord = null;
        if (config("remotisan.allow_process_kill", false) === true) {
            $auditRecord = Audit::getByUuid($uuid);
        }

        if (!$auditRecord) {
            throw new RecordNotFoundException("Action Not Allowed.", 404);
        }

        if ($this->getInstanceUuid() !== $auditRecord->getInstanceUuid()) {
            return static::INSTANCE_VIOLATION_MSG;
        }

        if (!$this->processExecutor->isOwnedProcess($auditRecord)) {
            return static::RIGHT_VIOLATION_MSG;
        }

        $dateTime = (string)Carbon::parse();
        $this->processExecutor->appendInputToFile($this->getFilePath($uuid), "\nPROCESS KILLED AT " . $dateTime . "\n");

        if (!$this->processExecutor->killProcess($auditRecord)) {
            return static::KILL_FAILED_MSG;
        }

        $auditRecord->markKilled();
        $values = collect($this->getKillUuids());

        if (false !== ($key = $values->search($uuid, true))) {
            $values->forget($key);
            $this->storeKillUuids($values->all());
        }

        return $uuid;
    }

    /**
     * @param $executionUuid
     *
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function read($executionUuid): array
    {
        $auditRecord = Audit::getByUuid($executionUuid);

        return [
            "content" => explode(PHP_EOL, rtrim(File::get($this->getFilePath($executionUuid)))),
            "isEnded" => ($auditRecord ? $auditRecord->getProcessStatus() : ProcessStatuses::COMPLETED) !== ProcessStatuses::RUNNING
        ];
    }

    /**
     * @param string $executionUuid
     *
     * @return string
     */
    protected function getFilePath(string $executionUuid): string
    {
        $path = config("remotisan.logger.path");
        File::ensureDirectoryExists($path);

        return $path.$executionUuid.'.log';
    }

    /**
     * @param          $role
     * @param callable $callable
     *
     * @return void
     */
    public function authWith($role, callable $callable): void
    {
        static::$authWith[] = ["role" => $role, "callable" => $callable];
    }

    /**
     * @return string
     */
    public function getUserIdentifier(): ?string
    {
        return static::$userIdentifierGetter ? call_user_func_array(static::$userIdentifierGetter, [Request::instance()]) : null;
    }

    /**
     * @param callable $userIdentifierGetter
     * @return void
     */
    public function setUserIdentifierGetter(callable $userIdentifierGetter):void
    {
        static::$userIdentifierGetter = $userIdentifierGetter;
    }

    /**
     * @return string|null
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
     * @return void
     * @throws UnauthenticatedException
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
     * @return bool
     */
    public function isSuperUser(): bool
    {
        $supers = Arr::wrap(config("remotisan.super_users", []));

        return in_array("*", $supers) || in_array($this->getUserIdentifier(), $supers);
    }

    /**
     * Get Killing UUIDs from redis.
     * @return array
     */
    public function getKillUuids(): array
    {
        return Cache::get($this->makeCacheKey()) ?? [];
    }

    /**
     * Store killing UUIDs in redis.
     * @param array $uuids
     * @return void
     */
    public function storeKillUuids(array $uuids): void
    {
        Cache::put($this->makeCacheKey(), $uuids);
    }

    /**
     * Get instance uuid from storage created during app deployment
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getInstanceUuid():string
    {
        if (!$this->instance_uuid) {
            $this->instance_uuid = cache()->driver("file")->rememberForever(static::INSTANCE_UUID_FILE_NAME, fn() => Str::uuid()->toString());
        }

        return $this->instance_uuid;
    }

    /**
     * Compose cache killing key
     * @return string
     */
    public function makeCacheKey(): string
    {
        return implode(":", [config("remotisan.kill_switch_key_prefix"), App::environment(), $this->getInstanceUuid()]);
    }
}
