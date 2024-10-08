<?php 

declare(strict_types=1);

namespace Fuzzy\Fzpkg\Providers;

use Illuminate\Support\ServiceProvider;
use Fuzzy\Fzpkg\Console\Commands\ExportSweetApiCommand;
use Fuzzy\Fzpkg\Console\Commands\InstallEventsCommand;
use Fuzzy\Fzpkg\Console\Commands\InstallLanguagesCommand;
use Fuzzy\Fzpkg\Console\Commands\InstallLivewireLayoutsCommand;
use Fuzzy\Fzpkg\Console\Commands\InstallMiddlewaresCommand;
use Fuzzy\Fzpkg\Console\Commands\InstallScrapersCommand;
use Fuzzy\Fzpkg\Console\Commands\InstallStubsCommand;
use Fuzzy\Fzpkg\Console\Commands\InstallSweetApiCommand;
use Fuzzy\Fzpkg\Console\Commands\InstallUtilsCommand;
use Fuzzy\Fzpkg\Console\Commands\MakeLivewireFormCommand;
use Fuzzy\Fzpkg\Console\Commands\MakeSweetApiEndpointsCommand;
use Fuzzy\Fzpkg\Console\Commands\MakeVoltComponentCommand;
use Fuzzy\Fzpkg\Console\Commands\ReloadSweetApiCommand;
use Fuzzy\Fzpkg\Console\Commands\RunScrapersCommand;
use Fuzzy\Fzpkg\Console\Commands\RunSweetApiCommand;
use Fuzzy\Fzpkg\Console\Commands\StatusSweetApiCommand; 
use Fuzzy\Fzpkg\Console\Commands\StopSweetApiCommand;

final class PackageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/fz.php' => config_path('fz.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $commands = [
                ExportSweetApiCommand::class,
                InstallEventsCommand::class,
                InstallLanguagesCommand::class,
                InstallLivewireLayoutsCommand::class,
                InstallMiddlewaresCommand::class,
                InstallScrapersCommand::class,
                InstallStubsCommand::class,
                InstallSweetApiCommand::class,
                InstallUtilsCommand::class,
                MakeLivewireFormCommand::class,
                MakeSweetApiEndpointsCommand::class,
                MakeVoltComponentCommand::class,
                RunScrapersCommand::class
            ];
            
            if (\Composer\InstalledVersions::isInstalled('laravel/octane')) {
                $commands = array_merge($commands, [
                    ReloadSweetApiCommand::class,
                    RunSweetApiCommand::class,
                    StatusSweetApiCommand::class,
                    StopSweetApiCommand::class
                ]);
            }

            $this->commands($commands);
        }
    }
}
