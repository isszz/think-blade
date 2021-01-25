
# blade
thinkphp6 blade view engine

## 安装

```shell
composer require isszz/think-blade -vvv
```

## 配置

```php
<?php

// 模板设置

return [
    // 这里切换为blade引擎
    'type'          => 'Blade',
    // 模版主题, blade新增
    'view_theme' => '', // 留空为不启用, 设置后, 还可添加一套default主题作为备选
    // 更多配置和Think相同, 部分blade无用
];
```

## 容器注入

容器中的类解析调用，对于已经绑定的类标识，会自动快速实例化

```html
@inject('test', 'app\service\Test')

<div>{{ $test->info() }}</div>
```

## 扩展 Blade

Blade 允许你使用 directive 方法自定义指令。当 Blade 编译器遇到自定义指令时，这会调用该指令包含的表达式提供的回调。

```php
View::directive('time2str', function($expression) {
  return "<?php echo think\Helper::time2str($expression); ?>";
});
```

用法, 当然你也可以传递参数

```php
@time2str(time(), 'Y-m-d H:i')
```

## 自定义 If 语句

在定义简单的、自定义条件语句时，编写自定义指令比必须的步骤复杂。在这种情况下，think Blade 提供了 View::if 方法，它允许你使用闭包快速度定义条件指令。例如，定义一个校验当前应用的自定义指令

```php
View::if('app', function (...$apps) {

    if (count($apps) > 0) {
        $patterns = is_array($apps[0]) ? $apps[0] : $apps;
        return \Illuminate\Support\Str::is($patterns, app('http')->getName());
    }

    return app('http')->getName();
});
```

一旦定义了自定义条件指令，就可以在模板中轻松的使用：

```php
@app('admin')
    // 后台应用
@elseapp('api')
    // api应用
@else
    // 其他应用
@endapp
```

### 需要使用到 auth 和 权限验证时, 需要自行实现一个 auth 挂载进 app 且实现下列方法

```
 * @method auth->check 判断当前用户是否登录
 * @method auth->guest 判断当前用户是否为游客

 * @method auth->can 用户是否有权限
 * @method auth->cannot 用户不能执行这个权限
 * @method auth->any 用户是否具有来自给定能力列表的任何授权能力
```

###  试用中间件 挂载 auth 到 app

```php
/**
 * 认证
 */
class Auth
{
    use Showmsg;
    
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $this->app->request;
    }

    /**
     * 初始化
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle($request, Closure $next)
    {
        // 这个类自己实现, 需要用到的方法, 参见上面
        $auth = new \app\admin\service\Auth($this->app);

        // 容器注入
        Container::getInstance()->bind('auth', $auth);
        
        // 在线用户信息, 未登录返回guest用户
        $user = $auth->user();
        
        // 模版变量注入
        View::assign([
            'auth' => $auth,
            'user' => $user,
        ]);
        
        return $next($request);
    }
}
```

## 更多用法参考 laravel blade 手册

https://learnku.com/docs/laravel/6.x/blade/5147
