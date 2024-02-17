<?php

namespace MadeByCharles\Optimizer\Filesystem;

use Exception;
use Throwable;

class PathNotExistsException extends Exception implements Throwable
{
    public function __construct(
        private readonly string $path,
        private readonly ?string $rootDirectory = null
    ) {
        parent::__construct(
            $this->rootDirectory
                ? sprintf(
                'Path non existent "%s" from "%s"',
                $this->getPath(),
                $this->getRoot()
            )
                : sprintf(
                'Path non existent "%s"',
                $this->getPath()
            ),
            404
        );
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getRoot(): string
    {
        return $this->rootDirectory;
    }
}
