<?php
namespace PayMe\Remotisan;

use Illuminate\Console\Application;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ProcessUtils;
use Illuminate\Support\Str;
use PayMe\Remotisan\Exceptions\UnauthenticatedException;
use PayMe\Remotisan\Models\ProcessStatuses;
use PayMe\Remotisan\Models\Audit;
use Symfony\Component\Process\Process;

class Remotisan
{

    private CommandsRepository $commandsRepo;
    /** @var callable[] */
    private static array $authWith = [];
    private ProcessExecutor $processExecutor;

    protected static $userIdentifierGetter;

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
        $this->audit((int)$pid, $uuid, time(), $command, $params, $this->getUserIdentifier(), ProcessStatuses::RUNNING);

        return $uuid;
    }

    /**
     * @param int $pid
     * @param string $uuid
     * @param int $timestamp
     * @param string $command
     * @param string $params
     * @param string $userIdentifier
     * @return void
     */
    public function audit(int $pid, string $uuid, int $timestamp, string $command, string $params, string $userIdentifier, int $status): void
    {
        Audit::create([
            "pid"           => $pid,
            "uuid"          => $uuid,
            "executed_at"   => $timestamp,
            "command"       => $command,
            "parameters"    => $params,
            "user_identifier"=> $userIdentifier,
            "process_status"=> $status,
        ]);
    }

    /**
     * Process killer passthru to process executor.
     * @param string $uuid
     * @return int
     */
    public function killProcess(string $uuid): int
    {
        $auditRecord = Audit::getByUuid($uuid);

        if (!$auditRecord || $auditRecord->user_identifier != $this->getUserIdentifier()
        || $auditRecord->process_status !== ProcessStatuses::RUNNING)
        {
            throw new UnauthenticatedException("Action Not Allowed.", 404);
        }

        $pid = $this->processExecutor->killProcess($auditRecord->pid);
        $auditRecord->updateProcessStatus(ProcessStatuses::KILLED);

        return $pid;
    }

    /**
     * @param $executionUuid
     *
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function read($executionUuid): array
    {
        $content = explode(PHP_EOL, rtrim(File::get($this->getFilePath($executionUuid))));
        $lines = count($content);
        $isEnded = false;
        if ($lines > 1 && $content[$lines-1] == $executionUuid) {
            array_pop($content);
            $isEnded = true;
        }
        return [
            "content" => $content,
            "isEnded" => $isEnded
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
        $callable = static::$userIdentifierGetter;
        if($callable === null) {
            return null;
        }

        $request = Request::instance();
        return call_user_func_array(static::$userIdentifierGetter, [$request]);
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
}
