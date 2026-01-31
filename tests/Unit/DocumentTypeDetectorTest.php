<?php

namespace Tests\Unit;

use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests for DocumentTypeDetector class.
 *
 * NOTE: The DocumentTypeDetector class has not been implemented yet.
 * These tests are skipped until the class is created.
 */
class DocumentTypeDetectorTest extends TestCase
{
    public function test_detects_invoice_from_filename(): void
    {
        $this->markTestSkipped('DocumentTypeDetector class not yet implemented');
    }

    public function test_detects_contract_from_filename(): void
    {
        $this->markTestSkipped('DocumentTypeDetector class not yet implemented');
    }

    public function test_detects_bank_statement_from_filename(): void
    {
        $this->markTestSkipped('DocumentTypeDetector class not yet implemented');
    }

    public function test_detects_text_subtype(): void
    {
        $this->markTestSkipped('DocumentTypeDetector class not yet implemented');
    }

    public function test_detects_receipt_from_image_extension(): void
    {
        $this->markTestSkipped('DocumentTypeDetector class not yet implemented');
    }

    private function makeTempFile(string $name, string $content): string
    {
        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.Str::uuid().'_'.$name;
        file_put_contents($tmp, $content);

        return $tmp;
    }
}
