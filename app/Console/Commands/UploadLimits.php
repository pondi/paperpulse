<?php

namespace App\Console\Commands;

use App\Services\Files\FileUploadConfigService;
use Illuminate\Console\Command;

class UploadLimits extends Command
{
    protected $signature = 'uploads:limits';

    protected $description = 'Print PHP CLI/FPM and Nginx upload settings for the selected processing capabilities';

    public function handle(FileUploadConfigService $config): int
    {
        $maximum = max($config->getMaxSizeMb('receipt'), $config->getMaxSizeMb('document'));
        $requestMaximum = $maximum * 20 + 1;
        $this->line("upload_max_filesize = {$maximum}M");
        $this->line('max_file_uploads = 20');
        $this->line("post_max_size = {$requestMaximum}M");
        $this->line("client_max_body_size {$requestMaximum}M;");

        return self::SUCCESS;
    }
}
