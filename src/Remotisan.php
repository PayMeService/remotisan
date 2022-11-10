<?php

namespace PayMe\Remotisan;

use Illuminate\Console\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ProcessUtils;
use Illuminate\Support\Str;
use PayMe\Remotisan\Exceptions\UnauthenticatedException;
use Symfony\Component\Process\Process;

class Remotisan
{

    private CommandsRepository $commandsRepo;
    /** @var ?callable */
    private static $authWith = null;

    public function __construct(CommandsRepository $commandsRepo)
    {
        $this->commandsRepo = $commandsRepo;
    }

    public function execute(string $command, array $definition = [])
    {
        if (!$this->commandsRepo->find($command)) {
            throw new \RuntimeException("command '{$command}' not allowed");
        }

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


    public function authWith(callable $callable): void
    {
        static::$authWith = $callable;
    }

    public function checkAuth(Request $request): void
    {
        if (static::$authWith && call_user_func_array(static::$authWith, [$request])) {
            throw new UnauthenticatedException();
        }
    }
}
