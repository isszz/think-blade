<?php

declare(strict_types=1);

namespace think\view\driver;

use think\App;
use Illuminate\Support\Str;
use think\contract\TemplateHandlerInterface;

class Blade implements TemplateHandlerInterface
{
    // Blade 引擎实例
    private $blade;
    private $app;

    // 模板引擎参数
    protected $config = [
        // 模版主题
        'theme' => '',
        // 缓存路径
        'compiled' => '',
        // 默认模板渲染规则 1 解析为小写+下划线 2 全部转换小写 3 保持操作方法
        'auto_rule' => 1,
        // 视图目录名
        'view_dir_name' => 'view',
        // 模板起始路径
        'view_path' => '',
        // 模板后缀
        'view_suffix' => 'html.php',
        // 模板文件名分隔符
        'view_depr' => DIRECTORY_SEPARATOR,
        // 是否开启模板编译缓存,设为false则每次都会重新编译
        'tpl_cache' => true,
    ];

    public function __construct(App $app, array $config = [])
    {
        $this->app = $app;
        $this->config = array_merge($this->config, (array) $config);

        if (empty($this->config['compiled'])) {
            $this->config['compiled'] = $app->getRuntimePath() . 'view' . DIRECTORY_SEPARATOR;
        }

        // 缓存主题路径
        if (!empty($this->config['theme'])) {
            $this->config['compiled'] .= $this->config['theme'] . DIRECTORY_SEPARATOR;
        }

        // debug 不缓存
        if ($this->app->isDebug()) {
            $this->config['tpl_cache'] = false;
        }

        if (empty($this->config['view_path'])) {
            $path = $app->getAppPath() .'view'. DS;
        } else {
            $path = realpath($this->config['view_path']) . DS .'view'. DS;
        }

        $this->blade = (new BladeInstance(
            $app,
            $path,
            $this->config['compiled'],
            $this->config['tpl_cache'],
        ))->getViewFactory();

        $this->blade->addExtension($this->config['view_suffix'] ?: 'html.php', 'blade');
    }

    /**
     * 检测是否存在模板文件
     *
     * @param  string $template 模板文件或者模板规则
     * @return bool
     */
    public function exists(string $template): bool
    {
        $template = $this->normalize($template);

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

        $template = $this->normalize($template);

        if ('' == pathinfo($template, PATHINFO_EXTENSION)) {
            $templatePath = $this->parseTemplate($template);
        }

        // 模板不存在 抛出异常
        if (!$templatePath || !is_file($templatePath)) {

            $app = $this->app->http->getName();
            $controller = $this->app->request->controller();

            $errorTemplate = $this->normalize($template, true);
            if (strpos($template, '@') === false && strpos($template, '/') === false) {
                $errorTemplate = $app .'@'. $controller .'.'. $errorTemplate;
            }

            throw new ViewNotFoundException(
                'View not exists: ' . $errorTemplate,
                $templatePath,
                $this->app->http->getName() .'@'. $this->app->request->controller(),
            );

            throw new ViewNotFoundException('View not exists:' . $this->normalize($template, true), $templatePath);
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

        $app = null;
        $depr = $this->config['view_depr'];
        $view = $this->config['view_dir_name'] ?: 'view';

        // 获取视图根目录
        if (strpos($template, '@')) {
            // 跨应用调用
            [$app, $template] = explode('@', $template);
        }

        // 多应用模式
        if(class_exists('\think\app\MultiApp')) {

            $appName = is_null($app) ? $this->app->http->getName() : $app;

            if (is_dir($this->app->getAppPath() . $view)) {
                $path = (is_null($app) ? $this->app->getAppPath() : $this->app->getBasePath() . $appName). $depr . $view . $depr;
            } else {
                $path = $this->app->getRootPath() . $view . $depr . $appName . $depr;
            }
        } else {
            // 单应用模式 
            $path = $this->app->getRootPath() . $view . $depr;
        }

        // 设置主题路径
        if (!empty($this->config['theme'])) {
            $path .= $this->config['theme'] . $depr;
        }

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

                    $template = str_replace('.', $depr, $controller) . $depr . $template;
                } elseif (false === strpos($template, $depr)) {
                    $template = str_replace('.', $depr, $controller) . $depr . $template;
                }
            }
        } else {
            $template = str_replace(['/', ':'], $depr, substr($template, 1));
        }

        $template = $path . ltrim($template, '/') . '.' . ltrim($this->config['view_suffix'], '.');

        if (is_file($template)) {
            return $template;
        }

        // 未设置主题, 尝试先去default查找
        if(empty($this->config['theme'])) {
            $default = str_replace(
                $depr .'view'. $depr,
                $depr .'view'. $depr .'default'. $depr,
                $template
            );

            if (is_file($default)) {
                return $default;
            }
        }

        // 默认主题不存在模版, 降级删除default主题继续查找
        if (strpos($template, $depr .'view'. $depr . 'default' . $depr) !== false) {
            $default = str_replace(
                $depr .'view'. $depr .'default'. $depr,
                $depr .'view'. $depr,
                $template
            );

            if (is_file($default)) {
                return $default;
            }
        }

        // 已设置主题, 但是找不到模版, 尝试降级为default主题
        if (strpos($template, $depr .'view'. $depr . $this->config['theme'] . $depr) !== false) {
            $default = str_replace(
                $depr . $this->config['theme'] . $depr,
                $depr .'default'. $depr,
                $template
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
    private function normalize($template = '', $isRaw = false)
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

    public function __debugInfo()
    {
        return [
            'config' => $this->config,
            'blade' => $this->blade
        ];
    }

    public function __call($method, $params)
    {
        return call_user_func_array([$this->blade, $method], $params);
    }
}
