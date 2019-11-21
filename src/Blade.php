<?php

declare(strict_types=1);

namespace think\view\driver;

use think\App;
use think\helper\Str;
use think\contract\TemplateHandlerInterface;

class Blade implements TemplateHandlerInterface
{
    // Blade 引擎实例
    private $blade;
    private $app;

    // 模板引擎参数
    protected $config = [
        // 默认模板渲染规则 1 解析为小写+下划线 2 全部转换小写 3 保持操作方法
        'auto_rule' => 1,
        // 视图目录名
        'view_dir_name' => 'view',
        // 模版主题
        'view_theme' => '',
        // 模板起始路径
        'view_path' => '',
        // 模板文件后缀
        'view_suffix' => 'blade.php',
        // 模板文件名分隔符
        'view_depr' => DIRECTORY_SEPARATOR,
        // 缓存路径
        'cache_path' => '',
        // 是否开启模板编译缓存,设为false则每次都会重新编译
        'tpl_cache' => true,
    ];

    public function __construct(App $app, array $config = [])
    {
        $this->app = $app;

        $this->config = array_merge($this->config, $config);

        if (empty($this->config['cache_path'])) {
            $this->config['cache_path'] = $app->getRuntimePath() . 'view' . DIRECTORY_SEPARATOR;
        }

        // 缓存主题路径
        if (!empty($this->config['view_theme'])) {
            $this->config['cache_path'] .= $this->config['view_theme'] . DIRECTORY_SEPARATOR;
        }

        // debug 不缓存
        if ($this->app->isDebug()) {
            $this->config['tpl_cache'] = false;
        }

        $this->config(array_merge($config, $this->config));

        $this->blade = new BladeInstance('', $this->config['cache_path'], $this->config['tpl_cache']);
        $this->blade->getViewFactory();
    }

    /**
     * 设置模板主题
     *
     * @param  string $path 模板文件路径
     * @return bool
     */
    public function theme($path = '')
    {
        if (empty($this->config['view_theme'])) {
            return $path;
        }

        return $path .= $this->config['view_theme'] . DIRECTORY_SEPARATOR;
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
     * @param  string $template 模板文件或者模板规则
     * @return bool
     */
    public function exists(string $template): bool
    {
        $template = $this->viewName($template);

        if ('' == pathinfo($template, PATHINFO_EXTENSION)) {
            $template = $this->parseTemplate($template);
        }

        return is_file($template);
    }
    
    /**
     * 渲染模板文件
     *
     * @param string $template 模板文件
     * @param array  $data     模板变量
     * @return void
     */
    public function fetch(string $template, array $data = []): void
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

        // 记录视图信息
        $this->app['log']
            ->record('[ VIEW ] ' . $template . ' [ ' . var_export(array_keys($data), true) . ' ]');

        echo $this->blade->file($templatePath, $data)->render();
    }

    /**
     * 渲染模板内容
     *
     * @param  string $template 模板内容
     * @param  array  $data 模板变量
     * @return void
     */
    public function display(string $template, array $data = []): void
    {
        echo $this->fetch($template, $data);
        // echo $this->blade->make($template, $data)->render();
    }

    /**
     * 自动定位模板文件
     *
     * @param string $template 模板文件规则
     * @return string
     */
    private function parseTemplate(string $template): string
    {
        $request = $this->app->request;

        // 获取视图根目录
        if (strpos($template, '@')) {
            // 跨应用调用
            list($app, $template) = explode('@', $template);
        }

        if ($this->config['view_path'] && !isset($app)) {
            $path = $this->config['view_path'];
        } else {
            $appName = isset($app) ? $app : $this->app->http->getName();
            $view = $this->config['view_dir_name'];

            if (is_dir($this->app->getAppPath() . $view)) {
                $path = isset($app) ? $this->app->getBasePath() . ($appName ? $appName . DIRECTORY_SEPARATOR : '') . $view . DIRECTORY_SEPARATOR : $this->app->getAppPath() . $view . DIRECTORY_SEPARATOR;
            } else {
                $path = $this->app->getRootPath() . $view . DIRECTORY_SEPARATOR . ($appName ? $appName . DIRECTORY_SEPARATOR : '');
            }
        }

        // 设置主题路径
        if (!empty($this->config['view_theme'])) {
            // default 主题备用
            $path .= $this->config['view_theme'] . DIRECTORY_SEPARATOR;
        }

        $depr = $this->config['view_depr'];

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

                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . $template;
                } elseif (false === strpos($template, $depr)) {
                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . $template;
                }
            }
        } else {
            $template = str_replace(['/', ':'], $depr, substr($template, 1));
        }

        $template = $path . ltrim($template, '/') . '.' . ltrim($this->config['view_suffix'], '.');

        // 模板不存在, 尝试默认模板
        if (!is_file($template) && !empty($this->config['view_theme']) && $this->config['view_theme'] !== 'default') {

            $default = str_replace(
                DIRECTORY_SEPARATOR . $this->config['view_theme'] . DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR, $template
            );
            
            if (is_file($default)) {
                return $default;
            }
        }

        return $template;
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
     * 配置模板引擎
     * 
     * @param  array  $config 参数
     * @return void
     */
    public function config(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 获取模板引擎配置
     *
     * @param  string  $name 参数名
     * @return mixed
     */
    public function getConfig(string $name)
    {
        return $this->config[$name];
    }

    public function __call($method, $params)
    {
        return call_user_func_array([$this->blade, $method], $params);
    }

    public function __debugInfo()
    {
        return [
            'config' => $this->config,
            'blade' => $this->blade
        ];
    }
}
