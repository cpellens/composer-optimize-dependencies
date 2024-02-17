<?php

namespace MadeByCharles\Optimizer\Console;

use ArrayAccess;
use ArrayObject;
use InvalidArgumentException;
use MadeByCharles\Optimizer\Filesystem\PathNotExistsException;
use MadeByCharles\Optimizer\Filesystem\Resolver;
use MadeByCharles\Optimizer\Traits\ConfigurationAwareTrait;
use Psr\Log\LoggerAwareTrait;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\VarExporter\LazyProxyTrait;
use Throwable;

class Autoloader implements CommandLoaderInterface
{
    use LoggerAwareTrait;
    use LazyProxyTrait;
    use ConfigurationAwareTrait;

    private readonly ArrayAccess $commands;
    private readonly Resolver    $resolver;

    /**
     * @throws ReflectionException
     * @throws PathNotExistsException
     */
    public function __construct(private readonly Application $application)
    {
        $directory = $this->getConfigurationOption('autoloader.paths.commands');

        $this->commands = new ArrayObject();
        $this->resolver = new Resolver($directory);

        $directory = $this->getResolver()->globRecursive('*.php');
        foreach ($directory as $file) {
            $className = $this->getClassFromFile($file);
            $command   = $this->createCommand($className);

            $this->application->add(
                new Command($command[0])
            );
        }
    }

    public function getResolver(): Resolver
    {
        return $this->resolver;
    }

    /**
     * @return class-string<Command>
     */
    public function getClassFromFile(string $commandFile): string
    {
        static $cache = new ArrayObject();

        if ($cache->offsetExists($commandFile)) {
            return $cache->offsetGet($commandFile);
        }

        // load the source of commandFile
        $fileContents   = file_get_contents($commandFile);
        $tokens         = token_get_all($fileContents, TOKEN_PARSE);
        $classToken     = null;
        $classNamespace = '';

        foreach ($tokens as $i => $token) {
            // if the token is a namespace token, then assign the namespace string
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $classNamespace .= $tokens[$i + 2][1] . '\\';
                continue;
            }

            if (is_array($token) && $token[0] === T_CLASS) {
                $classToken = $tokens[$i + 2];
            }
        }

        if ($classToken === null) {
            throw new InvalidArgumentException('No class found in file: ' . $commandFile);
        }

        $className = $classNamespace . $classToken[1];
        if (!class_exists($className)) {
            throw new InvalidArgumentException('No class found: ' . $className);
        }
        $cache->offsetSet($commandFile, $className);

        /** @var class-string<Command> $className */
        return $className;
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @throws ReflectionException
     * @psalm-return <string, T>
     */
    public function createCommand(string $className): array
    {
        static $memoization = new ArrayObject();

        if (!$memoization->offsetExists($className)) {
            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(AsCommand::class);

            if ($attributes === []) {
                throw new InvalidArgumentException(
                    sprintf('%s is not a command.', $className)
                );
            }

            $memoization->offsetSet($className, [$attributes[0]->getName(), $reflection]);
        }

        $def = $memoization->offsetGet($className);
        return [$def[0], $def[1]->newInstanceWithoutConstructor()];
    }

    public function has(string $name): bool
    {
        try {
            return $this->get($name) instanceof Command;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @throws ReflectionException
     */
    public function get(string $name): Command
    {
        if ($this->commands->offsetExists($name)) {
            return $this->commands->offsetGet($name);
        }

        // if the name doesn't have the .php extension on it, then add it
        $fileName = $name;
        if (!str_ends_with($fileName, '.php')) {
            $fileName .= '.php';
        }

        $commandFile = $this->resolver->resolve($fileName);

        // fetch the class defined in commandFile
        $commandClass = $this->getClassFromFile($commandFile);
        if (!is_subclass_of($commandClass, Command::class)) {
            throw new InvalidArgumentException(
                sprintf('Class "%s" does not extend "%s"', $commandClass, Command::class)
            );
        }

        $commandInstance = $this->createCommand($commandClass);
        $this->commands->offsetSet($name, $commandInstance);

        return $commandInstance;
    }

    /**
     * @return array<class-string<Command>>
     */
    public function getNames(): array
    {
        $this->initializeLazyObject();

        return array_keys($this->commands->getArrayCopy());
    }
}
