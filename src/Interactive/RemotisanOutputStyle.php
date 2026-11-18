<?php

namespace PayMe\Remotisan\Interactive;

use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * The output style of a command that Remotisan runs. It turns every question into a prompt the
 * user answers live in the browser.
 *
 * All of Laravel's question APIs end up in {@see askQuestion()}: `$this->ask()`, `secret()`,
 * `confirm()`, `choice()`, `anticipate()`, the `$this->components->*` prompts, and the
 * Laravel Prompts functions (`text()`, `password()`, ...). The provider forces the Prompts
 * fallback so they don't return their default for lack of a TTY.
 *
 * Rather than reading a terminal, the question goes to the broker as a marker line on stdout, and
 * the answer comes back as one line on stdin ({@see PromptProtocol}). The Question's own
 * default, normalizer and validator are applied exactly like Symfony's QuestionHelper does, so
 * commands behave the same as in a terminal. `--no-interaction` is still honoured.
 *
 * Bound in place of {@see OutputStyle} only inside processes spawned by the broker.
 *
 * @see \PayMe\Remotisan\RemotisanServiceProvider::registerInteractiveOutputStyle()
 */
class RemotisanOutputStyle extends OutputStyle
{
    /**
     * @var resource|null  test override for the child's STDIN
     */
    protected static $inputStream = null;

    /**
     * @var resource|null  test override for the child's STDOUT
     */
    protected static $outputStream = null;

    protected InputInterface $promptInput;

    /**
     * @param   InputInterface   $input
     * @param   OutputInterface  $output
     */
    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->promptInput = $input;

        parent::__construct($input, $output);
    }

    /**
     * Point the pipe at other streams. For tests only.
     *
     * @param   resource|null  $input
     * @param   resource|null  $output
     *
     * @return  void
     */
    public static function useStreams($input, $output): void
    {
        static::$inputStream  = $input;
        static::$outputStream = $output;
    }

    /**
     * @param   Question  $question
     *
     * @return  mixed
     */
    public function askQuestion(Question $question): mixed
    {
        if (!$this->routesOverPipe()) {
            return parent::askQuestion($question);
        }

        $validator = $question->getValidator();
        $attempts  = $question->getMaxAttempts();
        $error     = null;

        // Mirrors QuestionHelper::validateAttempts(): retry on a validation error, show it first.
        while ($attempts === null || $attempts-- > 0) {
            if ($error !== null) {
                $this->writeToPipe("[ERROR] " . $error->getMessage() . "\n");
            }

            $answer = $this->askOverPipe($question);

            if (!$validator) {
                return $answer;
            }

            try {
                return $validator($answer);
            } catch (RuntimeException $e) {
                throw $e;
            } catch (\Exception $e) {
                $error = $e;
            }
        }

        throw $error;
    }

    /**
     * @return  bool
     */
    protected function routesOverPipe(): bool
    {
        return getenv(PromptProtocol::ENV_FLAG) === "1"
            && !$this->promptInput->hasParameterOption(["--no-interaction", "-n"], true);
    }

    /**
     * Publish one prompt and block until its answer arrives.
     *
     * @param   Question  $question
     *
     * @return  mixed  the raw answer, defaulted and normalized - not validated yet
     * @throws  MissingInputException  when the broker closed the pipe and there is no default
     */
    protected function askOverPipe(Question $question): mixed
    {
        $this->writeToPipe(PromptProtocol::encodePrompt($this->describe($question)));

        $line = fgets(static::$inputStream ?? STDIN);

        if ($line === false) {
            // The broker is gone (killed or finished). Same contract as Symfony on EOF.
            if ($question->getDefault() === null) {
                throw new MissingInputException("Aborted.");
            }

            $line = PromptProtocol::encodeAnswer("");
        }

        $answer = PromptProtocol::decodeAnswer($line);
        $answer = $question->isTrimmable() ? trim($answer) : $answer;
        $answer = strlen($answer) > 0 ? $answer : $question->getDefault();

        if ($normalizer = $question->getNormalizer()) {
            return $normalizer($answer);
        }

        return $answer;
    }

    /**
     * The prompt as the user sees it. A hidden question never exposes its default.
     *
     * @param   Question  $question
     *
     * @return  array{id: string, question: string, secret: bool, multiline: bool, default: ?string, choices: ?array<int, string>}
     */
    protected function describe(Question $question): array
    {
        $choices = null;
        $default = $question->getDefault();

        if ($question instanceof ConfirmationQuestion) {
            $choices = ["yes", "no"];
            $default = $default === null ? null : ($default ? "yes" : "no");
        } elseif ($question instanceof ChoiceQuestion) {
            $choices = array_values(array_map("strval", $question->getChoices()));
        }

        return [
            "id"        => bin2hex(random_bytes(8)),
            "question"  => trim(Helper::removeDecoration($this->getFormatter(), (string)$question->getQuestion())),
            "secret"    => $question->isHidden(),
            "multiline" => $question->isMultiline(),
            "default"   => $question->isHidden() || $default === null || is_array($default) ? null : (string)$default,
            "choices"   => $choices,
        ];
    }

    /**
     * Raw write to the broker, bypassing formatting and verbosity (a -q run still prompts).
     *
     * @param   string  $data
     *
     * @return  void
     */
    protected function writeToPipe(string $data): void
    {
        $stream = static::$outputStream ?? STDOUT;

        fwrite($stream, $data);
        fflush($stream);
    }
}
