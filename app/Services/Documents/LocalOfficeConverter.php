<?php

namespace App\Services\Documents;

use App\Contracts\DocumentConverter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Smalot\PdfParser\Parser;

class LocalOfficeConverter implements DocumentConverter
{
    public function supportedMimeTypes(): array
    {
        return array_values(ConversionCapabilities::OFFICE_MIME_TYPES);
    }

    public function convert(string $source, string $destination, string $mimeType): void
    {
        $extension = array_search($mimeType, ConversionCapabilities::OFFICE_MIME_TYPES, true);
        if ($extension === false || ! is_file($source)) {
            throw new RuntimeException('Unsupported or missing local conversion input.');
        }
        $binary = config('processing.conversion.local.binary');
        $sandbox = config('processing.conversion.local.sandbox');
        if (! is_executable($binary) || ! is_executable($sandbox)) {
            throw new RuntimeException('Local conversion requires LibreOffice and Bubblewrap.');
        }
        $directory = dirname($destination).'/local-'.Str::uuid();
        File::ensureDirectoryExists($directory.'/profile/user', 0700);
        File::ensureDirectoryExists($directory.'/home', 0700);
        File::ensureDirectoryExists($directory.'/output', 0700);
        try {
            if (! copy($source, $directory.'/source.'.$extension)) {
                throw new RuntimeException('Could not stage local conversion input.');
            }
            File::put($directory.'/profile/user/registrymodifications.xcu', $this->profile());
            $command = [$sandbox, '--unshare-all', '--die-with-parent', '--new-session', '--cap-drop', 'ALL'];
            foreach (['/usr', '/lib', '/lib64', '/bin', '/sbin', '/etc/fonts', '/etc/libreoffice', '/etc/ld.so.cache'] as $path) {
                if (file_exists($path)) {
                    array_push($command, '--ro-bind', $path, $path);
                }
            }
            array_push($command, '--dir', '/proc', '--dev', '/dev', '--tmpfs', '/tmp', '--dir', '/run',
                '--bind', $directory, '/work', '--chdir', '/work', '--clearenv',
                '--setenv', 'HOME', '/work/home', '--setenv', 'TMPDIR', '/tmp', '--setenv', 'LANG', 'C.UTF-8',
                '--setenv', 'PATH', '/usr/bin:/bin', '--setenv', 'SAL_USE_VCLPLUGIN', 'gen',
                '--setenv', 'LD_LIBRARY_PATH', dirname($binary),
                $binary, '-env:UserInstallation=file:///work/profile', '--headless', '--nologo', '--nodefault',
                '--nofirststartwizard', '--norestore', '--convert-to', 'pdf', '--outdir', '/work/output', '/work/source.'.$extension);
            $environment = array_fill_keys(array_keys(getenv()), false);
            $result = Process::path($directory)->env($environment)
                ->timeout(config('processing.conversion.timeout', 120))->run($command);
            if (! $result->successful()) {
                throw new RuntimeException('Isolated LibreOffice conversion failed; verify sandbox support and fonts.');
            }
            $output = $directory.'/output/source.pdf';
            if (! is_file($output) || filesize($output) < 8
                || filesize($output) > config('processing.conversion.max_output_bytes', 104857600)
                || file_get_contents($output, false, null, 0, 5) !== '%PDF-'
                || count((new Parser)->parseFile($output)->getPages()) === 0) {
                throw new RuntimeException('LibreOffice did not produce a nonempty PDF.');
            }
            if (! rename($output, $destination)) {
                throw new RuntimeException('Could not retain local conversion output.');
            }
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function profile(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<oor:items xmlns:oor="http://openoffice.org/2001/registry">'
            .'<item oor:path="/org.openoffice.Office.Common/Security/Scripting">'
            .'<prop oor:name="DisableMacrosExecution" oor:op="fuse"><value>true</value></prop>'
            .'<prop oor:name="MacroSecurityLevel" oor:op="fuse"><value>3</value></prop>'
            .'<prop oor:name="SecureURL" oor:op="fuse"><value/></prop></item>'
            .'<item oor:path="/org.openoffice.Office.Common/Security">'
            .'<prop oor:name="BlockUntrustedRefererLinks" oor:op="fuse"><value>true</value></prop></item>'
            .'</oor:items>';
    }
}
