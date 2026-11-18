<?php

namespace PayMe\Remotisan\Tests\src;

use Illuminate\Support\Facades\Cache;
use PayMe\Remotisan\Interactive\PromptChannel;
use PayMe\Remotisan\Models\Execution;
use PayMe\Remotisan\ProcessStatuses;
use PayMe\Remotisan\Remotisan;

/**
 * `GET /execute/{uuid}/prompt` and `POST /execute/{uuid}/input` - the browser side of live input.
 */
class InteractiveEndpointTest extends TestCase
{
    protected string $uuid = "22222222-2222-2222-2222-222222222222";

    protected string $currentUser = "owner";

    protected array $prompt = [
        "id"        => "abc123",
        "question"  => "Transfer key",
        "secret"    => true,
        "multiline" => false,
        "default"   => null,
        "choices"   => null,
    ];

    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        config()->set("database.connections.testing", [
            "driver"   => "sqlite",
            "database" => ":memory:",
            "prefix"   => "",
        ]);
        config()->set("cache.default", "array");
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Every test gets a fresh :memory: database, so migrating it here is all the isolation needed.
        $this->artisan("migrate")->run();

        $remotisan = app(Remotisan::class);
        $remotisan->authWith("tester", fn() => true);
        $remotisan->setUserIdentifierGetter(fn() => $this->currentUser);
        config()->set("remotisan.super_users", []);
    }

    public function testPromptIsServedForARunningExecution()
    {
        $this->seedExecution();
        app(PromptChannel::class)->publishPrompt($this->uuid, $this->prompt);

        $this->getJson($this->url("prompt"))
            ->assertOk()
            ->assertJsonPath("prompt.id", "abc123")
            ->assertJsonPath("prompt.secret", true)
            ->assertJsonPath("canAnswer", true)
            ->assertJsonPath("isRunning", true);
    }

    public function testNoPromptOnceTheExecutionEnded()
    {
        $this->seedExecution(ProcessStatuses::COMPLETED);
        app(PromptChannel::class)->publishPrompt($this->uuid, $this->prompt);

        $this->getJson($this->url("prompt"))
            ->assertOk()
            ->assertJsonPath("prompt", null)
            ->assertJsonPath("isRunning", false);
    }

    public function testOwnerAnswerIsStoredEncryptedAndNotEchoed()
    {
        $this->seedExecution();
        app(PromptChannel::class)->publishPrompt($this->uuid, $this->prompt);

        $response = $this->postJson($this->url("input"), ["promptId" => "abc123", "answer" => "hunter2"]);

        $response->assertOk()->assertExactJson(["accepted" => true]);
        $this->assertStringNotContainsString("hunter2", $response->getContent());

        $raw = Cache::get("remotisan:input:{$this->uuid}:answer:abc123");
        $this->assertIsString($raw);
        $this->assertStringNotContainsString("hunter2", $raw);

        $channel = app(PromptChannel::class);
        $this->assertSame("hunter2", $channel->pullAnswer($this->uuid, "abc123"));
        $this->assertNull($channel->pullAnswer($this->uuid, "abc123"), "an answer is read once");
    }

    public function testEmptyAnswerIsAcceptedAsTakeTheDefault()
    {
        $this->seedExecution();
        app(PromptChannel::class)->publishPrompt($this->uuid, $this->prompt);

        $this->postJson($this->url("input"), ["promptId" => "abc123", "answer" => ""])->assertOk();

        $this->assertSame("", app(PromptChannel::class)->pullAnswer($this->uuid, "abc123"));
    }

    public function testOnlyTheOwnerOrASuperUserMayAnswer()
    {
        $this->seedExecution();
        app(PromptChannel::class)->publishPrompt($this->uuid, $this->prompt);
        $this->currentUser = "someone-else";

        $this->getJson($this->url("prompt"))->assertJsonPath("canAnswer", false);
        $this->postJson($this->url("input"), ["promptId" => "abc123", "answer" => "x"])->assertStatus(401);

        config()->set("remotisan.super_users", ["someone-else"]);
        $this->postJson($this->url("input"), ["promptId" => "abc123", "answer" => "x"])->assertOk();
    }

    public function testAStaleOrUnknownPromptIsRejected()
    {
        $this->seedExecution();
        app(PromptChannel::class)->publishPrompt($this->uuid, $this->prompt);

        $this->postJson($this->url("input"), ["promptId" => "old-prompt", "answer" => "x"])->assertStatus(409);
        $this->assertNull(app(PromptChannel::class)->pullAnswer($this->uuid, "old-prompt"));
    }

    public function testAnswersAreRefusedForAFinishedOrUnknownExecution()
    {
        $this->seedExecution(ProcessStatuses::COMPLETED);
        app(PromptChannel::class)->publishPrompt($this->uuid, $this->prompt);

        $this->postJson($this->url("input"), ["promptId" => "abc123", "answer" => "x"])->assertStatus(422);
        $this->postJson(config("remotisan.url") . "/execute/no-such-uuid/input", ["promptId" => "abc123", "answer" => "x"])->assertStatus(404);
    }

    public function testMalformedOrOversizedInputIsRejected()
    {
        $this->seedExecution();
        app(PromptChannel::class)->publishPrompt($this->uuid, $this->prompt);
        config()->set("remotisan.interactive.max_answer_length", 5);

        $this->postJson($this->url("input"), ["answer" => "x"])->assertStatus(422);
        $this->postJson($this->url("input"), ["promptId" => "abc123", "answer" => ["x"]])->assertStatus(422);
        $this->postJson($this->url("input"), ["promptId" => "abc123", "answer" => "too long"])->assertStatus(422);
    }

    /**
     * @param   int  $status
     *
     * @return  void
     */
    protected function seedExecution(int $status = ProcessStatuses::RUNNING): void
    {
        Execution::create([
            "job_uuid"        => $this->uuid,
            "server_uuid"     => "server",
            "user_identifier" => "owner",
            "command"         => "app:import",
            "parameters"      => "",
            "executed_at"     => time(),
            "finished_at"     => time(),
            "process_status"  => $status,
        ]);
    }

    /**
     * @param   string  $action
     *
     * @return  string
     */
    protected function url(string $action): string
    {
        return config("remotisan.url") . "/execute/{$this->uuid}/{$action}";
    }
}
