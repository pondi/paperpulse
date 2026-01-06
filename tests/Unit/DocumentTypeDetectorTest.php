<?php

namespace Tests\Unit;

use App\Services\AI\DocumentTypeDetector;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentTypeDetectorTest extends TestCase
{
    public function test_detects_invoice_from_filename(): void
    {
        $path = $this->makeTempFile('sample-invoice.pdf', 'PDF content');

        $detector = new DocumentTypeDetector;
        $result = $detector->detect($path);

        $this->assertSame('invoice', $result['type']);
        $this->assertNull($result['subtype']);
        @unlink($path);
    }

    public function test_detects_contract_from_filename(): void
    {
        $path = $this->makeTempFile('contract-agreement.docx', 'DOCX');

        $detector = new DocumentTypeDetector;
        $result = $detector->detect($path);

        $this->assertSame('contract', $result['type']);
        $this->assertNull($result['subtype']);
        @unlink($path);
    }

    public function test_detects_bank_statement_from_filename(): void
    {
        $path = $this->makeTempFile('bank-statement.pdf', 'PDF');

        $detector = new DocumentTypeDetector;
        $result = $detector->detect($path);

        $this->assertSame('bank_statement', $result['type']);
        @unlink($path);
    }

    public function test_detects_text_subtype(): void
    {
        $path = $this->makeTempFile('notes.txt', 'plain text content');

        $detector = new DocumentTypeDetector;
        $result = $detector->detect($path);

        $this->assertSame('document', $result['type']);
        $this->assertSame('text', $result['subtype']);
        @unlink($path);
    }

    public function test_detects_receipt_from_image_extension(): void
    {
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAE/wH+rkzHAAAAAElFTkSuQmCC');
        $path = $this->makeTempFile('photo.png', $pngData);

        $detector = new DocumentTypeDetector;
        $result = $detector->detect($path);

        $this->assertSame('receipt', $result['type']);
        @unlink($path);
    }

    private function makeTempFile(string $name, string $content): string
    {
        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.Str::uuid().'_'.$name;
        file_put_contents($tmp, $content);

        return $tmp;
    }
}
