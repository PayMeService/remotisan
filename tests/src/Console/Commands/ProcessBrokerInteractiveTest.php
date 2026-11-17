<?php

namespace PayMe\Remotisan\Tests\src\Console\Commands;

use PayMe\Remotisan\Console\Commands\ProcessBrokerCommand;
use PayMe\Remotisan\FileManager;
use PayMe\Remotisan\Interactive\PromptChannel;
use PayMe\Remotisan\Models\Execution;
use PayMe\Remotisan\ProcessStatuses;
use PayMe\Remotisan\Tests\src\TestCase;

/**
 * Drives the broker against a real child process that asks questions, end to end through the
 * pipe: env flag, prompt markers on stdout, answers on stdin, and the log transcript.
 *
 * The browser is played by {@see ScriptedPromptChannel}, which answers each prompt as soon as the
 * broker publishes it.
 */
class ProcessBrokerInteractiveTest extends TestCase
{
    protected string $uuid = "33333333-3333-3333-3333-333333333333";

    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        config()->set("database.connections.testing", [
            "driver"   => "sqlite",
            "database" => ":memory:",
            "prefix"   => "",
        ]);
        config()->set("remotisan.logger.path", sys_get_temp_dir() . "/remotisan_broker_" . getmypid());
        config()->set("cache.default", "array");
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Every test gets a fresh :memory: database, so migrating it here is all the isolation needed.
        $this->artisan("migrate")->run();

        @mkdir(config("remotisan.logger.path"), 0777, true);
        file_put_contents(FileManager::getLogFilePath($this->uuid), "");

        Execution::create([
            "job_uuid"        => $this->uuid,
            "server_uuid"     => "server",
            "user_identifier" => "owner",
            "command"         => "app:import",
            "parameters"      => "",
            "executed_at"     => time(),
            "finished_at"     => time(),
            "process_status"  => ProcessStatuses::RUNNING,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink(FileManager::getLogFilePath($this->uuid));

        // The broker's kill check memoizes a server uuid in a static; don't leak it into other tests.
        $serverUuid = new \ReflectionProperty(FileManager::class, "server_uuid");
        $serverUuid->setValue(null, "");

        parent::tearDown();
    }

    public function testAnswersReachTheChildAndSecretsStayOutOfTheLog()
    {
        $channel = new ScriptedPromptChannel([
            "Your name"    => "Ada",
            "Transfer key" => "top-secret-key",
            "Proceed?"     => "yes",
        ]);
        $this->app->instance(PromptChannel::class, $channel);

        $this->runBroker();

        $log = file_get_contents(FileManager::getLogFilePath($this->uuid));

        // The child got every answer
        $this->assertStringContainsString("name=Ada", $log);
        $this->assertStringContainsString("secret-length=14", $log);
        $this->assertStringContainsString("proceed=yes", $log);

        // The transcript shows the questions, echoes plain answers, masks the secret
        $this->assertStringContainsString("? Your name (default: nobody)", $log);
        $this->assertStringContainsString("> Ada", $log);
        $this->assertStringContainsString("? Transfer key", $log);
        $this->assertStringContainsString("> ********", $log);
        $this->assertStringContainsString("? Proceed? [yes/no] (default: no)", $log);
        $this->assertStringNotContainsString("top-secret-key", $log);
        $this->assertStringNotContainsString("@@remotisan:prompt@@", $log);

        // Nothing left for the browser to answer, and the run is a success
        $this->assertNull($channel->getPrompt($this->uuid));
        $this->assertSame(ProcessStatuses::COMPLETED, (int)Execution::getByJobUuid($this->uuid)->process_status);
    }

    /**
     * @return  void
     */
    private function runBroker(): void
    {
        $broker = new class extends ProcessBrokerCommand {
            protected function buildCommandArray(): array
            {
                return [PHP_BINARY, __DIR__ . "/../../../fixtures/interactive_child.php"];
            }
        };
        $broker->setLaravel($this->app);
        $broker->run(
            new \Symfony\Component\Console\Input\ArrayInput(["uuid" => $this->uuid]),
            new \Symfony\Component\Console\Output\NullOutput()
        );
    }
}

/**
 * Plays the browser: answers each published prompt by its question text.
 */
class ScriptedPromptChannel extends PromptChannel
{
    /** @var array<string, string> */
    private array $answers;

    /** @var array<string, array<string, mixed>> */
    private array $prompts = [];

    /**
     * @param   array<string, string>  $answers  question => answer
     */
    public function __construct(array $answers)
    {
        $this->answers = $answers;
    }

    public function publishPrompt(string $uuid, array $prompt): void
    {
        $this->prompts[$uuid] = $prompt;
    }

    public function getPrompt(string $uuid): ?array
    {
        return $this->prompts[$uuid] ?? null;
    }

    public function clearPrompt(string $uuid): void
    {
        unset($this->prompts[$uuid]);
    }

    public function pullAnswer(string $uuid, string $promptId): ?string
    {
        $prompt = $this->prompts[$uuid] ?? null;

        if (!$prompt || $prompt["id"] !== $promptId) {
            return null;
        }

        return $this->answers[$prompt["question"]] ?? null;
    }
}
