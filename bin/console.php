<?php

use MadeByCharles\Optimizer\Console\Autoloader;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerAwareTrait;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

require_once 'vendor/autoload.php';

class Application extends \Symfony\Component\Console\Application
{
    use LoggerAwareTrait;

    private readonly Autoloader    $autoloader;
    private readonly LoopInterface $eventLoop;

    public function __construct()
    {
        parent::__construct('Dependency Optimizer', '0.0.1');

        $this->logger = new Logger($this->getName());
        $this->logger->pushHandler(new ErrorLogHandler());
        $this->logger->pushHandler(new StreamHandler(fopen('php://output', 'w')));

        $this->eventLoop  = Loop::get();
        $this->autoloader = Autoloader::createLazyProxy(initializer: function (): Autoloader {
            return new Autoloader($this);
        });

        $this->setCatchExceptions(true);
        $this->setAutoExit(true);
    }

    public function run(
        ?InputInterface $input = null,
        ?OutputInterface $output = null
    ): int {
        $this->eventLoop->run();
        return parent::run($input, $output);
    }


}

return (new Application())->run(
    new Symfony\Component\Console\Input\ArgvInput(),
    new Symfony\Component\Console\Output\ConsoleOutput()
);
