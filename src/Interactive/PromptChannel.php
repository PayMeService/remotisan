<?php

namespace PayMe\Remotisan\Interactive;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Hands prompts from the broker to the browser and answers back, through the cache.
 *
 * The broker runs on whichever host received `POST /execute`, while the answer may be posted to
 * any host, so - like kill instructions - this needs a cache shared by all instances (Redis) in a
 * multi-instance deployment.
 *
 * Answers can be secrets. They are therefore:
 * - encrypted with the application key before they touch the cache store,
 * - kept for a short TTL only, and
 * - deleted on first read ({@see pullAnswer()}),
 * and they never reach the database, the log file or the process arguments.
 *
 * @see \PayMe\Remotisan\Console\Commands\ProcessBrokerCommand publishes prompts, pulls answers
 * @see \PayMe\Remotisan\Remotisan::submitInput() stores answers
 */
class PromptChannel
{
    /**
     * Answers not picked up by the broker within this window are dropped.
     */
    private const ANSWER_TTL_SECONDS = 600;

    /**
     * A prompt outlives a slow user, but not an orphaned broker forever.
     */
    private const PROMPT_TTL_SECONDS = 86400;

    /**
     * @param   string                $uuid
     * @param   array<string, mixed>  $prompt  the payload shown to the user - never holds an answer
     *
     * @return  void
     */
    public function publishPrompt(string $uuid, array $prompt): void
    {
        Cache::put($this->promptKey($uuid), $prompt, self::PROMPT_TTL_SECONDS);
    }

    /**
     * @param   string  $uuid
     *
     * @return  array<string, mixed>|null
     */
    public function getPrompt(string $uuid): ?array
    {
        return Cache::get($this->promptKey($uuid));
    }

    /**
     * @param   string  $uuid
     *
     * @return  void
     */
    public function clearPrompt(string $uuid): void
    {
        Cache::forget($this->promptKey($uuid));
    }

    /**
     * @param   string  $uuid
     * @param   string  $promptId
     * @param   string  $answer
     *
     * @return  void
     */
    public function submitAnswer(string $uuid, string $promptId, string $answer): void
    {
        Cache::put($this->answerKey($uuid, $promptId), Crypt::encryptString($answer), self::ANSWER_TTL_SECONDS);
    }

    /**
     * Read and delete the answer to a prompt.
     *
     * @param   string  $uuid
     * @param   string  $promptId
     *
     * @return  string|null  null while the user has not answered yet
     */
    public function pullAnswer(string $uuid, string $promptId): ?string
    {
        $encrypted = Cache::pull($this->answerKey($uuid, $promptId));

        if (!is_string($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            // Written under another APP_KEY (a host with a mismatched key) - unusable, treat as
            // unanswered so the prompt stays open instead of feeding garbage to the command.
            return null;
        }
    }

    /**
     * @param   string  $uuid
     *
     * @return  string
     */
    private function promptKey(string $uuid): string
    {
        return implode(":", [config("remotisan.interactive.key_prefix"), $uuid, "prompt"]);
    }

    /**
     * @param   string  $uuid
     * @param   string  $promptId
     *
     * @return  string
     */
    private function answerKey(string $uuid, string $promptId): string
    {
        return implode(":", [config("remotisan.interactive.key_prefix"), $uuid, "answer", $promptId]);
    }
}
