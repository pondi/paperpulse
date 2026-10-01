<?php

use App\Services\Documents\ConversionCapabilities;
use App\Services\Documents\LocalOfficeConverter;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;
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
            '--convert-to', 'pdf', '-env:UserInstallation=file:///work/profile', '/work/source.docx');
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
    $container = getenv('PAPERPULSE_OFFICE_CONTAINER');
    if (! $container) {
        $this->markTestSkipped('Requires the documented disposable Ubuntu conversion runtime.');
    }
    $directory = storage_path('app/private/conversion-runtime-'.Str::uuid());
    File::ensureDirectoryExists($directory, 0700);
    Process::fake(function ($pending) use ($container) {
        $command = array_map(fn ($argument) => str_replace(base_path(), '/workspace', $argument), $pending->command);
        $mountIndex = array_search('--dir', $command, true);
        $command = ['/usr/bin/bwrap', '--unshare-all', '--die-with-parent', '--new-session', '--cap-drop', 'ALL',
            '--ro-bind', '/usr', '/usr', '--ro-bind', '/lib', '/lib', '--ro-bind-try', '/lib64', '/lib64',
            '--ro-bind', '/bin', '/bin', '--ro-bind', '/etc/fonts', '/etc/fonts', '--ro-bind', '/etc/ld.so.cache', '/etc/ld.so.cache',
            ...array_slice($command, $mountIndex)];
        $index = array_search('/usr/bin/true', $command, true);
        $command[$index] = '/usr/lib/libreoffice/program/soffice.bin';
        $libraryIndex = array_search('LD_LIBRARY_PATH', $command, true);
        $command[$libraryIndex + 1] = '/usr/lib/libreoffice/program';
        $process = new Symfony\Component\Process\Process(['docker', 'exec', '--user', posix_getuid().':'.posix_getgid(), $container, ...$command]);
        $process->setTimeout(125)->run();
        Assert::assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        return Process::result(output: $process->getOutput(), errorOutput: $process->getErrorOutput(), exitCode: $process->getExitCode());
    });
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
