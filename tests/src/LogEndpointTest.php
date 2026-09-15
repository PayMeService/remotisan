<?php

namespace PayMe\Remotisan\Tests\src;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PayMe\Remotisan\Exceptions\UnauthenticatedException;
use PayMe\Remotisan\FileManager;
use PayMe\Remotisan\LogReader;
use PayMe\Remotisan\Models\Execution;
use PayMe\Remotisan\ProcessStatuses;
use PayMe\Remotisan\Remotisan;

class LogEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected string $uuid = "11111111-1111-1111-1111-111111111111";

    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        config()->set("database.default", "testing");
        config()->set("database.connections.testing", [
            "driver"   => "sqlite",
            "database" => ":memory:",
            "prefix"   => "",
        ]);
        config()->set("remotisan.logger.path", sys_get_temp_dir() . "/remotisan_endpoint_" . getmypid());
    }

    protected function setUp(): void
    {
        parent::setUp();

        app(Remotisan::class)->authWith("tester", fn() => true);
    }

    protected function tearDown(): void
    {
        @unlink(FileManager::getLogFilePath($this->uuid));

        parent::tearDown();
    }

    /**
     * @param   string[]  $lines
     * @param   int       $status
     *
     * @return  void
     */
    protected function seedExecution(array $lines, int $status = ProcessStatuses::COMPLETED): void
    {
        Execution::create([
            "job_uuid"        => $this->uuid,
            "server_uuid"     => "server",
            "user_identifier" => "tester",
            "command"         => "migrate:status",
            "parameters"      => "",
            "executed_at"     => time(),
            "finished_at"     => time(),
            "process_status"  => $status,
        ]);

        file_put_contents(FileManager::getLogFilePath($this->uuid), implode("\n", $lines) . "\n");
    }

    /**
     * @param   array  $query
     *
     * @return  \Illuminate\Testing\TestResponse
     */
    protected function readLog(array $query = [])
    {
        return $this->getJson(config("remotisan.url") . "/execute/{$this->uuid}?" . http_build_query($query));
    }

    public function testDefaultsToTheTailOfTheLog()
    {
        $this->seedExecution(["one", "two", "three"]);

        $response = $this->readLog(["limit" => 2]);

        $response->assertOk()
            ->assertJsonPath("lines.0.text", "two")
            ->assertJsonPath("lines.1.text", "three")
            ->assertJsonPath("atEnd", true)
            ->assertJsonPath("atStart", false)
            ->assertJsonPath("isEnded", true);
    }

    public function testWalksBothDirectionsWithTheCursorsItHandsOut()
    {
        $this->seedExecution(["one", "two", "three", "four"]);

        $tail   = $this->readLog(["limit" => 2])->json();
        $older  = $this->readLog(["direction" => "before", "cursor" => $tail["start"], "limit" => 2])->json();
        $newer  = $this->readLog(["direction" => "after", "cursor" => $older["end"], "limit" => 2])->json();

        $this->assertEquals(["one", "two"], array_column($older["lines"], "text"));
        $this->assertTrue($older["atStart"]);
        $this->assertEquals(["three", "four"], array_column($newer["lines"], "text"));
        $this->assertEquals($tail["lines"], $newer["lines"]);
    }

    public function testZeroLimitAnswersWithCountersOnly()
    {
        $this->seedExecution(["one", "two"], ProcessStatuses::RUNNING);

        $response = $this->readLog(["limit" => 0, "direction" => "after", "cursor" => 0]);

        $response->assertOk()
            ->assertJsonPath("lines", [])
            ->assertJsonPath("isEnded", false)
            ->assertJsonPath("size", filesize(FileManager::getLogFilePath($this->uuid)));
    }

    public function testRejectsAnUnknownDirectionOrAnAbsurdLimit()
    {
        $this->seedExecution(["one"]);

        $this->readLog(["direction" => "sideways"])->assertStatus(422);
        $this->readLog(["limit" => LogReader::MAX_LIMIT + 1])->assertStatus(422);
        $this->readLog(["cursor" => -5])->assertStatus(422);
    }

    public function testDownloadStreamsTheWholeLogFile()
    {
        $lines = array_map(fn($i) => "line {$i}", range(1, 5000));
        $this->seedExecution($lines);

        $response = $this->get(config("remotisan.url") . "/execute/{$this->uuid}/download");

        $response->assertOk()
            ->assertHeader("content-type", "text/plain; charset=UTF-8");
        $this->assertStringContainsString(
            "attachment; filename=migrate-status-{$this->uuid}.log",
            $response->headers->get("content-disposition")
        );
        // The whole file comes out, not the page the viewer would have shown.
        $this->assertEquals(file_get_contents(FileManager::getLogFilePath($this->uuid)), $response->streamedContent());
    }

    public function testDownloadStreamsAFileBiggerThanOneBlockByteForByte()
    {
        // Several read blocks worth, so the streaming loop has to run more than once.
        $this->seedExecution(array_fill(0, 40, str_repeat("x", 4096)));
        $path = FileManager::getLogFilePath($this->uuid);

        $response = $this->get(config("remotisan.url") . "/execute/{$this->uuid}/download");

        $this->assertEquals(file_get_contents($path), $response->streamedContent());
    }

    public function testDownloadingAnUnknownExecutionIsRefused()
    {
        $this->get(config("remotisan.url") . "/execute/does-not-exist/download")->assertStatus(404);
    }

    public function testDownloadingAMissingLogFileIsRefused()
    {
        $this->seedExecution(["one"]);
        $path = FileManager::getLogFilePath($this->uuid);
        unlink($path);

        $response = $this->getJson(config("remotisan.url") . "/execute/{$this->uuid}/download");

        $response->assertStatus(404);
        // Laravel renders an HTTP exception's message into the body even with app.debug off, so
        // the refusal must not carry the server side path of the log file.
        $this->assertStringNotContainsString($path, $response->getContent());
        $this->assertStringNotContainsString(dirname($path), $response->getContent());
    }

    public function testAnUnknownExecutionIsA404RatherThanAnApplicationError()
    {
        // No execution seeded - the endpoint must answer the poll, not blow up the host app's
        // error handler with a RecordNotFoundException.
        $this->readLog()->assertStatus(404);
    }

    public function testUnauthenticatedCallersAreTurnedAway()
    {
        $this->seedExecution(["one"]);
        // The host app renders this exception - the package only refuses to serve the log.
        (new \ReflectionClass(Remotisan::class))->setStaticPropertyValue("authWith", []);
        $this->withoutExceptionHandling();

        $this->expectException(UnauthenticatedException::class);

        $this->readLog();
    }
}
