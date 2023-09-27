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
    public string $command;
    public ?string $shell;
    public bool $reboot = false;

    public function __construct($expression, $command, $shell = null)
    {
        $this->expression = $expression;
        $this->command = $command;
        $this->shell = $shell;
    }

    public function getExpression()
    {
        return $this->expression;
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function setReboot($reboot)
    {
        $this->reboot = $reboot;
        return $this;
    }

    public function isReboot()
    {
        return $this->reboot;
    }

    public static function parser(string $line): ?CronTabItem
    {
        // parser line
        $line = trim($line);
        if (substr($line, 0, 1) == '#') {
            return null;
        } else if (substr($line, 0, 1) == '@') {
            $itemNo = 2;
        } else {
            $itemNo = 6;
        }
        $itemList = array_map('trim', explode(' ', $line, $itemNo));
        if (!is_array($itemList) || count($itemList) < $itemNo) {
            return null;
        }

        if ($itemNo == 6) {
            $expression = $itemList[0] . ' ' . $itemList[1] . ' ' . $itemList[2] . ' ' . $itemList[3] . ' ' . $itemList[4];
        } else {
            $expression = $itemList[0];
        }

        $expression = new CronExpression($expression);
        $command = implode(' ', array_slice($itemList, $itemNo - 1));
        $cronTabItem = new CronTabItem($expression, $command);

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
        echo 'run ' . $this->getExpression()->getExpression() . ' ' . $command . PHP_EOL;
        if ($this->shell === null) { // use default shell

        } else { // use customer shell

        }
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
        if (!is_file($crontab)) {
            throw new \Exception('crontab should be a file');
        }
        if (!is_readable($crontab)) {
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
        $crontabStr = file_get_contents($this->crontab);
        var_dump($crontabStr);
        $cronTab = [];
        $cronTabArr = explode("\n", str_replace("\r\n", "\n", trim($crontabStr)));
        foreach ($cronTabArr as $line) {
            $cronTabItem = CronTabItem::parser($line);
            if ($cronTabItem != null) {
                $cronTab[] = $cronTabItem;
            }
        }
        $cronTabCount = count($cronTab);
        if ($cronTabCount > 0) {
            $cronTabSpl = new CronTab($cronTabCount);
            foreach ($cronTab as $i =>  $item) {
                $cronTabSpl[$i] = $item;
            }
            return $cronTabSpl;
        } else {
            return [];
        }
    }

    public function run(CronTab $cronTab, $reboot = false)
    {
        foreach ($cronTab as $item) {
            $item->execute(true);
        }
    }

    public function runRebootTask(CronTab $cronTab)
    {
        $this->run($cronTab, true);
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
        if (count($cronTab) == 0) {
            return Command::SUCCESS;
        }
        // $crond->run($cronTab, $reboot);
        do {
            $crond->run($cronTab, $reboot);
            $reboot = false;
            sleep(60);
        } while ($daemon);

        return Command::SUCCESS;
    })
    ->run();
