<?php

namespace Modules\Pos\app\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PosServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Pos';
    protected string $nameLower = 'pos';

    public function boot(): void
    {
        $this->registerAuthorization();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }

    private function registerAuthorization(): void
    {
        Gate::define('pos.access', fn ($user) => $user !== null && Gate::forUser($user)->allows('sale.create'));
        Gate::define('pos.close-session', fn ($user) => $user !== null && in_array($user->user_role, [
            'Super Admin',
            'Administrator',
            'Accountant',
        ], true));
    }

    protected function registerConfig(): void
    {
        $configPath = module_path($this->name, config('modules.paths.generator.config.path'));

        if (!is_dir($configPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace($configPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $key = $relativePath === 'config.php'
                    ? $this->nameLower
                    : $this->nameLower . '.' . str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $relativePath);

                $this->mergeConfigFrom($file->getPathname(), $key);
            }
        }
    }

    public function registerViews(): void
    {
        $sourcePath = module_path($this->name, 'resources/views');
        $viewPath = resource_path('views/modules/'.$this->nameLower);

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower.'-module-views']);
        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

        $componentNamespace = $this->module_namespace($this->name, $this->app_path(config('modules.paths.generator.component-class.path')));
        Blade::componentNamespace($componentNamespace, $this->nameLower);
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->nameLower)) {
                $paths[] = $path.'/modules/'.$this->nameLower;
            }
        }

        return $paths;
    }
}
