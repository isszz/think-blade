<?php

namespace Illuminate\View;

class ViewName
{
    /**
     * Normalize the given view name.
     *
     * @param  string  $name
     * @return string
     */
    public static function normalize($name, $raw = false)
    {
        $delimiter = ViewFinderInterface::HINT_PATH_DELIMITER;

        if (! str_contains($name, $delimiter)) {
            return str_replace('/', '.', $name);
        }

        [$namespace, $name] = explode($delimiter, $name);

        return $namespace.$delimiter.str_replace('/', '.', $name);
    }

    /**
     * Normalize the given template.
     *
     * @param  string  $name
     * @return string
     */
    public static function normalize2($template = '', $isRaw = false)
    {
        if($isRaw && strpos($template, '/')) {
            return str_replace('/', '.', $template);
        }

        if (strpos($template, '.')) {
            $template = str_replace('.', '/', $template);
        }

        return $template;
    }
}
