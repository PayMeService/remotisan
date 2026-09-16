<?php

namespace PayMe\Remotisan;

use Illuminate\Contracts\Filesystem\FileNotFoundException;

/**
 * Cursor paginated reader over a log file, in both directions.
 *
 * The cursor is the byte offset of a line. It is unique, ordered and stable while the file grows,
 * so it needs no tiebreaker the way a (timestamp, id) cursor over a table would. A chunk is always
 * cut on line boundaries and capped in bytes, so a log of any size is served within memory limits.
 */
class LogReader
{
    const DIRECTION_AFTER  = "after";
    const DIRECTION_BEFORE = "before";
    const DIRECTION_TAIL   = "tail";

    const DIRECTIONS = [self::DIRECTION_AFTER, self::DIRECTION_BEFORE, self::DIRECTION_TAIL];

    /** Bytes read per fread() while scanning for line boundaries. */
    const BLOCK_BYTES = 65536;

    /** Bytes returned per request when none configured. */
    const DEFAULT_MAX_BYTES = 1048576;

    /** Lines returned per request when the caller asks for none. */
    const DEFAULT_LIMIT = 200;

    /** Hard ceiling on the lines a single request may ask for. */
    const MAX_LIMIT = 2000;

    /**
     * Reads one chunk of lines off the log.
     *
     * @param   string  $path
     * @param   string  $direction  after (newer than cursor), before (older than cursor) or tail.
     * @param   ?int    $cursor     Byte offset, as returned by a previous chunk. Null for the edge.
     * @param   int     $limit      Max lines to return. 0 returns metadata only.
     * @param   bool    $complete   Whether the writing process is done - decides if an unterminated
     *                              last line is a line yet, or still being written.
     *
     * @return  array{lines: array<array{offset: int, text: string}>, start: int, end: int, size: int, atStart: bool, atEnd: bool}
     * @throws  \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public static function chunk(
        string $path,
        string $direction = self::DIRECTION_TAIL,
        ?int $cursor = null,
        int $limit = self::DEFAULT_LIMIT,
        bool $complete = false
    ): array {
        if (!is_file($path)) {
            throw new FileNotFoundException("File does not exist at path {$path}.");
        }

        clearstatcache(true, $path);
        $size  = (int)filesize($path);
        $limit = max(0, min($limit, static::MAX_LIMIT));

        if ($limit === 0) {
            $edge = static::clamp($cursor ?? $size, $size);

            return static::result([], $edge, $edge, $size, $edge >= $size);
        }

        $handle = fopen($path, "rb");

        if ($handle === false) {
            $edge = static::clamp($cursor ?? $size, $size);

            return static::result([], $edge, $edge, $size, $edge >= $size);
        }

        try {
            if ($direction === static::DIRECTION_AFTER) {
                return static::forward($handle, static::clamp($cursor ?? 0, $size), $size, $limit, $complete);
            }

            // tail is "before the end of the file".
            $end = $direction === static::DIRECTION_TAIL ? $size : static::clamp($cursor ?? $size, $size);

            return static::backward($handle, $end, $size, $limit, $complete);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Echoes the whole log file in blocks, for a streamed download.
     *
     * Nothing bigger than one block is ever held in memory. The stream stops at the size the file
     * had when it opened, so a log that is still being written ends at a defined point instead of
     * trailing the process forever.
     *
     * @param   string  $path
     *
     * @return  void
     * @throws  \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public static function stream(string $path): void
    {
        if (!is_file($path)) {
            throw new FileNotFoundException("File does not exist at path {$path}.");
        }

        clearstatcache(true, $path);
        $remaining = (int)filesize($path);
        $handle    = fopen($path, "rb");

        if ($handle === false) {
            return;
        }

        try {
            while ($remaining > 0) {
                $block = fread($handle, (int)min(static::BLOCK_BYTES, $remaining));

                if ($block === false || $block === "") {
                    break;
                }

                $remaining -= strlen($block);
                echo $block;
                flush();
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Collects up to $limit lines starting at $start, going forward.
     *
     * @param   resource  $handle
     * @param   int       $start
     * @param   int       $size
     * @param   int       $limit
     * @param   bool      $complete
     *
     * @return  array
     */
    protected static function forward($handle, int $start, int $size, int $limit, bool $complete): array
    {
        $maxBytes  = static::maxBytes();
        $lines     = [];
        $buffer    = "";
        $pos       = $start;
        $lineStart = $start;

        fseek($handle, $start);

        while (count($lines) < $limit && $pos < $size && ($pos - $start) < $maxBytes) {
            $block = fread($handle, (int)min(static::BLOCK_BYTES, $maxBytes - ($pos - $start), $size - $pos));

            if ($block === false || $block === "") {
                break;
            }

            $pos    += strlen($block);
            $buffer .= $block;

            while (count($lines) < $limit && ($eol = strpos($buffer, "\n")) !== false) {
                $lines[]   = static::line($lineStart, substr($buffer, 0, $eol));
                $lineStart += $eol + 1;
                $buffer    = substr($buffer, $eol + 1);
            }
        }

        // What is left has no newline behind it. It is a line once the writer is done, or once it
        // alone fills a whole chunk - otherwise it is half a line still being written, and the next
        // request picks it up complete.
        if ($buffer !== "" && count($lines) < $limit
            && (($complete && $lineStart + strlen($buffer) >= $size) || (!$lines && strlen($buffer) >= $maxBytes))) {
            $lines[]   = static::line($lineStart, $buffer);
            $lineStart += strlen($buffer);
            $buffer    = "";
        }

        // Whatever is left unread, or left in the buffer as a whole line we had no room for, means
        // there is more to come. A half written line does not - it is not a line yet.
        $pending = strpos($buffer, "\n") !== false || ($complete && $buffer !== "");

        return static::result($lines, $start, $lineStart, $size, $pos >= $size && !$pending);
    }

    /**
     * Collects up to $limit lines ending at $end, going backwards.
     *
     * @param   resource  $handle
     * @param   int       $end
     * @param   int       $size
     * @param   int       $limit
     * @param   bool      $complete
     *
     * @return  array
     */
    protected static function backward($handle, int $end, int $size, int $limit, bool $complete): array
    {
        $maxBytes = static::maxBytes();
        $region   = "";
        $start    = $end;
        // Withholding a half written last line below still leaves us at the end of the log.
        $atEnd    = $end >= $size;
        // One newline per line, plus the one that proves the first line is not cut in half.
        $needed   = $limit + 1;

        while ($start > 0 && ($end - $start) < $maxBytes && substr_count($region, "\n") < $needed) {
            $blockSize = (int)min(static::BLOCK_BYTES, $start, $maxBytes - ($end - $start));

            if ($blockSize <= 0) {
                break;
            }

            $start -= $blockSize;
            fseek($handle, $start);
            $block = fread($handle, $blockSize);

            if ($block === false || $block === "") {
                $start += $blockSize;
                break;
            }

            $region = $block . $region;
        }

        // An unterminated tail is not a line yet while the process still writes it.
        if ($end >= $size && !$complete && $region !== "" && !str_ends_with($region, "\n")) {
            $lastEol = strrpos($region, "\n");
            $keep    = $lastEol === false ? 0 : $lastEol + 1;
            $end     = $start + $keep;
            $region  = substr($region, 0, $keep);
        }

        // Unless we reached the beginning of the file, the region opens mid line - drop that half.
        if ($start > 0) {
            $firstEol = strpos($region, "\n");

            if ($firstEol === false) {
                $start  = $end;
                $region = "";
            } else {
                $start  += $firstEol + 1;
                $region = substr($region, $firstEol + 1);
            }
        }

        $lines = static::splitLines($region, $start);

        if (count($lines) > $limit) {
            $lines = array_slice($lines, -$limit);
            $start = $lines[0]["offset"];
        }

        return static::result($lines, $start, $end, $size, $atEnd);
    }

    /**
     * Splits a region made of whole lines into cursor carrying lines.
     *
     * @param   string  $region
     * @param   int     $regionStart
     *
     * @return  array<array{offset: int, text: string}>
     */
    protected static function splitLines(string $region, int $regionStart): array
    {
        if ($region === "") {
            return [];
        }

        if (str_ends_with($region, "\n")) {
            $region = substr($region, 0, -1);
        }

        $lines  = [];
        $offset = $regionStart;

        foreach (explode("\n", $region) as $text) {
            $lines[] = static::line($offset, $text);
            $offset  += strlen($text) + 1;
        }

        return $lines;
    }

    /**
     * @param   int     $offset
     * @param   string  $text
     *
     * @return  array{offset: int, text: string}
     */
    protected static function line(int $offset, string $text): array
    {
        return ["offset" => $offset, "text" => rtrim($text, "\r")];
    }

    /**
     * @param   array  $lines
     * @param   int    $start
     * @param   int    $end
     * @param   int    $size
     * @param   bool   $atEnd  Whether any further line is readable past $end right now.
     *
     * @return  array
     */
    protected static function result(array $lines, int $start, int $end, int $size, bool $atEnd): array
    {
        return [
            "lines"   => $lines,
            "start"   => $start,
            "end"     => $end,
            "size"    => $size,
            "atStart" => $start <= 0,
            "atEnd"   => $atEnd,
        ];
    }

    /**
     * Keeps a client provided cursor inside the file. Out of range means the log was rotated or
     * truncated under the reader, so it falls back to that edge of the file.
     *
     * @param   int  $cursor
     * @param   int  $size
     *
     * @return  int
     */
    protected static function clamp(int $cursor, int $size): int
    {
        return max(0, min($cursor, $size));
    }

    /**
     * @return int
     */
    protected static function maxBytes(): int
    {
        return max(static::BLOCK_BYTES, (int)config("remotisan.logger.max_read_bytes", static::DEFAULT_MAX_BYTES));
    }
}
