<?php

namespace PayMe\Remotisan;

use Illuminate\Console\Application;
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

    public function __construct(CommandsRepository $commandsRepo)
    {
        $this->commandsRepo = $commandsRepo;
    }

    public function execute(string $command, array $definition = [])
    {
        if (!$commandData = $this->commandsRepo->find($command)) {
            throw new \RuntimeException("command '{$command}' not allowed");
        }

        $commandData->checkExecute($this->getUserGroup());

        $uuid = Str::uuid()->toString();
        $output = ProcessUtils::escapeArgument($this->getFilePath($uuid));

        $command = $command.' > '.$output.'; echo '.$uuid.' >> '.$output;

        $p = Process::fromShellCommandline('('.Application::formatCommandString($command).') 2>&1 &', base_path(), null, null, null);
        $p->start();
        sleep(1);
        $p->stop();

        return $uuid;
    }

    public function read($executionUuid)
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


    public function authWith($role, callable $callable): void
    {
        static::$authWith[$role] = $callable;
    }

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

    public function checkAuth(): void
    {
        $group = $this->getUserGroup();

        if (!$group) {
            throw new (config('remotisan.authentication_exception_class', UnauthenticatedException::class))();
        }
    }
}
