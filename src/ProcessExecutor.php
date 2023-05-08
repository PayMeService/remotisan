<?php
/**
 * Created by PhpStorm.
 * User: omer
 * Date: 21/11/2022
 * Time: 22:38
 */

namespace PayMe\Remotisan;

use Illuminate\Console\Application;
use Illuminate\Support\ProcessUtils;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ProcessExecutor
{
    protected Process $process;
    /**
     * @param string $uuid
     * @param string $output
     *
     * @return int $PID
     */
    public function execute(string $uuid, string $output): int
    {
        $command = $this->compileShell($output, $uuid);

        $this->process = Process::fromShellCommandline($command, base_path(), null, null, null);
        $this->process->start();
        $pid = $this->process->getPid();
        usleep(4000);
        $this->process->stop();

        return $pid;
    }

    public function compileShell(
        string $output,
        string $uuid
    ): string {
        $output  = ProcessUtils::escapeArgument($output);
        $command = Application::formatCommandString("remotisan:broker {$uuid}") . " > {$output};";

        // As background
        return '(' . $command . ') 2>&1 &';
    }

    /**
     * Splits parameters string into parameters array with escaping and return the array.
     *
     * @param string $params
     * @return array
     */
    public function compileCmdAsEscapedArray(string $params): array
    {
        return $this->compileParamsArray($this->parseParamsString($params, true));
    }

    /**
     * Compile parameters for a command.
     *
     * Copied from \Illuminate\Console\Scheduling\Schedule::compileParameters
     * Thanks to Laravel Team
     *
     * @param   array   $params
     * @return  array
     */
    protected function compileParamsArray(array $params): array
    {
        return collect($params)->map(function ($value, $key) {
            if (is_array($value)) {
                return $this->compileArrayInput($key, $value);
            }

            if (! is_numeric($value) && ! preg_match('/^(-.$|--.*)/i', $value)) {
                $value = static::escapeArgument($value);
            }

            return is_numeric($key) ? $value : "{$key}={$value}";
        })->values()->all();
    }

    /**
     * Is the given string surrounded by the given character?
     *
     * @param  string  $arg
     * @param  string  $char
     * @return bool
     */
    protected static function isSurroundedBy($arg, $char)
    {
        return 2 < strlen($arg) && $char === $arg[0] && $char === $arg[strlen($arg) - 1];
    }

    /**
     * Escapes a string to be used as a shell argument.
     *
     * @param  string  $argument
     * @return string
     */
    public static function escapeArgument($argument)
    {
        // Fix for PHP bug #43784 escapeshellarg removes % from given string
        // Fix for PHP bug #49446 escapeshellarg doesn't work on Windows
        // @see https://bugs.php.net/bug.php?id=43784
        // @see https://bugs.php.net/bug.php?id=49446
        if ('\\' === DIRECTORY_SEPARATOR) {
            if ('' === $argument) {
                return '""';
            }

            $escapedArgument = '';
            $quote = false;

            foreach (preg_split('/(")/', $argument, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE) as $part) {
                if ('"' === $part) {
                    $escapedArgument .= '\\"';
                } elseif (self::isSurroundedBy($part, '%')) {
                    // Avoid environment variable expansion
                    $escapedArgument .= '^%"'.substr($part, 1, -1).'"^%';
                } else {
                    // escape trailing backslash
                    if ('\\' === substr($part, -1)) {
                        $part .= '\\';
                    }
                    $quote = true;
                    $escapedArgument .= $part;
                }
            }

            if ($quote) {
                $escapedArgument = '"'.$escapedArgument.'"';
            }

            return $escapedArgument;
        }

        return str_replace("'", "'\\''", $argument);
    }

    /**
     * Compile array input for a command.
     *
     * Copied from \Illuminate\Console\Scheduling\Schedule::compileArrayInput
     * Thanks to Laravel Team
     *
     * @param   string|int  $key
     * @param   array       $value
     * @return  array
     */
    public function compileArrayInput($key, $value): array
    {
        $value = collect($value)->map(function ($value) {
            return ProcessUtils::escapeArgument($value);
        });

        if (Str::startsWith($key, '--')) {
            $value = $value->map(function ($value) use ($key) {
                return "{$key}={$value}";
            });
        } elseif (Str::startsWith($key, '-')) {
            $value = $value->map(function ($value) use ($key) {
                return "{$key} {$value}";
            });
        }

        return $value->values()->all();
    }

    /**
     * Convert a string of command arguments and options to an array.
     *
     * Copied from \Studio\Totem\Task::compileParameters
     * Thanks to Totem Team (codestudiohq)
     *
     * @param bool $console if true will convert arguments to non associative array
     *
     * @return array
     */
    public function parseParamsString($parameters, $console = false)
    {
        if ($parameters) {
            $regex = '/(?=\S)[^\'"\s]*(?:\'[^\']*\'[^\'"\s]*|"[^"]*"[^\'"\s]*)*/';
            preg_match_all($regex, $parameters, $matches, PREG_SET_ORDER, 0);

            $argument_index = 0;

            $duplicate_parameter_index = function (array $carry, array $param, string $trimmed_param) {
                if (! isset($carry[$param[0]])) {
                    $carry[$param[0]] = $trimmed_param;
                } else {
                    if (! is_array($carry[$param[0]])) {
                        $carry[$param[0]] = [$carry[$param[0]]];
                    }
                    $carry[$param[0]][] = $trimmed_param;
                }

                return $carry;
            };

            return collect($matches)->reduce(function ($carry, $parameter) use ($console, &$argument_index, $duplicate_parameter_index) {
                $param = explode('=', $parameter[0], 2);

                if (count($param) > 1) {
                    $trimmed_param = trim(trim($param[1], '"'), "'");
                    if ($console) {
                        if (Str::startsWith($param[0], ['--', '-'])) {
                            $carry = $duplicate_parameter_index($carry, $param, $trimmed_param);
                        } else {
                            $carry[$argument_index++] = $trimmed_param;
                        }

                        return $carry;
                    }

                    return $duplicate_parameter_index($carry, $param, $trimmed_param);
                }

                Str::startsWith($param[0], ['--', '-']) && ! $console ?
                    $carry[$param[0]] = true :
                    $carry[$argument_index++] = $param[0];

                return $carry;
            }, []);
        }

        return [];
    }
}
