<?php

namespace App\Services\AI\FileManager;

/**
 * Manages conversation context for multi-turn Gemini interactions.
 *
 * Maintains conversation history to enable Pass 1 (classification)
 * and Pass 2 (extraction) within the same context.
 */
class GeminiConversation
{
    protected array $history = [];

    /**
     * Add user message to conversation.
     *
     * @param  string  $text  User prompt text
     * @param  string|null  $fileUri  Optional file URI to include
     */
    public function addUserMessage(string $text, ?string $fileUri = null): self
    {
        $parts = [['text' => $text]];

        if ($fileUri) {
            $parts[] = [
                'fileData' => [
                    'fileUri' => $fileUri,
                    'mimeType' => 'application/pdf', // Default, can be made dynamic
                ],
            ];
        }

        $this->history[] = [
            'role' => 'user',
            'parts' => $parts,
        ];

        return $this;
    }

    /**
     * Add model response to conversation.
     *
     * @param  string  $text  Model response text
     */
    public function addModelResponse(string $text): self
    {
        $this->history[] = [
            'role' => 'model',
            'parts' => [['text' => $text]],
        ];

        return $this;
    }

    /**
     * Get conversation history for API request.
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * Get only user messages (for prompts).
     */
    public function getUserMessages(): array
    {
        return array_filter($this->history, fn ($msg) => $msg['role'] === 'user');
    }

    /**
     * Get only model responses.
     */
    public function getModelResponses(): array
    {
        return array_filter($this->history, fn ($msg) => $msg['role'] === 'model');
    }

    /**
     * Clear conversation history.
     */
    public function clear(): self
    {
        $this->history = [];

        return $this;
    }

    /**
     * Check if conversation has any messages.
     */
    public function isEmpty(): bool
    {
        return empty($this->history);
    }

    /**
     * Get number of messages in conversation.
     */
    public function count(): int
    {
        return count($this->history);
    }
}
