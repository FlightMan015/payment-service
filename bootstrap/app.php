<?php

declare(strict_types=1);

use Aptive\Component\Illuminate\CommandBus\ContainerLocator;
use Aptive\Component\JsonApi\JsonApi;
use ConfigCat\Cache\LaravelCache;
use ConfigCat\ClientInterface;
use ConfigCat\ClientOptions;
use ConfigCat\ConfigCatClient;
use ConfigCat\Log\LogLevel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use League\Tactician\CommandBus;
use League\Tactician\Handler\CommandHandlerMiddleware;
use League\Tactician\Handler\CommandNameExtractor\ClassNameExtractor;
use League\Tactician\Handler\MethodNameInflector\InvokeInflector;
use Psr\Log\LoggerInterface;

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new App\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

JsonApi::config(['debug' => env(key: 'APP_ENV', default: 'local') === 'local']);

$app->singleton(CommandBus::class, static function (Application $app) {
    $mw = new CommandHandlerMiddleware(new ClassNameExtractor(), new ContainerLocator($app), new InvokeInflector());
    return new CommandBus([$mw]);
});

$app->singleton(
    abstract: ClientInterface::class,
    concrete: static fn () => new ConfigCatClient(
        sdkKey: config('configcat.auth.sdk_key'),
        options: [
            ClientOptions::LOG_LEVEL => config('configcat.log_level', LogLevel::NOTICE),
            ClientOptions::LOGGER => $app->make(LoggerInterface::class),
            ClientOptions::CACHE => new LaravelCache(Cache::store()),
            ClientOptions::CACHE_REFRESH_INTERVAL => 300 // 300 Seconds or 5 Mins is the cache TTL
        ]
    )
);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;
