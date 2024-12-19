<?php

declare(strict_types=1);

namespace App;

use Illuminate\Foundation\Application as BaseApplication;

class Application extends BaseApplication
{
    protected function bindPathsInContainer(): void
    {
        $this->instance('path', $this->path());
        $this->instance('path.base', $this->basePath());
        $this->instance('path.config', $this->configPath());
        $this->instance('path.database', $this->databasePath());
        $this->instance('path.public', $this->publicPath());
        $this->instance('path.resources', $this->resourcePath());
        $this->instance('path.storage', $this->storagePath());

        $this->useBootstrapPath(value(function () {
            return realpath($this->basePath('.laravel')) && is_dir($directory = $this->basePath('.laravel'))
                ? $directory
                : $this->basePath('bootstrap');
        }));

        $this->useLangPath(value(function () {
            return realpath($this->resourcePath('lang')) && is_dir($directory = $this->resourcePath('lang'))
                ? $directory
                : $this->basePath('lang');
        }));
    }
}
