<?php

declare(strict_types=1);

namespace Fuzzy\Fzpkg\Console\Commands;

use Illuminate\Filesystem\Filesystem;

final class InstallScrapersCommand extends BaseCommand
{
    protected $signature = 'fz:install:scrapers';

    protected $description = 'Install default fz scrapers classes';

    public function handle(): void
    {
        $fileSystem = new Filesystem();

        $this->checkEnvFlag('FZ_SCRAPERS_INSTALLED', 'Fz scrapers already installed');

        $fileSystem->ensureDirectoryExists(app_path('Scrapers/Classes'));
        $fileSystem->ensureDirectoryExists(app_path('Scrapers/output'));

        $fileSystem->copyDirectory(__DIR__.'/../../../data/scrapers', app_path('Scrapers'));

        /* --- */

        $targets = [
            'FZ_SCRAPERS_INSTALLED=' => [
                'from' => 'FZ_SCRAPERS_INSTALLED=.*$',
                'to' => 'FZ_SCRAPERS_INSTALLED=true'
            ],
        ];

        $this->updateEnvFileOrAppend($targets);
    }
}
