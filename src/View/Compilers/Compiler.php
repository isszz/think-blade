<?php

namespace Illuminate\View\Compilers;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;

use function Illuminate\Support\hash_fit;

abstract class Compiler
{
    /**
     * The Filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Get the cache path for the compiled views.
     *
     * @var string
     */
    protected $cachePath;

    /**
     * bool The default Whether to enable cacheing.
     *
     * @var string
     */
    protected $isCache;

    /**
     * The compiled view file extension.
     *
     * @var string
     */
    protected $compiledExtension = 'php';
    
    /**
     * Create a new compiler instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  string  $cachePath
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(Filesystem $files/*, $cachePath, $isCache = true, $compiledExtension = 'php'*/)
    {
        $this->files = $files;

        $config = app('config')->get('view');

        /*
        if (! $cachePath) {
            throw new InvalidArgumentException('Please provide a valid cache path.');
        }*/

        if (empty($config['compiled'])) {
            $config['compiled'] = app()->getRuntimePath(); //  . 'view' . DS
        }

        $this->cachePath = $config['compiled'];
        $this->isCache = $config['cache'] ?? false;
        $this->compiledExtension = $config['compiled_extension'] ?? 'php';

        $theme = $config['theme'] ?? '';

        // 设置到view文件夹
        $this->cachePath = $this->cachePath .'view'. DS;

        // 如果有主题, 则增加主题文件夹
        if ($theme) {
            $this->cachePath = $this->cachePath . $theme . DS;
        }
    }

    /**
     * Get the path to the compiled version of a view.
     *
     * @param  string  $path
     * @return string
     */
    public function getCompiledPath($path)
    {
        // preg_match("/;app;([a-zA-Z]+);view;([a-zA-Z]+);/", str_replace('\\', ';', $path), $appendPaths);
        // return $this->cachePath . '/' . sha1($path) . '.' . basename($path) .'.'. $this->compiledExtension;
        return $this->cachePath .'/'. hash_fit('v2'. basename($path)) .'.'. $this->compiledExtension;
    }

    /**
     * Determine if the view at the given path is expired.
     *
     * @param  string  $path
     * @return bool
     */
    public function isExpired($path)
    {
        $compiled = $this->getCompiledPath($path);

        // If the compiled file doesn't exist we will indicate that the view is expired
        // so that it can be re-compiled. Else, we will verify the last modification
        // of the views is less than the modification times of the compiled views.
        if (! $this->files->exists($compiled)) {
            return true;
        }

        if (! $this->isCache) {
            return true;
        }

        return $this->files->lastModified($path) >=
               $this->files->lastModified($compiled);
    }

    /**
     * Create the compiled file directory if necessary.
     *
     * @param  string  $path
     * @return void
     */
    protected function ensureCompiledDirectoryExists($path)
    {
        if (! $this->files->exists(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        }
    }
}
