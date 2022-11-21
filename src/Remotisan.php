<?php
namespace PayMe\Remotisan;

use Illuminate\Console\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ProcessUtils;
use Illuminate\Support\Str;
use PayMe\Remotisan\Exceptions\UnauthenticatedException;
use Symfony\Component\Process\Process;

class Remotisan
{

    private CommandsRepository $commandsRepo;
    /** @var callable[] */
    private static array $authWith = [];

    /**
     * @param CommandsRepository $commandsRepo
     */
    public function __construct(CommandsRepository $commandsRepo)
    {
        $this->commandsRepo = $commandsRepo;
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
        $output = ProcessUtils::escapeArgument($this->getFilePath($uuid));

        $command = $command . ' ' . $params . ' > ' . $output . '; echo ' . $uuid . ' >> ' . $output;

        $p = Process::fromShellCommandline('('.Application::formatCommandString($command).') 2>&1 &', base_path(), null, null, null);
        $p->start();
        sleep(1);
        $p->stop();

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
