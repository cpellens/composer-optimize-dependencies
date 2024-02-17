<?php

namespace MadeByCharles\Optimizer\Filesystem;

use Directory;
use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileObject;

readonly class Resolver
{
    private string $path;
    private mixed  $handle;

    /**
     * @throws PathNotExistsException
     */
    public function __construct(string $path = '')
    {
        // determine the project root directory
        $rootDirectory = static::getRootDirectory();
        if ($rootDirectory === false) {
            throw new PathNotExistsException($path);
        }

        $this->path = realpath($rootDirectory . DIRECTORY_SEPARATOR . $path);
        $this->handle = stream_context_create();
    }

    private static function getRootDirectory(): string|false
    {
        return realpath(__DIR__ . '/../../');
    }

    /**
     * @return iterable<string>
     * @throws PathNotExistsException
     */
    public function globRecursive(string $pattern): iterable
    {
        $directory = $this->open();
        if (!$directory instanceof Directory) {
            throw new InvalidArgumentException('Invalid directory: ' . $directory->getPath());
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory->path,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileObject $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                yield $file->getRealPath();
            }
        }
    }

    /**
     * @throws PathNotExistsException
     */
    public function open(string $path = '', string $mode = 'r'): Directory|SplFileObject
    {
        // resolve the path first
        $resolvedPath = $this->resolve($path);
        if (!file_exists($resolvedPath)) {
            throw new PathNotExistsException($path, $this->getPath());
        }

        $isDirectory = is_dir($resolvedPath);

        // if it's a directory, then open the directory. otherwise, create a SplFileObject
        if ($isDirectory) {
            return dir($resolvedPath, $this->handle);
        } else {
            return new SplFileObject($resolvedPath, $mode, context: $this->handle);
        }
    }

    public function resolve(string ...$path): string
    {
        return realpath(
            implode(
                DIRECTORY_SEPARATOR,
                [
                    $this->getPath(),
                    ...$path
                ]
            )
        );
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
