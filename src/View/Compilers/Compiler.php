<?php
declare(strict_types=1);

namespace Illuminate\View\Compilers;

use Illuminate\View\ViewException;

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
     * @param  string  $cachePath
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($cachePath, $shouldCache = true, $compiledExtension = 'php')
    {
        $this->cachePath = $cachePath;
        $this->isCache = $shouldCache;
        $this->compiledExtension = $compiledExtension;
    }

    /**
     * Get the path to the compiled version of a view.
     *
     * @param  string  $path
     * @return string
     */
    public function getCompiledPath($path)
    {
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
        if (! file_exists($compiled)) {
            return true;
        }

        if (! $this->isCache) {
            return true;
        }

        return filemtime($path) >= filemtime($compiled);
    }

    /**
     * Create the compiled file directory if necessary.
     *
     * @param  string  $path
     * @return void
     */
    protected function ensureCompiledDirectoryExists($path)
    {
        if (! file_exists(dirname($path))) {
            @mkdir(dirname($path), 0777, true);
        }
    }

    /**
     * Write the contents of a file.
     *
     * @param  string  $path
     * @param  string  $contents
     * @param  bool  $lock
     * @return int|bool
     */
    public function putViewContent($path, $contents, $lock = false)
    {
        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    /**
     * Get the contents of a file.
     *
     * @param  string  $path
     * @return string
     */
    public function getViewContent($path, $lock = false)
    {
        if (is_file($path)) {
            return $lock ? $this->sharedGet($path) : file_get_contents($path);
        }

        throw new ViewException("View file does not exist at path {$path}");
    }

    /**
     * Get contents of a file with shared access.
     *
     * @param  string  $path
     * @return string
     */
    public function sharedGet($path)
    {
        $contents = '';

        $handle = fopen($path, 'rb');

        if ($handle) {
            try {
                if (flock($handle, LOCK_SH)) {
                    clearstatcache(true, $path);

                    $contents = fread($handle, $this->size($path) ?: 1);

                    flock($handle, LOCK_UN);
                }
            } finally {
                fclose($handle);
            }
        }

        return $contents;
    }
}
