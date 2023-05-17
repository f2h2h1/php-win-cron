<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Command\Command;
use Cron\CronExpression;
/*

php crond.php -- 
php crond.php --reboot --debug

*/

class CronTabItem
{
    public CronExpression $expression;
    public $command;

    public function getExpression()
    {
        return $this->expression;
    }

    public function getCommand()
    {
        
    }

    public static function parser(string $line): ?CronTabItem
    {
        $cronTabItem = new CronTabItem();

        return $cronTabItem;
    }

    public function execute($validateExpressions = false)
    {
        if ($validateExpressions) {
            if (!$this->getExpression()->isDue()) {
                return;
            }
        }

        $command = $this->getCommand();

    }
}

class CronTab extends \SplFixedArray
{
    /**
     * @return CronTabItem
     */
    public function current()
    {
        return parent::current();
    }
}

class Crond
{
    public $crontab;
    public $shell;
    public $reboot;
    public $daemon;
    public $debug;

    public function __construct(
        $crontab,
        $shell = '',
        $reboot = false,
        $daemon = false,
        $debug = false,
    ) {
        if (!$crontab) {
            throw new \Exception('crontab can not be empty');
        }
        if (is_file($crontab)) {
            throw new \Exception('crontab should be a file');
        }
        if (is_readable($crontab)) {
            throw new \Exception('crontab can not read');
        }

        $this->crontab = $crontab;
        $this->shell = $shell;
        $this->reboot = $reboot;
        $this->daemon = $daemon;
        $this->debug = $debug;
    }

    public function parser()
    {
        $cronTabArr = [];
        $cronTabArrCount = count($cronTabArr);
        $cronTab = new CronTab($cronTabArrCount);
        $a = CronTab::fromArray([]);

        return $cronTab;
    }

    public function run(CronTab $cronTab, $reboot = false)
    {
        foreach ($cronTab as $item) {
            $item->execute(true);
        }
    }

    public function runRebootTask(CronTab $cronTab)
    {
        foreach ($cronTab as $item) {
            $item->execute(true);
        }
    }
}

(new SingleCommandApplication())
    ->setName('My Super Command') // Optional
    ->setVersion('1.0.0') // Optional
    ->addOption('crontab', null, InputOption::VALUE_REQUIRED)
    ->addOption('shell', null, InputOption::VALUE_REQUIRED)
    ->addOption('reboot', null, InputOption::VALUE_NONE)
    ->addOption('daemon', 'd', InputOption::VALUE_NONE)
    ->addOption('debug', null, InputOption::VALUE_NONE)
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $options = $input->getOptions();
        $crontab = $options['crontab'] ?? '';
        $shell = $options['shell'] ?? '';
        $reboot = $options['reboot'] ?? false;
        $daemon = $options['daemon'] ?? false;
        $debug = $options['debug'] ?? false;

        if ($debug) {
            var_dump($input->getOptions());
        }

        $crond = new Crond(
            (string)$crontab,
            (string)$shell,
            (bool)$reboot,
            (bool)$daemon,
            (bool)$debug,
        );
        if ($daemon) {
            $reboot = true;
        }
        $cronTab = $crond->parser();
        do {
            $crond->run($cronTab, $reboot);
            $reboot = false;
        } while ($daemon);

        return Command::SUCCESS;
    })
    ->run();
