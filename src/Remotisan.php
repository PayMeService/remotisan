<?php
namespace PayMe\Remotisan;

use Illuminate\Console\Application;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ProcessUtils;
use Illuminate\Support\Str;
use PayMe\Remotisan\Exceptions\UnauthenticatedException;
use PayMe\Remotisan\Models\RemotisanAudit;
use Symfony\Component\Process\Process;

class Remotisan
{

    private CommandsRepository $commandsRepo;
    /** @var callable[] */
    private static array $authWith = [];
    private ProcessExecutor $processExecutor;

    protected static string $userDisplayName = '';

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
        $this->audit($pid, $uuid, time(), $command, $params, static::$userDisplayName);

        return $uuid;
    }

    /**
     * Call audit if callable provided.
     * The package would ONLY send information known to him,
     * the implementer shall retrieve all other data they want to audit, such as: userId or userDisplayName
     * or anything else and log it the way they like.
     *
     * @param int $pid
     * @param string $uuid
     * @param int $timestamp
     * @return void
     */
    public function audit(int $pid, string $uuid, int $timestamp, string $command, string $params, string $userDisplayName): void
    {
        RemotisanAudit::create([
            "pid"           => $pid,
            "uuid"          => $uuid,
            "executed_at"   => $timestamp,
            "command"       => $command,
            "parameters"    => $params,
            "user_name"     => $userDisplayName,
        ]);
    }

    /**
     * Retrieve historical records scoped to user, ordered by executred_at desc
     * and limit to show_history_records_num from artisan config.
     * @return Collection
     */
    public function getHistoryScopedToUser(): Collection
    {
        return RemotisanAudit::query()
            ->where("user_name", static::$userDisplayName)
            ->orderByDesc("executed_at")
            ->limit(config("remotisan.show_history_records_num"))
            ->get();
    }

    /**
     * Process killer passthru to process executor.
     * @param int $pid
     * @return int
     */
    public function killProcess(int $pid): int
    {
        return $this->processExecutor->killProcess($pid);
    }

    /**
     * @param string $userDisplayName
     * @return void
     */
    public static function setUserDisplayName(string $userDisplayName):void
    {
        static::$userDisplayName = $userDisplayName;
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
    public static function authWith($role, callable $callable): void
    {
        static::$authWith[] = ["role" => $role, "callable" => $callable];
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
