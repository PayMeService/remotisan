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
    /**
     * @param string $command
     * @param string $params
     *
     * @return string
     */
    public function execute(string $command, string $params, string $uuid, string $output): string
    {
        $command = $this->compileShell($output, $params, $command, $uuid);

        $p = Process::fromShellCommandline($command, base_path(), null, null, null);
        $p->start();
        usleep(4000);
        $p->stop();

        return $uuid;
    }

    public function compileShell(
        string $output,
        string $params,
        string $command,
        string $uuid
    ): string {
        $output  = ProcessUtils::escapeArgument($output);

        $params  = $this->escapeParamsString($params);
        $command = Application::formatCommandString("{$command} {$params}") .
                   " > {$output}; echo '{$uuid}' >> {$output}";

        // As background
        return '(' . $command . ') 2>&1 &';
    }

    public function escapeParamsString(string $params): string
    {
        return $this->compileParamsArray(
            $this->parseParamsString($params)
        );
    }

    /**
     * Compile parameters for a command.
     *
     * Copied from \Illuminate\Console\Scheduling\Schedule::compileParameters
     * Thanks to Laravel Team
     *
     * @param  array  $parameters
     * @return string
     */
    protected function compileParamsArray(array $params): string
    {
        return collect($params)->map(function ($value, $key) {
            if (is_array($value)) {
                return $this->compileArrayInput($key, $value);
            }

            if (! is_numeric($value) && ! preg_match('/^(-.$|--.*)/i', $value)) {
                $value = ProcessUtils::escapeArgument($value);
            }

            return is_numeric($key) ? $value : "{$key}={$value}";
        })->implode(' ');
    }

    /**
     * Compile array input for a command.
     *
     * Copied from \Illuminate\Console\Scheduling\Schedule::compileArrayInput
     * Thanks to Laravel Team
     *
     * @param  string|int  $key
     * @param  array  $value
     * @return string
     */
    protected function compileArrayInput($key, $value)
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

        return $value->implode(' ');
    }

    /**
     * Convert a string of command arguments and options to an array.
     *
     *
     * Copied from \Studio\Totem\Task::compileParameters
     * Thanks to Totem Team (codestudiohq)
     *
     * @param bool $console if true will convert arguments to non associative array
     *
     * @return array
     */
    protected function parseParamsString($parameters, $console = false)
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
