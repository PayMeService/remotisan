<?php

namespace PayMe\Remotisan;

use Illuminate\Console\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
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
     * @param string $commandToExec
     * @param array  $definition
     *
     * @return string
     */
    public function execute(string $command, string $commandToExec, array $definition = []): string
    {
        if (!$commandData = $this->commandsRepo->find($command)) {
            throw new \RuntimeException("command '{$command}' not allowed");
        }

        $commandData->checkExecute($this->getUserGroup());

        $uuid = Str::uuid()->toString();
        $output = ProcessUtils::escapeArgument($this->getFilePath($uuid));

        $command = $commandToExec.' > '.$output.'; echo '.$uuid.' >> '.$output;

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
        static::$authWith[$role] = $callable;
    }

    /**
     * @return string|null
     */
    public function getUserGroup(): ?string
    {
        $request = \Illuminate\Support\Facades\Request::instance();
        foreach(static::$authWith as $role => $callable) {
            if (call_user_func_array($callable, [$request])) {
                return $role;
            }
        }

        return null;
    }

    /**
     * @return void
     * @throws UnauthenticatedException
     */
    public function checkAuth(): void
    {
        $group = $this->getUserGroup();

        if (!$group) {
            throw new UnauthenticatedException();
        }
    }
}
