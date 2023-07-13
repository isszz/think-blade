<?php

namespace Illuminate\View;

use think\Container;
// use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\View\Factory as FactoryContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Illuminate\View\Engines\EngineResolver;
use InvalidArgumentException;

use think\blade\ViewNotFoundException;

use function Illuminate\Support\tap;

use think\App;

class Factory implements FactoryContract
{
    use Macroable,
        Concerns\ManagesComponents,
        Concerns\ManagesFragments,
        Concerns\ManagesLayouts,
        Concerns\ManagesLoops,
        Concerns\ManagesStacks,
        Concerns\ManagesTranslations;

    // 模板引擎参数
    protected $config = [
        // 视图目录名
        'dir_name' => 'view',
        // 模版主题
        'theme' => '',
        // 模板起始路径
        'base_path' => '',
        // 模板文件后缀
        'suffix' => 'blade.php',
        // 模板文件名分隔符
        'depr' => DS,
        // 缓存路径
        'compiled' => '',
        // 是否开启模板编译缓存,设为false则每次都会重新编译
        'cache' => true,
    ];

    /**
     * The engine implementation.
     *
     * @var \Illuminate\View\Engines\EngineResolver
     */
    protected $engines;

    /**
     * The view finder implementation.
     *
     * @var \Illuminate\View\ViewFinderInterface
     */
    protected $finder;

    /**
     * The IoC container instance.
     *
     * @var \think\Container
     */
    protected $container;

    /**
     * Data that should be available to all templates.
     *
     * @var array
     */
    protected $shared = [];

    /**
     * The extension to engine bindings.
     *
     * @var array
     */
    protected $extensions = [
        'html.php' => 'blade',
        'blade.php' => 'blade',
        'php' => 'php',
        'css' => 'file',
        'html' => 'file',
    ];

    /**
     * The view composer events.
     *
     * @var array
     */
    protected $composers = [];

    /**
     * The number of active rendering operations.
     *
     * @var int
     */
    protected $renderCount = 0;

    /**
     * The "once" block IDs that have been rendered.
     *
     * @var array
     */
    protected $renderedOnce = [];

    protected $app;

    /**
     * Create a new view factory instance.
     *
     * @param  \Illuminate\View\Engines\EngineResolver  $engines
     * @param  \Illuminate\View\ViewFinderInterface  $finder
     * @return void
     */
    public function __construct(EngineResolver $engines, $finder, App $app)
    {
        $this->app = $app;
        $this->finder = $finder;
        $this->engines = $engines;

        $config = $this->app->get('config')->get('view');

        $this->config = array_merge($this->config, $config);

        if (empty($this->config['compiled'])) {
            $this->config['compiled'] = $app->getRuntimePath();
        }

        // 缓存主题路径
        if (!empty($this->config['theme'])) {
            $this->config['compiled'] .= $this->config['theme'] . DS;
        }

        // default view path
        $cutrrentApp = $this->app->http->getName();

        if ($this->config['base_path']) {
            $path = $this->config['base_path'];
        } else {
            $appName = $cutrrentApp;
            $view = $this->config['dir_name'];

            if (is_dir($this->app->getAppPath() . $view)) {
                $path = $this->app->getAppPath() . $view . DS;
            } else {
                $path = $this->app->getRootPath() . $view . DS . ($appName ? $appName . DS : '');
            }
        }

        // 设置主题路径
        if (!empty($this->config['theme'])) {
            // default 主题备用
            $path .= $this->config['theme'] . DS;
        }


        // dd($path);
        // $finder->addLocation($path); //  . 'components'. DS

        // debug 不缓存
        if ($this->app->isDebug()) {
            // $this->config['cache'] = false;
        }

        $this->share('__env', $this);
    }

    /**
     * 设置模板主题
     *
     * @param  string $path 模板文件路径
     * @return bool
     */
    public function theme($path = '')
    {
        if (empty($this->config['theme'])) {
            return $path;
        }

        return $path .= $this->config['theme'] . DS;
    }

    /**
     * 根据模版获取实际路径
     *
     * @param  string $path 模板文件路径
     * @return bool
     */
    public function findView($template = '')
    {
        $templatePath = '';

        $template = $this->viewName($template);

        if ('' == pathinfo($template, PATHINFO_EXTENSION)) {
            $templatePath = $this->parseTemplate($template);
        }

        // 模板不存在 抛出异常
        if (!$templatePath || !is_file($templatePath)) {
            throw new ViewNotFoundException('View not exists:' . $this->viewName($template, true), $templatePath);
        }

        return $templatePath;
    }


    /**
     * 检测是否存在模板文件
     *
     * @param  string $view 模板文件或者模板规则
     * @return bool
     */
    public function exists(string $view): bool
    {
        $view = $this->viewName($view);

        if ('' == pathinfo($view, PATHINFO_EXTENSION)) {
            $view = $this->parseTemplate($view);
        }

        return is_file($view);
    }

    /**
     * Get the evaluated view contents for the given view.
     *
     * @param  string  $view
     * @param  \Illuminate\Contracts\Support\Arrayable|array   $data
     * @param  array   $mergeData
     * @return \Illuminate\Contracts\View\View
     */
    public function make($view, $data = [], $mergeData = [])
    {
        if (is_file($view)) {
            $path = $view;
        } else {
            $path = '';
            // $view = ViewName::normalize2($view);

            $view = $this->viewName($view);

            if ('' == pathinfo($view, PATHINFO_EXTENSION)) {
                $path = $this->parseTemplate($view);
            }

            if (!$path || !is_file($path)) {
                $path = $this->finder->find(
                    $this->normalizeName($view)
                );
            }
        }

        // 模板不存在 抛出异常
        if (!$path || !is_file($path)) {
            throw new ViewNotFoundException('View not exists:' . $this->viewName($view, true), $path);
        }

        // 记录视图信息
        $this->app['log']
            ->record('[ VIEW ] ' . $view . ' [ ' . var_export(array_keys($data), true) . ' ]');

        // Next, we will create the view instance and call the view creator for the view
        // which can set any data, etc. Then we will return the view instance back to
        // the caller for rendering or performing other view manipulations on this.
        $data = array_merge($mergeData, $this->parseData($data));

        return tap($this->viewInstance($path, $path, $data), function ($view) {
            $this->callCreator($view);
        });
    }

    /**
     * 渲染模板内容
     *
     * @param  string $view 模板内容
     * @param  array  $data 模板变量
     * @param  array   $mergeData
     * @return void
     */
    public function display(string $view, array $data = [], array $mergeData = [])
    {
        $path = $this->finder->find(
            $this->normalizeName($view)
        );

        return $this->make($path, $data, $mergeData);
    }

    /**
     * 渲染模板文件 | think
     *
     * @param string $view 模板文件
     * @param array  $data     模板变量
     * @param  array   $mergeData
     * @return void
     */
    public function fetch(string $view, array $data = [], array $mergeData = [])
    {
        return $this->make($view, $data, $mergeData);
    }

    /**
     * 获取模版所在基础路径
     *
     * @param string $template 模板文件规则
     * @return string
     */
    private function parseBasePath(string $template): string
    {
        // 获取视图根目录
        if (strpos($template, '@')) {
            // 跨应用调用
            [$app, $template] = explode('@', $template);
        }

        $cutrrentApp = $this->app->http->getName();

        if ($this->config['base_path'] && !isset($app)) {
            $path = $this->config['base_path'];
        } else {
            $appName = isset($app) ? $app : $cutrrentApp;
            $view = $this->config['dir_name'];

            if (is_dir($this->app->getAppPath() . $view)) {
                $path = isset($app) ? $this->app->getBasePath() . ($appName ? $appName . DS : '') . $view . DS : $this->app->getAppPath() . $view . DS;
            } else {
                $path = $this->app->getRootPath() . $view . DS . ($appName ? $appName . DS : '');
            }
        }

        // 设置主题路径
        if (!empty($this->config['theme'])) {
            // default 主题备用
            $path .= $this->config['theme'] . DS;
        }

        return $path;
    }

    /**
     * 自动定位模板文件
     *
     * @param string $template 模板文件规则
     * @return string
     */
    private function parseTemplate(string $template): string
    {
        $depr = $this->config['depr'];
        $request = $this->app->request;
        $path = $this->parseBasePath($template);

        if (0 !== strpos($template, '/')) {
            $template   = str_replace(['/', ':'], $depr, $template);
            $controller = $request->controller();
            if (strpos($controller, '.')) {
                $pos        = strrpos($controller, '.');
                $controller = substr($controller, 0, $pos) . '.' . Str::snake(substr($controller, $pos + 1));
            } else {
                $controller = Str::snake($controller);
            }

            if ($controller) {
                if ('' == $template) {
                    // 如果模板文件名为空 按照默认规则定位
                    if (2 == $this->config['auto_rule']) {
                        $template = $request->action(true);
                    } elseif (3 == $this->config['auto_rule']) {
                        $template = $request->action();
                    } else {
                        $template = Str::snake($request->action());
                    }

                    $template = str_replace('.', DS, $controller) . $depr . $template;
                } elseif (false === strpos($template, $depr)) {
                    $template = str_replace('.', DS, $controller) . $depr . $template;
                }
            }
        } else {
            $template = str_replace(['/', ':'], $depr, substr($template, 1));
        }

        $template = $path . ltrim($template, '/') . '.' . ltrim($this->config['suffix'], '.');

        if (is_file($template)) {
            return $template;
        }

        // dd($template);
        // 未设置主题, 尝试先去default查找
        if(empty($this->config['theme'])) {

            $default = str_replace(DS .'view'. DS, DS .'view'. DS .'default'. DS, $template);

            if (is_file($default)) {
                return $default;
            }
        }

        // 默认主题不存在模版, 降级删除default主题继续查找
        if (strpos($template, DS .'view'. DS . 'default' . DS) !== false) {

            $default = str_replace(DS .'view'. DS . 'default' . DS, DS .'view'. DS, $template);

            if (is_file($default)) {
                return $default;
            }
        }

        // 已设置主题, 但是找不到模版, 尝试降级为default主题
        if (strpos($template, DS .'view'. DS . $this->config['theme'] . DS) !== false) {

            $default = str_replace(
                DS . $this->config['theme'] . DS,
                DS . 'default' . DS, $template
            );

            if (is_file($default)) {
                return $default;
            }
        }

        return '';
    }

    /**
     * Normalize the given template.
     *
     * @param  string  $name
     * @return string
     */
    private function viewName($template = '', $isRaw = false)
    {

        if($isRaw && strpos($template, '/')) {
            return str_replace('/', '.', $template);
        }

        if (strpos($template, '.')) {
            $template = str_replace('.', '/', $template);
        }

        return $template;
    }

    /**
     * Get the evaluated view contents for the given view.
     *
     * @param  string  $path
     * @param  \Illuminate\Contracts\Support\Arrayable|array   $data
     * @param  array   $mergeData
     * @return \Illuminate\Contracts\View\View
     */
    public function file($path, $data = [], $mergeData = [])
    {
        $data = array_merge($mergeData, $this->parseData($data));

        return tap($this->viewInstance($path, $path, $data), function ($view) {
            $this->callCreator($view);
        });
    }


    /**
     * Get the first view that actually exists from the given list.
     *
     * @param  array  $views
     * @param  \Illuminate\Contracts\Support\Arrayable|array   $data
     * @param  array   $mergeData
     * @return \Illuminate\Contracts\View\View
     *
     * @throws \InvalidArgumentException
     */
    public function first(array $views, $data = [], $mergeData = [])
    {
        $view = Arr::first($views, function ($view) {
            return $this->exists($view);
        });

        if (! $view) {
            throw new InvalidArgumentException('None of the views in the given array exist.');
        }

        return $this->make($view, $data, $mergeData);
    }

    /**
     * Get the rendered content of the view based on a given condition.
     *
     * @param  bool  $condition
     * @param  string  $view
     * @param  \Illuminate\Contracts\Support\Arrayable|array   $data
     * @param  array   $mergeData
     * @return string
     */
    public function renderWhen($condition, $view, $data = [], $mergeData = [])
    {
        if (! $condition) {
            return '';
        }

        return $this->make($view, $this->parseData($data), $mergeData)->render();
    }

    /**
     * Get the rendered contents of a partial from a loop.
     *
     * @param  string  $view
     * @param  array   $data
     * @param  string  $iterator
     * @param  string  $empty
     * @return string
     */
    public function renderEach($view, $data, $iterator, $empty = 'raw|')
    {
        $result = '';

        // If is actually data in the array, we will loop through the data and append
        // an instance of the partial view to the final result HTML passing in the
        // iterated value of this data array, allowing the views to access them.
        if (count($data) > 0) {
            foreach ($data as $key => $value) {
                $result .= $this->make(
                    $view, ['key' => $key, $iterator => $value]
                )->render();
            }
        }

        // If there is no data in the array, we will render the contents of the empty
        // view. Alternatively, the "empty view" could be a raw string that begins
        // with "raw|" for convenience and to let this know that it is a string.
        else {
            $result = Str::startsWith($empty, 'raw|')
                        ? substr($empty, 4)
                        : $this->make($empty)->render();
        }

        return $result;
    }

    /**
     * Normalize a view name.
     *
     * @param  string $name
     * @return string
     */
    protected function normalizeName($name)
    {
        return ViewName::normalize($name);
    }

    /**
     * Parse the given data into a raw array.
     *
     * @param  mixed  $data
     * @return array
     */
    protected function parseData($data)
    {
        return $data instanceof Arrayable ? $data->toArray() : $data;
    }

    /**
     * Create a new view instance from the given arguments.
     *
     * @param  string  $view
     * @param  string  $path
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $data
     * @return \Illuminate\Contracts\View\View
     */
    protected function viewInstance($view, $path, $data)
    {
        return new View($this, $this->getEngineFromPath($path), $view, $path, $data);
    }


    /**
     * Get the appropriate view engine for the given path.
     *
     * @param  string  $path
     * @return \Illuminate\Contracts\View\Engine
     *
     * @throws \InvalidArgumentException
     */
    public function getEngineFromPath($path)
    {
        if (! $extension = $this->getExtension($path)) {
            throw new InvalidArgumentException("Unrecognized extension in file: {$path}");
        }

        $engine = $this->extensions[$extension];


        return $this->engines->resolve($engine);
    }

    /**
     * Get the extension used by the view file.
     *
     * @param  string  $path
     * @return string
     */
    protected function getExtension($path)
    {
        $extensions = array_keys($this->extensions);

        return Arr::first($extensions, function ($value) use ($path) {
            return Str::endsWith($path, '.'.$value);
        });
    }

    /**
     * Add a piece of shared data to the environment.
     *
     * @param  array|string  $key
     * @param  mixed|null  $value
     * @return mixed
     */
    public function share($key, $value = null)
    {
        $keys = is_array($key) ? $key : [$key => $value];

        foreach ($keys as $key => $value) {
            $this->shared[$key] = $value;
        }

        return $value;
    }

    /**
     * Add a piece of shared data to the environment.
     *
     * @param  array|string  $key
     * @param  mixed|null  $value
     * @return mixed
     */
    public function assign($key, $value = null)
    {
        return $this->share($key, $value);
    }

    /**
     * Increment the rendering counter.
     *
     * @return void
     */
    public function incrementRender()
    {
        $this->renderCount++;
    }

    /**
     * Decrement the rendering counter.
     *
     * @return void
     */
    public function decrementRender()
    {
        $this->renderCount--;
    }

    /**
     * Check if there are no active render operations.
     *
     * @return bool
     */
    public function doneRendering()
    {
        return $this->renderCount == 0;
    }

    /**
     * Determine if the given once token has been rendered.
     *
     * @param  string  $id
     * @return bool
     */
    public function hasRenderedOnce(string $id)
    {
        return isset($this->renderedOnce[$id]);
    }

    /**
     * Mark the given once token as having been rendered.
     *
     * @param  string  $id
     * @return void
     */
    public function markAsRenderedOnce(string $id)
    {
        $this->renderedOnce[$id] = true;
    }

    /**
     * Add a location to the array of view locations.
     *
     * @param  string  $location
     * @return void
     */
    public function addLocation($location)
    {
        $this->finder->addLocation($location);
    }

    /**
     * Add a new namespace to the loader.
     *
     * @param  string  $namespace
     * @param  string|array  $hints
     * @return $this
     */
    public function addNamespace($namespace, $hints)
    {
        $this->finder->addNamespace($namespace, $hints);

        return $this;
    }

    /**
     * Prepend a new namespace to the loader.
     *
     * @param  string  $namespace
     * @param  string|array  $hints
     * @return $this
     */
    public function prependNamespace($namespace, $hints)
    {
        $this->finder->prependNamespace($namespace, $hints);

        return $this;
    }

    /**
     * Replace the namespace hints for the given namespace.
     *
     * @param  string  $namespace
     * @param  string|array  $hints
     * @return $this
     */
    public function replaceNamespace($namespace, $hints)
    {
        $this->finder->replaceNamespace($namespace, $hints);

        return $this;
    }

    /**
     * Register a valid view extension and its engine.
     *
     * @param  string    $extension
     * @param  string    $engine
     * @param  \Closure|null  $resolver
     * @return void
     */
    public function addExtension($extension, $engine, $resolver = null)
    {
        if (isset($resolver)) {
            $this->engines->register($engine, $resolver);
        }

        unset($this->extensions[$extension]);

        $this->extensions = array_merge([$extension => $engine], $this->extensions);
    }

    /**
     * Flush all of the factory state like sections and stacks.
     *
     * @return void
     */
    public function flushState()
    {
        $this->renderCount = 0;
        $this->renderedOnce = [];

        $this->flushSections();
        $this->flushStacks();
        $this->flushFragments();
    }

    /**
     * Flush all of the section contents if done rendering.
     *
     * @return void
     */
    public function flushStateIfDoneRendering()
    {
        if ($this->doneRendering()) {
            $this->flushState();
        }
    }

    /**
     * Get the extension to engine bindings.
     *
     * @return array
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * Get the engine resolver instance.
     *
     * @return \Illuminate\View\Engines\EngineResolver
     */
    public function getEngineResolver()
    {
        return $this->engines;
    }

    /**
     * Get the IoC container instance.
     *
     * @return \think\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set the IoC container instance.
     *
     * @param  \think\Container  $container
     * @return void
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Get an item from the shared data.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function shared($key, $default = null)
    {
        return Arr::get($this->shared, $key, $default);
    }

    /**
     * Get all of the shared data for the environment.
     *
     * @return array
     */
    public function getShared()
    {
        return $this->shared;
    }

    /**
     * ManagesEvents
     * Call the composer for a given view.
     *
     * @param  \Illuminate\Contracts\View\View  $view
     * @return void
     */
    public function callComposer($view)
    {
    }

    /**
     * ManagesEvents
     * Call the creator for a given view.
     *
     * @param  \Illuminate\Contracts\View\View  $view
     * @return void
     */
    public function callCreator($view)
    {
    }
}
