<?php

namespace think\blade;

use Illuminate\Contracts\View\View as ViewInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\FileEngine;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

use function Illuminate\Support\tap;

use think\Container;

use think\App;
use think\Config;
use think\Service;

/**
 * Standalone class for generating text using blade templates.
 */
class BladeService extends Service
{
    /**
     * string The default cache path.
     */
    private $compiled;

    /**
     * bool The default Whether to enable cacheing.
     */
    private $isCache;
    private $compiledExtension = 'php';

    /**
     * Factory|null The internal cache of the Factory to only instantiate it once.
     */
    private $factory;

    /**
     * BladeCompiler|null The internal cache of the BladeCompiler to only instantiate it once.
     */
    private $compiler;

    private $files = null;
    private $config = [];

    /**
     * Create a new instance of the blade view factory.
     *
     * @param App $app
     * @param string $cache The default path for cached php
     */
    public function boot(Config $config)
    {
        /*$this->config = $config->get('view');

        if (empty($this->config['compiled'])) {
            $this->config['compiled'] = $this->app->getRuntimePath(); //  . 'view' . DS
        }

        $this->compiled = $this->config['compiled'];
        $this->isCache = $this->config['cache'] ?? false;
        $this->compiledExtension = $this->config['compiled_extension'] ?? 'php';*/

        $this->compiled = $this->app->getRuntimePath();

        // if(class_exists('\think\app\MultiApp')) {}

        if (is_null($this->files)) {
            $this->files = new Filesystem;
        }

        $this->registerFactory();
        $this->registerViewFinder();
        $this->registerBladeCompiler();
        $this->registerEngineResolver();
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
                $this->app->get('view.finder')
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
        return new Factory($resolver, $finder, $this->app);
    }

    /**
     * Register the view finder implementation.
     *
     * @return void
     */
    public function registerViewFinder()
    {
        $this->app->bind('view.finder', function () {
            return new FileViewFinder($this->files, [
                $this->compiled,
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
                $this->files,
                // $this->compiled,
                // $this->isCache,
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
            return new FileEngine($this->files);
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
            return new PhpEngine($this->files);
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

            Container::getInstance()->resolving(function() use ($compiler) {
                $compiler->forgetCompiledOrNotExpired();
            });

            return $compiler;
        });
    }
}
