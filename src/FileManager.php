<?php
namespace PayMe\Remotisan;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
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
     * Reads one cursor paginated chunk of an execution's log.
     *
     * @param string $executionUuid
     * @param string $direction  after, before or tail - see LogReader.
     * @param ?int   $cursor     Byte offset taken from a previous chunk.
     * @param int    $limit      Max lines to return. 0 returns metadata only.
     *
     * @return  array
     * @throws  \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public static function read(
        $executionUuid,
        string $direction = LogReader::DIRECTION_TAIL,
        ?int $cursor = null,
        int $limit = LogReader::DEFAULT_LIMIT
    ): array {
        $executionRecord = Execution::getByJobUuid($executionUuid);
        if (!$executionRecord) {
            throw new RecordNotFoundException();
        }

        $isEnded = !$executionRecord->isRunning();

        return LogReader::chunk(static::getLogFilePath($executionUuid), $direction, $cursor, $limit, $isEnded)
            + ["isEnded" => $isEnded];
    }

    /**
     * Resolves the log file of an execution, refusing to hand back a path that is not there.
     *
     * @param   string  $executionUuid
     *
     * @return  string
     * @throws  \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public static function requireLogFilePath(string $executionUuid): string
    {
        $path = static::getLogFilePath($executionUuid);

        if (!File::exists($path)) {
            throw new FileNotFoundException("File does not exist at path {$path}.");
        }

        return $path;
    }

    /**
     * Builds a readable file name for a downloaded log, ending with the job uuid so downloads of
     * the same command stay apart.
     *
     * @param   Execution  $execution
     *
     * @return  string
     */
    public static function getDownloadFileName(Execution $execution): string
    {
        // Command names carry colons, which Str::slug drops rather than separates.
        $command = Str::slug(str_replace(":", "-", trim($execution->command . " " . $execution->parameters)));

        return Str::limit($command ?: "remotisan", 60, "") . "-" . $execution->job_uuid . ".log";
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
