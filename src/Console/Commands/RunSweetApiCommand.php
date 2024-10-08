<?php

namespace Fuzzy\Fzpkg\Console\Commands;

use Fuzzy\Fzpkg\Classes\Utils\Utils;
use Fuzzy\Fzpkg\Classes\SweetApi\Attributes\Router\{RoutePrefix, Route, BaseMiddleware, Trashed};
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Env;
use Dotenv\Dotenv;
use ReflectionClass;
use Laravel\Octane\Commands\StartCommand;

//### FIXME: Aggiungere altri attributi per le rotte... (where etc...) https://laravel.com/docs/11.x/routing

final class RunSweetApiCommand extends StartCommand
{
    public $description = 'Start the Octane/SweetAPI server';

    public function __construct()
    {
        if (!defined('RUN_ARTISAN_FROM_SWEET_API_DIR')) {
            $signature = str_replace('octane:start', 'fz:sweetapi:run { apiName : SweetApi Folder (case sensitive) } { --disable-swagger : Disable swagger documentation }', $this->signature);
        }
        else {
            $signature = str_replace('octane:start', 'fz:sweetapi:run { --disable-swagger : Disable swagger documentation }', $this->signature);
        }

        $signature = preg_replace('@{--host=[^}]+}@m', '', $signature);
        $signature = preg_replace('@{--port=[^}]+}@m', '', $signature);

        $this->signature = $signature;

        parent::__construct();
    }

    public function handle(): void
    {
        if (!defined('RUN_ARTISAN_FROM_SWEET_API_DIR')) {
            $apiName = $this->argument('apiName');
            $apiDirectoryPath = base_path('sweets/' . $apiName);

            if (!file_exists($apiDirectoryPath)) {
                $this->fail('SweetAPI "' . $this->argument('apiName') . '" not exists (directory "' . $apiDirectoryPath . '" not found)');
            }
            else {
                $this->createRoutesFile($apiDirectoryPath, Utils::makeFilePath($apiDirectoryPath, 'routes', 'api.php'));
            }

            app()->setBasePath($apiDirectoryPath);
            app()->useEnvironmentPath($apiDirectoryPath);

            Dotenv::create(Env::getRepository(), app()->environmentPath(), app()->environmentFile())->load();

            (new LoadConfiguration())->bootstrap(app());
        }
        else {
            $apiName = RUN_ARTISAN_FROM_SWEET_API_DIR;
            $apiDirectoryPath = base_path();

            $this->createRoutesFile($apiDirectoryPath, Utils::makeFilePath($apiDirectoryPath, 'routes', 'api.php'));
        }

        $this->getDefinition()->addOption(new InputOption('--host', null, InputOption::VALUE_REQUIRED, ''));
        $this->getDefinition()->addOption(new InputOption('--port', null, InputOption::VALUE_REQUIRED, ''));

        $urlParts = parse_url(config('app.url'));

        if (!isset($urlParts['host'])) {
            $this->fail('SweetAPI "' . $this->argument('apiName') . '" invalid APP_URL value in .env');
        }
            
        $host = $urlParts['host'];
        $port = isset($urlParts['port']) ? $urlParts['port'] : 80;

        $this->getDefinition()->getOption('host')->setDefault($host);
        $this->getDefinition()->getOption('port')->setDefault($port);

        parent::handle();
    }

    protected function createRoutesFile(string $apiDirectoryPath, string $apiRoutesFilename) 
    {
        $classes = glob(Utils::makeFilePath($apiDirectoryPath, 'app', 'Http', 'Endpoints', '?*Endpoints.php'));

        file_put_contents($apiRoutesFilename, '');

        if (count($classes) > 0) {
            $endpointsStruct = [];
            $uses = [];

            if (!defined('RUN_ARTISAN_FROM_SWEET_API_DIR')) {
                include Utils::makeFilePath($apiDirectoryPath, 'vendor', 'autoload.php');
            }

            foreach ($classes as $idx => $class) {
                $className = basename($class, '.php');

                if ($className === 'SwaggerEndpoints' && $this->option('disable-swagger')) {
                    continue;
                }

                $endpointsStruct[$idx] = [];
                $endpointsStruct[$idx]['use'] = Utils::makeNamespacePath('App', 'Http', 'Endpoints', $className);
                $endpointsStruct[$idx]['prefix'] = '';
                $endpointsStruct[$idx]['middleware'] = ['add' => [], 'exclude' => []];
                $endpointsStruct[$idx]['controller'] = $className;
                $endpointsStruct[$idx]['methods'] = [];

                $reflectionClass = new ReflectionClass('\\' . $endpointsStruct[$idx]['use']);

                foreach ($reflectionClass->getAttributes() as $attribute) {
                    $attributeInstance = $attribute->newInstance();

                    if ($attributeInstance instanceof RoutePrefix) {
                        $endpointsStruct[$idx]['prefix'] = $attributeInstance->path;
                    }
                    else if ($attributeInstance instanceof BaseMiddleware) {
                        $name = $attributeInstance->getName();

                        if (!empty($name)) {
                            $endpointsStruct[$idx]['middleware'][($attributeInstance->exclude ? 'exclude' : 'add')][] = $name;
                        }
                    }
                }

                foreach ($reflectionClass->getMethods() as $method) {
                    $methodName = $method->getName();

                    $routeVerbs = null;
                    $routePath = null;
                    $routeName = null;
                    $routeMiddleware = ['add' => [], 'exclude' => []];
                    $withTrashed = false;

                    foreach ($method->getAttributes() as $attribute) {
                        $attributeInstance = $attribute->newInstance();

                        if ($attributeInstance instanceof Route) {
                            $routeVerbs = strtolower($attributeInstance->verbs);
                            $routePath = $attributeInstance->path;
                            $routeName = $attributeInstance->name;
                            
                            if ($routeVerbs === '*') {
                                $routeVerbs = 'any';
                            }
                            else {
                                $parts = explode('|', $routeVerbs);

                                if (count($parts) > 1) {
                                    $routeVerbs = array_map(function($item) {return "'$item'"; }, $parts);
                                }
                                else {
                                    $routeVerbs = $parts;
                                }
                            }
                        }
                        else if ($attributeInstance instanceof BaseMiddleware) {
                            $name = $attributeInstance->getName();

                            if (!empty($name)) {
                                $routeMiddleware[($attributeInstance->exclude ? 'exclude' : 'add')][] = $name;
                            }
                        }
                        else if ($attributeInstance instanceof Trashed) {
                            $withTrashed = true;
                        }
                    }

                    if (!is_null($routeVerbs)) {
                        $endpointsStruct[$idx]['methods'][] = ['methodName' => $methodName, 'routeVerbs' => $routeVerbs, 'routePath' => $routePath, 'routeName' => $routeName, 'middleware' => $routeMiddleware, 'withTrashed' => $withTrashed];
                    }
                }
            }

            file_put_contents($apiRoutesFilename, "<?php\n\n", FILE_APPEND);
            file_put_contents($apiRoutesFilename, "use Illuminate\Support\Facades\Route;\n", FILE_APPEND);

            $endpointsStruct = array_reverse($endpointsStruct);
            
            foreach ($endpointsStruct as $idx => $data) {
                file_put_contents($apiRoutesFilename, "use " . $data['use'] . ";\n", FILE_APPEND);
            }

            foreach ($uses as $use) {
                file_put_contents($apiRoutesFilename, $use . "\n", FILE_APPEND);
            }

            file_put_contents($apiRoutesFilename, "\n", FILE_APPEND);

            foreach ($endpointsStruct as $idx => $controllerData) {
                $item = '';
                $tCount = 0;

                if (count($controllerData['middleware']['add']) > 0) {
                    $item .= str_repeat("\t", $tCount++) . "Route::middleware([" . implode(',', $controllerData['middleware']['add']) . "])->group(function () {\n";
                }

                if (count($controllerData['middleware']['exclude']) > 0) {
                    $item .= str_repeat("\t", $tCount++) . "Route::withoutMiddleware([" . implode(',', $controllerData['middleware']['exclude']) . "])->group(function () {\n";
                }

                $item .= str_repeat("\t", $tCount++) . "Route::prefix('" . $controllerData['prefix'] . "')->group(function () {\n";
                $item .= str_repeat("\t", $tCount++) . "Route::controller(" . $controllerData['controller'] . "::class)->group(function () {\n";

                foreach ($controllerData['methods'] as $idx => $methodData) {
                    if (is_string($methodData['routeVerbs'])) {
                        $item .= str_repeat("\t", $tCount) . "Route::any('" . $methodData['routePath'] . "', '" . $methodData['methodName'] . "')";
                    }
                    else if (count($methodData['routeVerbs']) === 1) {
                        $item .= str_repeat("\t", $tCount) . "Route::" . $methodData['routeVerbs'][0] . "('" . $methodData['routePath'] . "', '" . $methodData['methodName'] . "')";
                    }
                    else { // count($methodData['routeVerbs']) > 1
                        $item .= str_repeat("\t", $tCount) . "Route::match([" . implode(',', $methodData['routeVerbs']) . "], '" . $methodData['routePath'] . "', '" . $methodData['methodName'] . "')";
                    }

                    if ($methodData['routeName'] !== '') {
                        $item .= "->name('" . $methodData['routeName'] . "')";
                    } 

                    if (count($methodData['middleware']['add']) > 0) {
                        $item .= "->middleware([" . implode(',', $methodData['middleware']['add']) . "])";
                    }

                    if (count($methodData['middleware']['exclude']) > 0) {
                        $item .= "->withoutMiddleware([" . implode(',', $methodData['middleware']['exclude']) . "])";
                    }

                    if ($methodData['withTrashed']) {
                        $item .= "->withTrashed()";
                    }

                    $item .= ";\n";
                }

                $item .= str_repeat("\t", --$tCount) . "});\n";
                $item .= str_repeat("\t", --$tCount) . "});\n";

                if (count($controllerData['middleware']['exclude']) > 0) {
                    $item .= str_repeat("\t", --$tCount) . "});\n";
                }

                if (count($controllerData['middleware']['add']) > 0) {
                    $item .= str_repeat("\t", --$tCount) . "});\n";
                }

                $item .= "\n";

                file_put_contents($apiRoutesFilename, $item, FILE_APPEND);
            }
        }
    }
}
