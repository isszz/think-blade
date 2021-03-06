<?php

namespace think\view\driver;

use Illuminate\Contracts\View\View as ViewInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\FileEngine;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use function is_dir;
use function mkdir;

/**
 * Standalone class for generating text using blade templates.
 */
class BladeInstance implements BladeInterface
{
    /**
     * string The default path for views.
     */
    private $path;

    /**
     * string The default cache path.
     */
    private $cachePath;

    /**
     * bool The default Whether to enable cacheing.
     */
    private $isCache;

    /**
     * Factory|null The internal cache of the Factory to only instantiate it once.
     */
    private $factory;

    /**
     * BladeCompiler|null The internal cache of the BladeCompiler to only instantiate it once.
     */
    private $compiler;

    /**
     * Create a new instance of the blade view factory.
     *
     * @param string $path The default path for views
     * @param string $cache The default path for cached php
     */
    public function __construct(string $path, string $cachePath, bool $isCache)
    {
        $this->path = $path;
        $this->cachePath = $cachePath;
        $this->isCache = $isCache;
    }

    /**
     * @return EngineResolver
     */
    private function getResolver(): EngineResolver
    {
        $resolver = new EngineResolver();

        $resolver->register("file", function () {
            return new FileEngine();
        });

        $resolver->register("php", function () {
            return new PhpEngine();
        });
        
        $resolver->register("blade", function () {
            $compiler =  $this->getCompiler();

            // Bind blade as think app
            \think\Container::getInstance()->bind('blade.compiler', $compiler);

            return new CompilerEngine($compiler);
        });

        return $resolver;
    }

    /**
     * Get the laravel view factory.
     *
     * @return Factory
     */
    public function getViewFactory(): Factory
    {
        if ($this->factory) {
            return $this->factory;
        }
        $this->factory = new Factory($this->getResolver());

        return $this->factory;
    }

    /**
     * Get the internal compiler in use.
     *
     * @return BladeCompiler
     */
    private function getCompiler(): BladeCompiler
    {
        if ($this->compiler) {
            return $this->compiler;
        }

        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0777, true);
        }

        $blade = new BladeCompiler(new Filesystem, $this->cachePath, $this->isCache);

        $this->compiler = $blade;

        return $this->compiler;
    }

    /**
     * {@inheritdoc}
     */
    public function addExtension(string $extension): BladeInterface
    {
        $this
            ->getViewFactory()
            ->addExtension($extension, "blade");

        return $this;
    }

    /**
     * Register a custom Blade compiler.
     *
     * @param callable $compiler
     *
     * @return $this
     */
    public function extend(callable $compiler): BladeInterface
    {
        $this
            ->getCompiler()
            ->extend($compiler);

        return $this;
    }

    /**
     * Register a handler for custom directives.
     *
     * @param string $name
     * @param callable $handler
     *
     * @return $this
     */
    public function directive(string $name, callable $handler): BladeInterface
    {
        $this
            ->getCompiler()
            ->directive($name, $handler);

        return $this;
    }

    /**
     * Register an "if" statement directive.
     *
     * @param  string  $name
     * @param  callable  $callback
     * @return $this
     */
    public function if(string $name, callable $handler): BladeInterface
    {
        $this
            ->getCompiler()
            ->if($name, $handler);

        return $this;
    }

    /**
     * Check if a view exists.
     *
     * @param string $view The name of the view to check
     *
     * @return bool
     */
    public function exists($view): bool
    {
        return $this->getViewFactory()->exists($view);
    }

    /**
     * Share data across all views.
     *
     * @param string $key The name of the variable to share
     * @param mixed $value The value to assign to the variable
     *
     * @return $this
     */
    public function share($key, $value = null): BladeInterface
    {
        $this->getViewFactory()->share($key, $value);

        return $this;
    }

    /**
     * Register a composer.
     *
     * @param string $key The name of the composer to register
     * @param mixed $value The closure or class to use
     *
     * @return array
     */
    public function composer($key, $value): array
    {
        return [];
    }

    /**
     * Register a creator.
     *
     * @param string $key The name of the creator to register
     * @param mixed $value The closure or class to use
     *
     * @return array
     */
    public function creator($key, $value): array
    {
        return [];
    }

    /**
     * Get the evaluated view contents for the given path.
     *
     * @param string $path The path of the file to use
     * @param array $data The parameters to pass to the view
     * @param array $mergeData The extra data to merge
     *
     * @return ViewInterface The generated view
     */
    public function file($path, $data = [], $mergeData = []): ViewInterface
    {
        return $this->getViewFactory()->file($path, $data, $mergeData);
    }

    /**
     * Generate a view.
     *
     * @param string $view The name of the view to make
     * @param array $params The parameters to pass to the view
     * @param array $mergeData The extra data to merge
     *
     * @return ViewInterface The generated view
     */
    public function make($view, $params = [], $mergeData = []): ViewInterface
    {
        return $this->getViewFactory()->make($view, $params, $mergeData);
    }

    /**
     * Get the content by generating a view.
     *
     * @param string $view The name of the view to make
     * @param array $params The parameters to pass to the view
     *
     * @return string The generated content
     */
    public function render(string $view, array $params = []): string
    {
        return $this->make($view, $params)->render();
    }
}
