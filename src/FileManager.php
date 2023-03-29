<?php
namespace PayMe\Remotisan;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PayMe\Remotisan\Models\Execution;

class FileManager
{

    /**
     * Reads the logfile and returns its content + isEnded for front end.
     *
     * @param $executionUuid
     *
     * @return  array
     * @throws  \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public static function read($executionUuid): array
    {
        $executionRecord = Execution::getByJobUuid($executionUuid);

        return [
            "content" => explode(PHP_EOL, rtrim(File::get(static::getLogFilePath($executionUuid)))),
            "isEnded" => ($executionRecord ? $executionRecord->process_status : ProcessStatuses::COMPLETED) !== ProcessStatuses::RUNNING
        ];
    }

    /**
     * Builds path to log file
     *
     * @param   string  $executionUuid
     *
     * @return  string
     */
    public static function getLogFilePath(string $executionUuid): string
    {
        $path = config("remotisan.logger.path");
        File::ensureDirectoryExists($path);

        return $path.$executionUuid.'.log';
    }
}
