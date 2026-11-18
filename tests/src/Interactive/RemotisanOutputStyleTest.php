<?php

namespace PayMe\Remotisan\Tests\src\Interactive;

use PayMe\Remotisan\Interactive\PromptProtocol;
use PayMe\Remotisan\Interactive\RemotisanOutputStyle;
use PayMe\Remotisan\Tests\src\TestCase;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Question\Question;

/**
 * The child side of live input: questions go out as marker lines, answers come back on stdin.
 *
 * The pipe is simulated with in-memory streams. Answers are queued up front, which is what the
 * broker does in real life too - the child just blocks on the next line.
 */
class RemotisanOutputStyleTest extends TestCase
{
    /** @var resource */
    private $stdin;

    /** @var resource */
    private $stdout;

    protected function setUp(): void
    {
        parent::setUp();

        putenv(PromptProtocol::ENV_FLAG . "=1");
        $this->stdin  = fopen("php://memory", "w+");
        $this->stdout = fopen("php://memory", "w+");
        RemotisanOutputStyle::useStreams($this->stdin, $this->stdout);
    }

    protected function tearDown(): void
    {
        putenv(PromptProtocol::ENV_FLAG);
        RemotisanOutputStyle::useStreams(null, null);

        parent::tearDown();
    }

    public function testAskSendsAPromptAndReturnsTheAnswer()
    {
        $io = $this->style(["Ada"]);

        $this->assertSame("Ada", $io->ask("Your name"));

        $prompt = $this->sentPrompts()[0];
        $this->assertSame("Your name", $prompt["question"]);
        $this->assertFalse($prompt["secret"]);
    }

    public function testSecretIsFlaggedAndNeverExposesItsDefault()
    {
        $io       = $this->style(["s3cr3t\nwith a second line"]);
        $question = (new Question("Transfer key", "default-secret"))->setHidden(true)->setTrimmable(false);

        $this->assertSame("s3cr3t\nwith a second line", $io->askQuestion($question));

        $prompt = $this->sentPrompts()[0];
        $this->assertTrue($prompt["secret"]);
        $this->assertNull($prompt["default"]);
        $this->assertStringNotContainsString("default-secret", $this->rawStdout());
    }

    public function testEmptyAnswerTakesTheDefault()
    {
        $io = $this->style([""]);

        $this->assertSame("fallback", $io->ask("Value", "fallback"));
        $this->assertSame("fallback", $this->sentPrompts()[0]["default"]);
    }

    public function testConfirmOffersYesNoAndNormalizes()
    {
        $io = $this->style(["y"]);

        $this->assertTrue($io->confirm("Proceed?", false));

        $prompt = $this->sentPrompts()[0];
        $this->assertSame(["yes", "no"], $prompt["choices"]);
        $this->assertSame("no", $prompt["default"]);
    }

    public function testChoiceSendsChoicesAndMapsTheAnswer()
    {
        $io = $this->style(["beta"]);

        $this->assertSame("beta", $io->choice("Pick", ["alpha", "beta"]));
        $this->assertSame(["alpha", "beta"], $this->sentPrompts()[0]["choices"]);
    }

    public function testAValidationErrorIsShownAndTheQuestionAskedAgain()
    {
        $io       = $this->style(["abc", "42"]);
        $question = (new Question("Number"))->setValidator(function ($value) {
            if (!is_numeric($value)) {
                throw new \InvalidArgumentException("Not a number");
            }

            return (int)$value;
        });

        $this->assertSame(42, $io->askQuestion($question));
        $this->assertCount(2, $this->sentPrompts());
        $this->assertStringContainsString("[ERROR] Not a number", $this->rawStdout());
    }

    public function testAClosedPipeFallsBackToTheDefaultOrAborts()
    {
        $io = $this->style([]);

        $this->assertSame("fallback", $io->ask("Value", "fallback"));

        $this->expectException(MissingInputException::class);
        $io->ask("Required value");
    }

    public function testNoInteractionKeepsSymfonyBehaviour()
    {
        $input = new ArrayInput(["--no-interaction" => true], new InputDefinition([new InputOption("no-interaction", "n")]));
        $input->setInteractive(false);
        $io = new RemotisanOutputStyle($input, new BufferedOutput());

        $this->assertSame("fallback", $io->ask("Value", "fallback"));
        $this->assertSame([], $this->sentPrompts());
    }

    public function testWithoutTheBrokerFlagNothingGoesOverThePipe()
    {
        putenv(PromptProtocol::ENV_FLAG);
        $input = new ArrayInput([]);
        $input->setInteractive(false);
        $io = new RemotisanOutputStyle($input, new BufferedOutput());

        $this->assertSame("fallback", $io->ask("Value", "fallback"));
        $this->assertSame([], $this->sentPrompts());
    }

    /**
     * @param   string[]  $answers  queued on the simulated stdin
     *
     * @return  RemotisanOutputStyle
     */
    private function style(array $answers): RemotisanOutputStyle
    {
        foreach ($answers as $answer) {
            fwrite($this->stdin, PromptProtocol::encodeAnswer($answer));
        }
        rewind($this->stdin);

        return new RemotisanOutputStyle(new ArrayInput([]), new BufferedOutput());
    }

    /**
     * @return  string
     */
    private function rawStdout(): string
    {
        rewind($this->stdout);

        return stream_get_contents($this->stdout);
    }

    /**
     * @return  array<int, array<string, mixed>>
     */
    private function sentPrompts(): array
    {
        return PromptProtocol::extractPrompts($this->rawStdout())[1];
    }
}
