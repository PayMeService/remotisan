<?php
namespace PayMe\Remotisan;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PayMe\Remotisan\Exceptions\RecordNotFoundException;
use PayMe\Remotisan\Models\Execution;

class FileManager
{
    const SERVER_UUID_FILE_NAME = "remotisan_server_guid";

    protected static string $server_uuid = "";

    /**
     * Get instance uuid from storage created during app deployment
     *
     * @return  string
     * @throws  \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public static function getServerUuid():string
    {
        if (!static::$server_uuid) {
            static::$server_uuid = cache()->driver("file")->rememberForever(static::SERVER_UUID_FILE_NAME, fn() => Str::uuid()->toString());
        }

        return static::$server_uuid;
    }

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
        if (!$executionRecord) {
            throw new RecordNotFoundException();
        }

        $content = File::exists(static::getLogFilePath($executionUuid)) ? rtrim(File::get(static::getLogFilePath($executionUuid))) : '';

        return [
            "content" => explode(PHP_EOL, $content),
            "isEnded" => !$executionRecord->isRunning()
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

        if (!Str::endsWith("/", $path)) {
            $path .= "/";
        }

        return $path.$executionUuid.'.log';
    }
}
