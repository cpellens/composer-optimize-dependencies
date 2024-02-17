<?php

namespace MadeByCharles\Optimizer\Traits;

use ArrayAccess;
use ArrayObject;
use MadeByCharles\Optimizer\Filesystem\PathNotExistsException;
use MadeByCharles\Optimizer\Filesystem\Resolver;
use SplFileObject;
use Symfony\Component\Yaml\Yaml;

/**
 * A trait that provides configuration loading functionality.
 *
 * PHP version 7.0 or higher
 *
 * @package YourPackage
 */
trait ConfigurationAwareTrait
{
    private static ?Resolver $configResolver;

    /**
     * @var string reflects the name of the yaml file that gets loaded
     */
    private string $configNamespace = 'global';

    private ArrayAccess $config;

    /**
     * @throws PathNotExistsException
     */
    protected function getConfigurationOption(string $name, mixed $default = null): mixed
    {
        $this->loadConfiguration();

        $path        = explode('.', $name);
        $currentNode = clone $this->config;
        foreach ($path as $segment) {
            if (!isset($currentNode[$segment])) {
                return $default;
            }
            $currentNode = $currentNode[$segment];
        }

        return $currentNode;
    }

    /**
     * @throws PathNotExistsException
     */
    private function loadConfiguration(): void
    {
        static $cache = new ArrayObject();

        // already loaded config - nothing to do
        if (isset($this->config)) {
            return;
        }

        // don't repeatedly parse the yaml file
        if ($cache->offsetExists($this->configNamespace)) {
            $this->config = $cache->offsetGet($this->configNamespace);
            return;
        }

        // if the fileName doesn't have the .yaml extension, add it
        $fileName = $this->configNamespace;
        if (!pathinfo($fileName, PATHINFO_EXTENSION)) {
            $fileName .= '.yaml';
        }

        // if the resolved path does not exist, or if it's a directory, throw error
        $resolver = static::$configResolver ??= new Resolver('config');
        $yaml     = $resolver->open($fileName);
        if (!$yaml instanceof SplFileObject || !$yaml->isReadable()) {
            throw new PathNotExistsException($this->configNamespace, $this->getResolver()->getPath());
        }

        // read the contents of the yaml file into memory
        $this->config = new ArrayObject(Yaml::parseFile($yaml->getRealPath()));
        $cache->offsetSet($this->configNamespace, $this->config);
    }
}
