<?php
declare(strict_types=1);

namespace think\view\driver;

use Illuminate\Contracts\View\View as ViewInterface;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\FileEngine;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
// use Illuminate\View\FileViewFinder;

use think\App;

use function is_dir;
use function mkdir;
use function Illuminate\Support\tap;

/**
 * Standalone class for generating text using blade templates.
 */
class BladeInstance
{
    /**
     * app
     */
    private $app;

    /**
     * string The default path for views.
     */
    private $path;

    /**
     * bool The default Whether to enable cacheing.
     */
    private $isCache;

    private $files = null;

    /**
     * Create a new instance of the blade view factory.
     *
     * @param string $path The default path for views
     * @param string $cache The default path for cached php
     */
    public function __construct(App $app, string $path, string $cachePath, bool $isCache)
    {
        $this->app = $app;
        $this->path = $path;
        $this->cachePath = $cachePath;
        $this->isCache = $isCache;

        $this->registerFactory();
        // $this->registerViewFinder();
        $this->registerBladeCompiler();
        $this->registerEngineResolver();

        return $this;
    }

    /**
     * Register the view environment.
     *
     * @return void
     */
    public function registerFactory()
    {
        $this->app->bind('blade.view', function () {
            $factory = $this->createFactory(
                $this->app->get('view.engine.resolver'),
                null,
                // $this->app->get('view.finder')
            );

            // We will also set the container instance on this view environment since the
            // view composers may be classes registered in the container, which allows
            // for great testable, flexible composers for the application developer.
            $factory->setContainer($this->app);

            $factory->share('app', $this->app);

            return $factory;
        });
    }

    /**
     * Create a new Factory Instance.
     *
     * @param  \Illuminate\View\Engines\EngineResolver  $resolver
     * @param  \Illuminate\View\ViewFinderInterface  $finder
     * @return \Illuminate\View\Factory
     */
    protected function createFactory($resolver, $finder)
    {
        return new Factory($this->app, $resolver, $finder);
    }

    /**
     * Register the view finder implementation.
     *
     * @return void
     */
    public function registerViewFinder()
    {
        $this->app->bind('view.finder', function () {
            return new FileViewFinder([
                $this->path,
            ]);
        });
    }

    /**
     * Register the Blade compiler implementation.
     *
     * @return void
     */
    public function registerBladeCompiler()
    {
        $this->app->bind('blade.compiler', function () {
            return tap(new BladeCompiler(
                $this->cachePath,
                $this->isCache,
                // $this->compiledExtension,
            ), function ($blade) {
                $blade->component('dynamic-component', \Illuminate\View\DynamicComponent::class);
            });
        });
    }

    /**
     * Register the engine resolver instance.
     *
     * @return void
     */
    public function registerEngineResolver()
    {
        $this->app->bind('view.engine.resolver', function () {
            $resolver = new EngineResolver;

            // Next, we will register the various view engines with the resolver so that the
            // environment will resolve the engines needed for various views based on the
            // extension of view file. We call a method for each of the view's engines.
            foreach (['file', 'php', 'blade'] as $engine) {
                $this->{'register'. ucfirst($engine) .'Engine'}($resolver);
            }

            return $resolver;
        });
    }

    /**
     * Register the file engine implementation.
     *
     * @param  \Illuminate\View\Engines\EngineResolver  $resolver
     * @return void
     */
    public function registerFileEngine($resolver)
    {
        $resolver->register('file', function () {
            return new FileEngine();
        });
    }

    /**
     * Register the PHP engine implementation.
     *
     * @param  \Illuminate\View\Engines\EngineResolver  $resolver
     * @return void
     */
    public function registerPhpEngine($resolver)
    {
        $resolver->register('php', function () {
            return new PhpEngine();
        });
    }

    /**
     * Register the Blade engine implementation.
     *
     * @param  \Illuminate\View\Engines\EngineResolver  $resolver
     * @return void
     */
    public function registerBladeEngine($resolver)
    {
        $resolver->register('blade', function () {
            $compiler = new CompilerEngine($this->app->get('blade.compiler'));

            $this->app->resolving(function() use ($compiler) {
                $compiler->forgetCompiledOrNotExpired();
            });

            return $compiler;
        });
    }

    /**
     * Get the laravel view factory.
     *
     * @return Factory
     */
    public function getViewFactory(): Factory
    {
        return $this->app->get('blade.view');
    }

    public function __call($method, $params)
    {
        return call_user_func_array([$this->app->get('blade.compiler'), $method], $params);
    }
}
