<?php

namespace PayMe\Remotisan\Tests\src;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use PayMe\Remotisan\LogReader;

class LogReaderTest extends TestCase
{
    protected string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . "/remotisan_test_" . uniqid() . ".log";
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    /**
     * @param   string[]  $lines
     * @param   bool      $trailingEol
     *
     * @return  void
     */
    protected function writeLog(array $lines, bool $trailingEol = true): void
    {
        file_put_contents($this->path, implode("\n", $lines) . ($trailingEol ? "\n" : ""));
    }

    /**
     * @param   array  $chunk
     *
     * @return  string[]
     */
    protected function texts(array $chunk): array
    {
        return array_column($chunk["lines"], "text");
    }

    public function testTailReturnsTheLastLines()
    {
        $this->writeLog(["one", "two", "three", "four"]);

        $chunk = LogReader::chunk($this->path, LogReader::DIRECTION_TAIL, null, 2, true);

        $this->assertEquals(["three", "four"], $this->texts($chunk));
        $this->assertEquals(filesize($this->path), $chunk["end"]);
        $this->assertTrue($chunk["atEnd"]);
        $this->assertFalse($chunk["atStart"]);
    }

    public function testWalksBackwardsPageByPageUntilTheStartOfTheFile()
    {
        $this->writeLog(["one", "two", "three", "four", "five"]);

        $chunk = LogReader::chunk($this->path, LogReader::DIRECTION_TAIL, null, 2, true);
        $this->assertEquals(["four", "five"], $this->texts($chunk));

        $chunk = LogReader::chunk($this->path, LogReader::DIRECTION_BEFORE, $chunk["start"], 2, true);
        $this->assertEquals(["two", "three"], $this->texts($chunk));
        $this->assertFalse($chunk["atStart"]);

        $chunk = LogReader::chunk($this->path, LogReader::DIRECTION_BEFORE, $chunk["start"], 2, true);
        $this->assertEquals(["one"], $this->texts($chunk));
        $this->assertTrue($chunk["atStart"]);
        $this->assertEquals(0, $chunk["start"]);
    }

    public function testWalksForwardsPageByPageUntilTheEndOfTheFile()
    {
        $this->writeLog(["one", "two", "three"]);

        $chunk = LogReader::chunk($this->path, LogReader::DIRECTION_AFTER, 0, 2, true);
        $this->assertEquals(["one", "two"], $this->texts($chunk));
        $this->assertFalse($chunk["atEnd"]);

        $chunk = LogReader::chunk($this->path, LogReader::DIRECTION_AFTER, $chunk["end"], 2, true);
        $this->assertEquals(["three"], $this->texts($chunk));
        $this->assertTrue($chunk["atEnd"]);
    }

    public function testCursorsAreTheByteOffsetsOfTheirLines()
    {
        $this->writeLog(["one", "two", "three"]);

        $chunk = LogReader::chunk($this->path, LogReader::DIRECTION_AFTER, 0, 10, true);

        $this->assertEquals([0, 4, 8], array_column($chunk["lines"], "offset"));
        // Both directions address the very same line by the very same cursor.
        $back = LogReader::chunk($this->path, LogReader::DIRECTION_BEFORE, 8, 10, true);
        $this->assertEquals([0, 4], array_column($back["lines"], "offset"));
        $this->assertEquals(8, $back["end"]);
    }

    public function testForwardChunksAreContiguousAndNeverOverlap()
    {
        $lines = array_map(fn($i) => "line {$i}", range(1, 50));
        $this->writeLog($lines);

        $collected = [];
        $cursor    = 0;

        do {
            $chunk     = LogReader::chunk($this->path, LogReader::DIRECTION_AFTER, $cursor, 7, true);
            $collected = array_merge($collected, $this->texts($chunk));
            $cursor    = $chunk["end"];
        } while (!$chunk["atEnd"]);

        $this->assertEquals($lines, $collected);
    }

    public function testAnUnterminatedLastLineIsWithheldWhileTheProcessStillWrites()
    {
        $this->writeLog(["done", "half written"], false);

        $running = LogReader::chunk($this->path, LogReader::DIRECTION_AFTER, 0, 10, false);
        $this->assertEquals(["done"], $this->texts($running));
        $this->assertTrue($running["atEnd"], "the withheld half line still counts as being at the end");

        // The rest of that line lands, and only then is it served - once, in full.
        file_put_contents($this->path, " at last\n", FILE_APPEND);
        $next = LogReader::chunk($this->path, LogReader::DIRECTION_AFTER, $running["end"], 10, false);
        $this->assertEquals(["half written at last"], $this->texts($next));
    }

    public function testAnUnterminatedLastLineIsServedOnceTheProcessIsDone()
    {
        $this->writeLog(["done", "no trailing eol"], false);

        $chunk = LogReader::chunk($this->path, LogReader::DIRECTION_AFTER, 0, 10, true);
        $this->assertEquals(["done", "no trailing eol"], $this->texts($chunk));

        $tail = LogReader::chunk($this->path, LogReader::DIRECTION_TAIL, null, 1, true);
        $this->assertEquals(["no trailing eol"], $this->texts($tail));
    }

    public function testTailOfARunningProcessSkipsTheHalfWrittenLine()
    {
        $this->writeLog(["one", "two", "half"], false);

        $chunk = LogReader::chunk($this->path, LogReader::DIRECTION_TAIL, null, 2, false);

        $this->assertEquals(["one", "two"], $this->texts($chunk));
        $this->assertEquals(8, $chunk["end"]);
    }

    public function testACursorPastTheEndFallsBackToTheEdgeOfTheFile()
    {
        $this->writeLog(["fresh"]);

        $after = LogReader::chunk($this->path, LogReader::DIRECTION_AFTER, 5000, 10, true);
        $this->assertEquals([], $this->texts($after));
        $this->assertTrue($after["atEnd"]);

        $before = LogReader::chunk($this->path, LogReader::DIRECTION_BEFORE, 5000, 10, true);
        $this->assertEquals(["fresh"], $this->texts($before));
    }

    public function testALineLongerThanAWholeChunkIsStillServed()
    {
        config()->set("remotisan.logger.max_read_bytes", 1024);
        $long = str_repeat("x", 200000);
        $this->writeLog([$long, "after the monster"]);

        $chunk = LogReader::chunk($this->path, LogReader::DIRECTION_AFTER, 0, 10, true);
        $this->assertCount(1, $chunk["lines"]);
        $this->assertGreaterThan(0, $chunk["end"], "the cursor must advance or the client would loop");

        // Reading on eventually gets past it.
        $cursor = $chunk["end"];
        $seen   = [];
        for ($i = 0; $i < 500 && !$chunk["atEnd"]; $i++) {
            $chunk  = LogReader::chunk($this->path, LogReader::DIRECTION_AFTER, $cursor, 10, true);
            $cursor = $chunk["end"];
            $seen   = array_merge($seen, $this->texts($chunk));
        }
        $this->assertEquals("after the monster", end($seen));
    }

    public function testChunksAreCappedByBytesNotOnlyByLines()
    {
        config()->set("remotisan.logger.max_read_bytes", 65536);
        $this->writeLog(array_fill(0, 2000, str_repeat("y", 500)));

        $chunk = LogReader::chunk($this->path, LogReader::DIRECTION_AFTER, 0, LogReader::MAX_LIMIT, true);

        $this->assertLessThan(2000, count($chunk["lines"]));
        $this->assertLessThanOrEqual(65536 + 501, $chunk["end"] - $chunk["start"]);
    }

    public function testZeroLimitReturnsMetadataOnly()
    {
        $this->writeLog(["one", "two"]);

        $chunk = LogReader::chunk($this->path, LogReader::DIRECTION_TAIL, null, 0, true);

        $this->assertEquals([], $chunk["lines"]);
        $this->assertEquals(filesize($this->path), $chunk["size"]);
        $this->assertTrue($chunk["atEnd"]);
    }

    public function testEmptyLogReadsAsAnEmptyChunk()
    {
        file_put_contents($this->path, "");

        $chunk = LogReader::chunk($this->path, LogReader::DIRECTION_TAIL, null, 10, false);

        $this->assertEquals([], $chunk["lines"]);
        $this->assertTrue($chunk["atStart"]);
        $this->assertTrue($chunk["atEnd"]);
    }

    public function testBlankLinesAreKept()
    {
        $this->writeLog(["one", "", "", "four"]);

        $chunk = LogReader::chunk($this->path, LogReader::DIRECTION_AFTER, 0, 10, true);

        $this->assertEquals(["one", "", "", "four"], $this->texts($chunk));
    }

    public function testCarriageReturnsAreTrimmed()
    {
        file_put_contents($this->path, "windows\r\nline\r\n");

        $chunk = LogReader::chunk($this->path, LogReader::DIRECTION_AFTER, 0, 10, true);

        $this->assertEquals(["windows", "line"], $this->texts($chunk));
    }

    public function testThrowsWhenTheLogIsMissing()
    {
        $this->expectException(FileNotFoundException::class);

        LogReader::chunk($this->path . ".nope", LogReader::DIRECTION_TAIL, null, 10, true);
    }
}
