<?php

namespace App\Services\AI\Prompt;

class PromptContentParser
{
    public static function parse(string $content, callable $schemaResolver, callable $optionsResolver, string $templateName, array $options): array
    {
        $sections = [];

        if (preg_match('/<system>(.*?)<\/system>/s', $content, $m)) {
            $sections['system'] = trim($m[1]);
        }
        if (preg_match('/<user>(.*?)<\/user>/s', $content, $m)) {
            $sections['user'] = trim($m[1]);
        }
        if (preg_match('/<assistant>(.*?)<\/assistant>/s', $content, $m)) {
            $sections['assistant'] = trim($m[1]);
        }
        if (empty($sections)) {
            $sections['user'] = trim($content);
        }

        $messages = [];
        if (!empty($sections['system'])) {
            $messages[] = ['role' => 'system', 'content' => $sections['system']];
        }
        if (!empty($sections['user'])) {
            $messages[] = ['role' => 'user', 'content' => $sections['user']];
        }
        if (!empty($sections['assistant'])) {
            $messages[] = ['role' => 'assistant', 'content' => $sections['assistant']];
        }

        return [
            'messages' => $messages,
            'template_name' => $templateName,
            'schema' => $schemaResolver($templateName, $options),
            'options' => $optionsResolver($templateName, $options),
        ];
    }
}

