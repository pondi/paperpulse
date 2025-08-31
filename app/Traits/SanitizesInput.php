<?php

namespace App\Traits;

trait SanitizesInput
{
    /**
     * Sanitize string input by removing potentially dangerous content.
     */
    protected function sanitizeString(?string $input): ?string
    {
        if (is_null($input)) {
            return null;
        }

        // Remove any HTML tags
        $input = strip_tags($input);

        // Convert special characters to HTML entities
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        // Remove any null bytes
        $input = str_replace(chr(0), '', $input);

        // Trim whitespace
        $input = trim($input);

        return $input;
    }

    /**
     * Sanitize filename to prevent path traversal attacks.
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Remove any path components
        $filename = basename($filename);

        // Remove any non-alphanumeric characters except dots, hyphens, and underscores
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '', $filename);

        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'file_'.time();
        }

        // Limit filename length
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $name = substr($name, 0, 255 - strlen($extension) - 1);
            $filename = $name.'.'.$extension;
        }

        return $filename;
    }

    /**
     * Sanitize array of input data.
     *
     * @param  array  $fields  Fields to sanitize
     */
    protected function sanitizeData(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = $this->sanitizeString($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Validate and sanitize email address.
     */
    protected function sanitizeEmail(?string $email): ?string
    {
        if (is_null($email)) {
            return null;
        }

        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Sanitize numeric input.
     *
     * @param  mixed  $number
     * @return float|int|null
     */
    protected function sanitizeNumber($number, bool $allowDecimal = true)
    {
        if (is_null($number)) {
            return null;
        }

        if ($allowDecimal) {
            $result = filter_var($number, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

            return $result !== false ? (float) $result : null;
        }

        $result = filter_var($number, FILTER_SANITIZE_NUMBER_INT);

        return $result !== false ? (int) $result : null;
    }
}
