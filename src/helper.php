<?php
use think\facade\Request;
use Illuminate\Contracts\View\Factory as ViewFactory;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}


if (! function_exists('v')) {
    /**
     * Get the evaluated view contents for the given view.
     *
     * @param  string|null  $view
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $data
     * @param  array  $mergeData
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    function v($view = null, $data = [], $mergeData = [])
    {
        $factory = app('blade.view');

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($view, $data, $mergeData);
    }
}

if (! function_exists('blade')) {
    /**
     * Get the evaluated view contents for the given view.
     *
     * @param  string|null  $view
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $data
     * @param  array  $mergeData
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    function blade($view = null, $data = [], $mergeData = [])
    {
        $factory = app('blade.view');

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($view, $data, $mergeData);
    }
}

if (! function_exists('csrf_field')) {
    /**
     * Generate a CSRF token form field.
     *
     * @return html
     */
    function csrf_field(string $name = '__token__', string $type = 'md5'): string
    {
        return '<input type="hidden" name="' . $name . '" value="'. Request::buildToken($name, $type) .'">';
    }
}

if (! function_exists('method_field')) {
    /**
     * Generate a form field to spoof the HTTP verb used by forms.
     *
     * @param  string  $method
     * @return html
     */
    function method_field($method, string $name = '__method__', )
    {
        return '<input type="hidden" name="'. $name .'" value="'. $method .'">';
    }
}
