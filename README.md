
# blade
thinkphp8 blade view engine

<p>
    <a href="https://packagist.org/packages/isszz/think-blade"><img src="https://img.shields.io/badge/php->=8.0-8892BF.svg" alt="Minimum PHP Version"></a>
    <a href="https://packagist.org/packages/isszz/think-blade"><img src="https://img.shields.io/badge/thinkphp->=8.0-8892BF.svg" alt="Minimum Thinkphp Version"></a>
    <a href="https://packagist.org/packages/isszz/think-blade"><img src="https://poser.pugx.org/isszz/think-blade/v/stable" alt="Stable Version"></a>
    <a href="https://packagist.org/packages/isszz/think-blade"><img src="https://poser.pugx.org/isszz/think-blade/downloads" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/isszz/think-blade"><img src="https://poser.pugx.org/isszz/think-blade/license" alt="License"></a>
</p>

## 安装

```shell
composer require isszz/think-blade
```

## 配置

```php
<?php

// view.php 模板配置, 多应用时, 每个应用的配置可以不同

return [
    // 视图目录名
    'dir_name' => 'view',
    // 模版主题
    'theme' => '',
    // 模板起始路径
    'base_path' => '',
    // 模板文件后缀
    'suffix' => 'blade.php',
    // 模板文件名分隔符
    'depr' => DIRECTORY_SEPARATOR,
    // 缓存路径
    'compiled' => '', // 默认留空使用runtime目录
    // 是否开启模板编译缓存, 设为false则每次都会重新编译
    'cache' => true,
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
use think\blade\facade\Blade;

Blade::directive('time2str', function($expression) {
  return "<?php echo \Helper::time2str($expression); ?>";
});
```

用法, 当然你也可以传递参数

```php
@time2str(time(), 'Y-m-d H:i')
```

## 自定义 If 语句

在定义简单的、自定义条件语句时，编写自定义指令比必须的步骤复杂。在这种情况下，think Blade 提供了 View::if 方法，它允许你使用闭包快速度定义条件指令。例如，定义一个校验当前应用的自定义指令

```php
use think\blade\facade\Blade;

Blade::if('app', function (...$apps) {
    $appName = app('http')->getName();

    if (count($apps) > 0) {
        $patterns = is_array($apps[0]) ? $apps[0] : (array) $apps;
        return in_array($appName, $patterns);
    }

    return $appName;
});
```

一旦定义了自定义条件指令，就可以在模板中轻松的使用：

```php
@app('admin')
    // 后台应用
@elseapp('api')
    // api应用
@elseapp(['index', 'common'])
    // index和common应用
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

###  中间件 挂载 auth 到 app 案例

```php
use think\blade\facade\View;

/**
 * 认证
 */
class Auth
{
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
        $this->app->bind('auth', $auth);
        
        // 在线用户信息, 未登录返回guest用户
        $user = $auth->user();
        
        // 模版变量注入
        View::share([
            'auth' => $auth,
            'user' => $user,
        ]);
        
        return $next($request);
    }
}
```

###  有条件地编译 class 样式

该`@class`指令有条件地编译 CSS class 样式。该指令接收一个数组，其中数组的键包含你希望添加的一个或多个样式的类名，而值是一个布尔表达式。如果数组元素有一个数值的键，它将始终包含在呈现的 class 列表中：
```
// 多行php代码
@php
    $isActive = false;
    $hasError = true;
@endphp

<span @class([
    'p-4',
    'font-bold' => $isActive,
    'text-gray-500' => !$isActive,
    'bg-red' => $hasError,
])></span>

// 结果:
<span class="p-4 text-gray-500 bg-red"></span>
```

###  同样，@style 指令可用于有条件地将内联 CSS 样式添加到一个 HTML 元素中。
```
// 单行php代码可以简写如下
@php($isActive = true)

<span @style([
    'background-color: red',
    'font-weight: bold' => $isActive,
])></span>

// 结果:
<span style="background-color: red; font-weight: bold;"></span>
```
### 附加属性

为方便起见，你可以使用该`@checked`指令轻松判断给定的 HTML 复选框输入是否被「选中（checked）」。如果提供的条件判断为`true`，则此指令将回显`checked`：
```html
<input type="checkbox" name="active" value="active" @checked(true) />
```

### `@selected`指令可用于判断给定的选项是否被「选中（selected）」：
```html
@php
$versions = [
    '1.0',
    '1.2',
    '1.3',
    '1.4',
    '1.5',
];
@endphp
<select name="version">
    @foreach ($versions as $version)
        <option value="{{ $version }}" @selected('1.2' == $version)>
            {{ $version }}
        </option>
    @endforeach
</select>
```
### `@disabled`指令可用于判断给定元素是否为「禁用（disabled）」:
```html
<button type="submit" @disabled($errors->isNotEmpty())>Submit</button>
```

### `@readonly`指令可以用来指示某个元素是否应该是「只读 （readonly）」的。
```html
<input type="email" name="email" value="email@laravel.com"  @readonly(1) />
```

### `@required`指令可以用来指示一个给定的元素是否应该是「必需的（required）」。
```html
<input type="text" name="title" value="title" @required(1) />
```
### 表单csrf token
```html
@csrf
// 也支持参数, 同thinkphp的Request::buildToken()参数相同
@csrf($name, $type)
// 结果:
<input type="hidden" name="__token__" value="a47f4452e760ae12af62c11fbea5c65e">
```
### Method 字段
由于 HTML 表单不能发出 `PUT`、`PATCH` 或 `DELETE` 请求，因此需要添加一个隐藏的 `__method__` 字段来欺骗这些 HTTP 动词。 `@method` Blade 指令可以为你创建此字段：
```html
<form action="/foo/bar" method="POST">
    @method('PUT', '___method')
    ...
</form>
```
### 自定义button组件
```html
<!-- /app/index/view/default/components/button.html.php -->
<button type="{{ $type }}" {{ $attributes->whereDoesntStartWith('name') }}>{{ $name }}</button>

// 引用
<x-button type="submit" name="提交" class="btn btn-submit" />
```

### 自定义button组件
```html
<!-- /app/index/view/default/components/alert.html.php -->
<div class="alert">
    <h3 {{ $title->attributes }}>{{ $title ?? '' }}</h3>
    {{ $slot }}
</div>

// 引用
<x-alert type="info" class="mb-4">
    <x-slot:title class="color-red">
        Server Error
    </x-slot>
    <strong>Whoops!</strong> Something went wrong!
</x-alert>
```
## 更多用法参考 laravel blade 手册

https://learnku.com/docs/laravel/10.x/blade/14852
