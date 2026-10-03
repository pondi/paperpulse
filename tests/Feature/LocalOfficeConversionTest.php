<?php

use App\Services\Documents\ConversionCapabilities;
use App\Services\Documents\LocalOfficeConverter;
use Dompdf\Dompdf;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\Factory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

beforeEach(function (): void {
    config()->set('processing.conversion.local.binary', '/usr/bin/true');
    config()->set('processing.conversion.local.sandbox', '/usr/bin/true');
    Process::preventStrayProcesses();
});

it('isolates profiles, network, inherited secrets and macro settings for each local office job', function (): void {
    $directory = storage_path('app/private/conversion-test-'.Str::uuid());
    File::ensureDirectoryExists($directory, 0700);
    $source = $directory.'/user name;$(command).docx';
    file_put_contents($source, 'input');
    $profiles = [];
    $pdf = new Dompdf;
    $pdf->loadHtml('<p>Safe conversion</p>');
    $pdf->render();
    Process::fake(function ($process) use (&$profiles, $pdf) {
        $profiles[] = $process->path;
        expect($process->command)->toContain('--unshare-all', '--clearenv', '--cap-drop', 'ALL', '--die-with-parent',
            '--proc', '/proc', '--convert-to', 'pdf', '-env:UserInstallation=file:///work/profile', '/work/source.docx');
        expect($process->command)->not->toContain(base_path(), '/');
        expect($process->timeout)->toBe(120);
        expect(File::get($process->path.'/profile/user/registrymodifications.xcu'))
            ->toContain('DisableMacrosExecution', '<value>true</value>', 'MacroSecurityLevel', '<value>3</value>', 'BlockUntrustedRefererLinks');
        expect(array_values(array_unique($process->environment, SORT_REGULAR)))->toBe([false]);
        file_put_contents($process->path.'/output/source.pdf', $pdf->output());

        return Process::result();
    });
    try {
        $converter = app(LocalOfficeConverter::class);
        $converter->convert($source, $directory.'/first.pdf', ConversionCapabilities::OFFICE_MIME_TYPES['docx']);
        $converter->convert($source, $directory.'/second.pdf', ConversionCapabilities::OFFICE_MIME_TYPES['docx']);
        expect($profiles[0])->not->toBe($profiles[1]);
        expect(is_dir($profiles[0]))->toBeFalse()->and(is_dir($profiles[1]))->toBeFalse();
        expect(File::get($directory.'/first.pdf'))->toStartWith('%PDF-');
        expect(File::get($source))->toBe('input');
    } finally {
        File::deleteDirectory($directory);
    }
});

it('fails closed without a sandbox or successful PDF output and removes private temporary files', function (): void {
    $directory = storage_path('app/private/conversion-test-'.Str::uuid());
    File::ensureDirectoryExists($directory, 0700);
    $source = $directory.'/source.docx';
    file_put_contents($source, 'corrupt input');
    try {
        config()->set('processing.conversion.local.sandbox', '/missing/bwrap');
        expect(fn () => app(LocalOfficeConverter::class)->convert($source, $directory.'/out.pdf', ConversionCapabilities::OFFICE_MIME_TYPES['docx']))
            ->toThrow(RuntimeException::class, 'Bubblewrap');
        Process::assertNothingRan();
        config()->set('processing.conversion.local.sandbox', '/usr/bin/true');
        Process::fake(fn () => Process::result(exitCode: 124));
        expect(fn () => app(LocalOfficeConverter::class)->convert($source, $directory.'/out.pdf', ConversionCapabilities::OFFICE_MIME_TYPES['docx']))
            ->toThrow(RuntimeException::class, 'conversion failed');
        expect(glob($directory.'/local-*'))->toBe([]);
        expect(is_file($directory.'/out.pdf'))->toBeFalse();
        Process::fake(fn () => Process::result());
        expect(fn () => app(LocalOfficeConverter::class)->convert($source, $directory.'/out.pdf', ConversionCapabilities::OFFICE_MIME_TYPES['docx']))
            ->toThrow(RuntimeException::class, 'nonempty PDF');
        expect(glob($directory.'/local-*'))->toBe([]);
    } finally {
        File::deleteDirectory($directory);
    }
});

it('converts real DOCX XLSX and PPTX fixtures in an isolated Ubuntu runtime', function (string $extension): void {
    if (! getenv('PAPERPULSE_OFFICE_RUNTIME')) {
        $this->markTestSkipped('Requires Ubuntu with LibreOffice, Bubblewrap and unprivileged namespaces.');
    }
    config()->set('processing.conversion.local.binary', '/usr/bin/libreoffice');
    config()->set('processing.conversion.local.sandbox', '/usr/bin/bwrap');
    Process::swap(new Factory);
    $directory = storage_path('app/private/conversion-runtime-'.Str::uuid());
    File::ensureDirectoryExists($directory, 0700);
    try {
        $source = base_path('tests/fixtures/office/fixture.'.$extension);
        $output = $directory.'/archive.pdf';
        app(LocalOfficeConverter::class)->convert($source, $output, ConversionCapabilities::OFFICE_MIME_TYPES[$extension]);
        expect(count((new Parser)->parseFile($output)->getPages()))->toBeGreaterThan(0);
        expect(glob($directory.'/local-*'))->toBe([]);
    } finally {
        File::deleteDirectory($directory);
    }
})->with(['docx', 'xlsx', 'pptx']);

it('cleans the isolated work directory when the conversion process times out', function (): void {
    $directory = storage_path('app/private/conversion-test-'.Str::uuid());
    File::ensureDirectoryExists($directory, 0700);
    $source = $directory.'/source.docx';
    file_put_contents($source, 'input');
    $process = new Symfony\Component\Process\Process(['sleep', '10']);
    $exception = new ProcessTimedOutException(
        new Symfony\Component\Process\Exception\ProcessTimedOutException($process, 1),
        Process::result(exitCode: 124),
    );
    Process::fake(fn () => throw $exception);
    try {
        expect(fn () => app(LocalOfficeConverter::class)->convert($source, $directory.'/out.pdf', ConversionCapabilities::OFFICE_MIME_TYPES['docx']))
            ->toThrow(ProcessTimedOutException::class);
        expect(glob($directory.'/local-*'))->toBe([]);
        expect(is_file($directory.'/out.pdf'))->toBeFalse();
    } finally {
        File::deleteDirectory($directory);
    }
});

it('converts concurrent jobs and rejects corrupt input in the Ubuntu runtime', function (): void {
    if (! getenv('PAPERPULSE_OFFICE_RUNTIME')) {
        $this->markTestSkipped('Requires the Ubuntu conversion runtime.');
    }
    $directory = storage_path('app/private/conversion-runtime-'.Str::uuid());
    File::ensureDirectoryExists($directory, 0700);
    $processes = [];
    $script = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; '
        .'$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
        .'$app->make(App\\Services\\Documents\\LocalOfficeConverter::class)->convert($argv[1], $argv[2], $argv[3]);';
    try {
        foreach (['docx', 'xlsx', 'pptx'] as $extension) {
            $process = new Symfony\Component\Process\Process([PHP_BINARY, '-r', $script,
                base_path('tests/fixtures/office/fixture.'.$extension), $directory.'/'.$extension.'.pdf',
                ConversionCapabilities::OFFICE_MIME_TYPES[$extension]], base_path());
            $process->setTimeout(125)->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
        }
        expect(glob($directory.'/local-*'))->toBe([]);
        expect(count(glob($directory.'/*.pdf')))->toBe(3);
        config()->set('processing.conversion.local.binary', '/usr/bin/libreoffice');
        config()->set('processing.conversion.local.sandbox', '/usr/bin/bwrap');
        Process::swap(new Factory);
        $source = $directory.'/corrupt.docx';
        file_put_contents($source, "PK\x03\x04corrupt document");
        expect(fn () => app(LocalOfficeConverter::class)->convert($source, $directory.'/corrupt.pdf', ConversionCapabilities::OFFICE_MIME_TYPES['docx']))
            ->toThrow(RuntimeException::class);
        expect(glob($directory.'/local-*'))->toBe([]);
        expect(is_file($directory.'/corrupt.pdf'))->toBeFalse();
    } finally {
        foreach ($processes as $process) {
            $process->stop();
        }
        File::deleteDirectory($directory);
    }
});
