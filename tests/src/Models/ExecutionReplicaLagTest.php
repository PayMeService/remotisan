<?php

namespace PayMe\Remotisan\Tests\src\Models;

use Illuminate\Support\Facades\DB;
use PayMe\Remotisan\Models\Execution;
use PayMe\Remotisan\ProcessStatuses;
use PayMe\Remotisan\Tests\src\TestCase;

/**
 * Covers the lookup that the broker process and the log reading endpoint both rely on, against a
 * connection whose read and write hosts are genuinely separate - the shape a replicated MySQL
 * setup has in production.
 *
 * @see Execution::getByJobUuid()
 */
class ExecutionReplicaLagTest extends TestCase
{
    protected string $uuid = "22222222-2222-2222-2222-222222222222";

    protected static string $primaryPath = "";
    protected static string $replicaPath = "";

    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        static::$primaryPath = sys_get_temp_dir() . "/remotisan_primary_" . getmypid() . ".sqlite";
        static::$replicaPath = sys_get_temp_dir() . "/remotisan_replica_" . getmypid() . ".sqlite";

        foreach ([static::$primaryPath, static::$replicaPath] as $path) {
            file_put_contents($path, "");
        }

        config()->set("database.default", "testing");
        config()->set("database.connections.testing", [
            "driver"   => "sqlite",
            // Testbench inspects the top level "database" key; the read/write hosts below override
            // it per PDO, which is what gives the connection a genuinely separate replica.
            "database" => static::$primaryPath,
            "read"   => ["database" => static::$replicaPath],
            "write"  => ["database" => static::$primaryPath],
            "sticky" => false,
            "prefix" => "",
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Migrations run over the write PDO, so only the primary file gets the schema. Copying it
        // to the replica leaves both files with the same, still empty, table - after which every
        // write lands on the primary alone, exactly like a replica that has not caught up yet.
        $this->artisan("migrate")->run();
        DB::purge("testing");
        copy(static::$primaryPath, static::$replicaPath);
        DB::purge("testing");

        Execution::create([
            "job_uuid"        => $this->uuid,
            "server_uuid"     => "server",
            "user_identifier" => "tester",
            "command"         => "migrate:status",
            "parameters"      => "",
            "executed_at"     => time(),
            "finished_at"     => 0,
            "process_status"  => ProcessStatuses::RUNNING,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink(static::$primaryPath);
        @unlink(static::$replicaPath);

        parent::tearDown();
    }

    public function testTheReplicaDoesNotYetHaveTheFreshlyWrittenRecord()
    {
        $this->assertNull(Execution::query()->where("job_uuid", $this->uuid)->first());
    }

    public function testGetByJobUuidFallsBackToTheWriteConnection()
    {
        $executionRecord = Execution::getByJobUuid($this->uuid);

        $this->assertNotNull($executionRecord);
        $this->assertEquals($this->uuid, $executionRecord->job_uuid);
        $this->assertTrue($executionRecord->isRunning());
    }

    public function testGetByJobUuidStillReturnsNullForAnUnknownUuid()
    {
        $this->assertNull(Execution::getByJobUuid("no-such-uuid"));
    }
}
