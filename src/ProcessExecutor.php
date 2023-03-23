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
use PayMe\Remotisan\Exceptions\ProcessFailedException;
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

    /**
     * Simple command executor. Handles the execution and return the process for future work with it.
     * Be it checks, or getOutput() or any other further manipulation on process object.
     *
     * @param string $cmd
     * @return Process
     */
    public function executeCommand(string $cmd): Process
    {
        $process = Process::fromShellCommandline($cmd, base_path());
        $process->enableOutput();
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process->getErrorOutput());
        }

        return $process;
    }

    /**
     * Append input to file.
     * @param $filePath
     * @param $input
     * @return int
     */
    public function appendInputToFile($filePath, $input): int
    {
        $process = $this->executeCommand("echo \"{$input}\" >> {$filePath}");

        return (bool)$process->getPid();
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
     * @deprecated shall be deprecated soon.
     * @param string $params
     * @return string
     */
    public function escapeParamsString(string $params): string
    {
        return implode(' ', $this->compileParamsArray(
            $this->parseParamsString($params)
        ));
    }

    /**
     * Splits parameters string into parameters array with escaping and return the array.
     *
     * @param string $params
     * @return array
     */
    public function compileCmdAsEscapedArray(string $params): array
    {
        return $this->compileParamsArray($this->parseParamsString($params));
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
                $value = ProcessUtils::escapeArgument($value);
            }

            return is_numeric($key) ? $value : "{$key}={$value}";
        })->values()->all();
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
