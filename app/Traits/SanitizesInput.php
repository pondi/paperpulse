<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait SanitizesInput
{
    /**
     * Sanitize string input by removing potentially dangerous content.
     *
     * @param string|null $input
     * @return string|null
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
     *
     * @param string $filename
     * @return string
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Remove any path components
        $filename = basename($filename);
        
        // Remove any non-alphanumeric characters except dots, hyphens, and underscores
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '', $filename);
        
        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'file_' . time();
        }
        
        // Limit filename length
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $name = substr($name, 0, 255 - strlen($extension) - 1);
            $filename = $name . '.' . $extension;
        }
        
        return $filename;
    }

    /**
     * Sanitize array of input data.
     *
     * @param array $data
     * @param array $fields Fields to sanitize
     * @return array
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
     *
     * @param string|null $email
     * @return string|null
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
     * @param mixed $number
     * @param bool $allowDecimal
     * @return float|int|null
     */
    protected function sanitizeNumber($number, bool $allowDecimal = true)
    {
        if (is_null($number)) {
            return null;
        }
        
        if ($allowDecimal) {
            return filter_var($number, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }
        
        return filter_var($number, FILTER_SANITIZE_NUMBER_INT);
    }
}