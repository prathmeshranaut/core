<?php
/*
 * This file is part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flarum\Asset;

use Flarum\Asset\CompilerInterface;
use Illuminate\Support\Str;

class RevisionCompiler implements CompilerInterface
{
    /**
     * @var string[]
     */
    protected $files = [];

    /**
     * @var callable[]
     */
    protected $strings = [];

    /**
     * @param string $path
     * @param string $filename
     */
    public function __construct($path, $filename)
    {
        $this->path = $path;
        $this->filename = $filename;
    }

    /**
     * {@inheritdoc}
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
    }

    /**
     * {@inheritdoc}
     */
    public function addFile($file)
    {
        $this->files[] = $file;
    }

    /**
     * {@inheritdoc}
     */
    public function addString(callable $callback)
    {
        $this->strings[] = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function getFile()
    {
        $revision = $this->getRevision();

        $lastModTime = 0;
        foreach ($this->files as $file) {
            $lastModTime = max($lastModTime, filemtime($file));
        }

        $ext = pathinfo($this->filename, PATHINFO_EXTENSION);
        $file = $this->path.'/'.substr_replace($this->filename, '-'.$revision, -strlen($ext) - 1, 0);

        if (! ($exists = file_exists($file)) || filemtime($file) < $lastModTime) {
            if ($exists) {
                unlink($file);
            }

            $revision = Str::quickRandom();
            $this->putRevision($revision);
            $file = $this->path.'/'.substr_replace($this->filename, '-'.$revision, -strlen($ext) - 1, 0);
            file_put_contents($file, $this->compile());
        }

        return $file;
    }

    /**
     * @param string $string
     * @return string
     */
    protected function format($string)
    {
        return $string;
    }

    /**
     * {@inheritdoc}
     */
    public function compile()
    {
        $output = '';

        foreach ($this->files as $file) {
            $output .= $this->format(file_get_contents($file));
        }

        foreach ($this->strings as $callback) {
            $output .= $this->format($callback());
        }

        return $output;
    }

    /**
     * @return string
     */
    protected function getRevisionFile()
    {
        return $this->path.'/rev-manifest.json';
    }

    /**
     * @return string|null
     */
    protected function getRevision()
    {
        if (file_exists($file = $this->getRevisionFile())) {
            $manifest = json_decode(file_get_contents($file), true);

            return array_get($manifest, $this->filename);
        }
    }

    /**
     * @param string $revision
     * @return int
     */
    protected function putRevision($revision)
    {
        if (file_exists($file = $this->getRevisionFile())) {
            $manifest = json_decode(file_get_contents($file), true);
        } else {
            $manifest = [];
        }

        $manifest[$this->filename] = $revision;

        return file_put_contents($this->getRevisionFile(), json_encode($manifest));
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $revision = $this->getRevision();

        $ext = pathinfo($this->filename, PATHINFO_EXTENSION);

        $file = $this->path . '/' . substr_replace($this->filename, '-' . $revision, -strlen($ext) - 1, 0);

        if (file_exists($file)) {
            unlink($file);
        }
    }
}