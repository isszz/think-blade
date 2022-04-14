<?php

namespace think\view\driver;

class ViewNotFoundException extends \RuntimeException
{
    protected $template;

    public function __construct(string $message, string $template = '', string $controller = '')
    {
        $this->message  = $message;
        $this->template = $template;
        $this->controller = $controller;
    }

    /**
     * 获取报错模板文件|非路劲
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * 获取报错模板所属控制器
     *
     * @return string
     */
    public function getController(): string
    {
        return $this->controller;
    }
}
