<?php
declare(strict_types=1);

namespace Illuminate\View;

class ViewName
{
    /**
     * Hint path delimiter value.
     *
     * @var string
     */
    const HINT_PATH_DELIMITER = '@';

    /**
     * Normalize the given view name.
     *
     * @param  string  $name
     * @return string
     */
    public static function normalize($name, $raw = false)
    {
        $delimiter = self::HINT_PATH_DELIMITER;

        if (! str_contains($name, $delimiter)) {
            return str_replace('/', '.', $name);
        }

        [$namespace, $name] = explode($delimiter, $name);

        // $app = app('http')->getName();
        // if ($namespace != $app) {}

        return $namespace . $delimiter . str_replace('/', '.', $name);
    }

    /**
     * Normalize the given template.
     *
     * @param  string  $name
     * @param  bool  $raw
     * @return string
     */
    public static function normalize2tp($template = '', $raw = false)
    {
        if($raw && strpos($template, '/')) {
            return str_replace('/', '.', $template);
        }

        if (strpos($template, '.')) {
            $template = str_replace('.', '/', $template);
        }

        return $template;
    }
}
