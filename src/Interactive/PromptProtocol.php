<?php

namespace PayMe\Remotisan\Interactive;

/**
 * Wire format between a command run by Remotisan and its broker.
 *
 * The command never talks to the browser. When it asks a question it writes ONE marker line to its
 * stdout, and then blocks reading ONE answer line from its stdin:
 *
 *   child stdout  ->  "@@remotisan:prompt@@{"id":"...","question":"...","secret":true,...}\n"
 *   child stdin   <-  "<base64 of the answer>\n"
 *
 * The broker strips marker lines out of the output before it reaches the log file, publishes the
 * prompt through {@see PromptChannel}, and writes the user's answer to the child's stdin. Answers
 * are base64 encoded so any value - including a multi-line paste - stays exactly one line on the
 * pipe.
 *
 * @see RemotisanOutputStyle the child side
 * @see \PayMe\Remotisan\Console\Commands\ProcessBrokerCommand the broker side
 */
final class PromptProtocol
{
    /**
     * Set by the broker on the child process. Only a process carrying it routes questions over
     * the pipe; everything else (a terminal, a cron run) keeps the normal Symfony behaviour.
     */
    public const ENV_FLAG = "REMOTISAN_INTERACTIVE";

    public const MARKER = "@@remotisan:prompt@@";

    /**
     * @param   array{id: string, question: string, secret: bool, multiline: bool, default: ?string, choices: ?array<int, string>}  $prompt
     *
     * @return  string  the marker line, newline terminated
     */
    public static function encodePrompt(array $prompt): string
    {
        return self::MARKER . json_encode($prompt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * Split a chunk of child stdout into regular output and the prompts it carried.
     *
     * Marker lines are written with a single fwrite well below PIPE_BUF, so a marker always
     * arrives whole inside one chunk.
     *
     * @param   string  $chunk
     *
     * @return  array{0: string, 1: array<int, array<string, mixed>>}  [output without markers, prompts]
     */
    public static function extractPrompts(string $chunk): array
    {
        $prompts = [];

        $output = preg_replace_callback(
            "/^" . preg_quote(self::MARKER, "/") . "(.*)(?:\\n|$)/m",
            function (array $match) use (&$prompts): string {
                $prompt = json_decode($match[1], true);

                if (is_array($prompt) && isset($prompt["id"], $prompt["question"])) {
                    $prompts[] = $prompt;
                }

                return "";
            },
            $chunk
        );

        return [$output, $prompts];
    }

    /**
     * @param   string  $answer
     *
     * @return  string  the answer line, newline terminated
     */
    public static function encodeAnswer(string $answer): string
    {
        return base64_encode($answer) . "\n";
    }

    /**
     * @param   string  $line
     *
     * @return  string
     */
    public static function decodeAnswer(string $line): string
    {
        return (string)base64_decode(trim($line), true);
    }
}
