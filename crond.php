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

php crond.php --crontab crontab
php crond.php --crontab crontab --debug
php crond.php --crontab crontab --daemon --debug
php crond.php --crontab crontab2 --shell "C:\Users\81522963\.CoronaeBorealis\Git\cmd\bash.exe -l" --daemon --debug
echo $?

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
    public $reboot = false;
    public $daemon;
    public $debug;
    public $execList = [];

    public function __construct(
        $crontab,
        $shell = '',
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
        $this->daemon = $daemon;
        $this->debug = $debug;
    }

    public function parser()
    {
        $crontabStr = file_get_contents($this->crontab);
        if ($this->debug) {
            echo 'crontab content ' . PHP_EOL . $crontabStr . PHP_EOL;
        }
        $cronTab = [];
        $cronTabArr = explode("\n", str_replace("\r\n", "\n", trim($crontabStr)));
        foreach ($cronTabArr as $line) {
            $cronTabItem = CronTabItem::parser($line);
            if ($cronTabItem != null) {
                $cronTab[] = $cronTabItem;
            }
        }
        $cronTabCount = count($cronTab);
        if ($this->debug) {
            echo 'valid items total ' . $cronTabCount . PHP_EOL;
        }
        if ($cronTabCount > 0) {
            $cronTabSpl = new CronTab($cronTabCount);
            foreach ($cronTab as $i =>  $item) {
                $cronTabSpl[$i] = $item;
            }
            if ($this->debug) {
                echo 'valid items list' . PHP_EOL;
                foreach ($cronTab as $i =>  $item) {
                    echo $item->getExpression()->getExpression() . ' ' . $item->getCommand() . PHP_EOL;
                }
            }
            return $cronTabSpl;
        } else {
            return [];
        }
    }

    public function run(CronTab $cronTab)
    {
        foreach ($cronTab as $item) {
            if (!$item->getExpression()->isDue()) {
                continue;
            }
            if ($this->debug) {
                echo date('Y-m-d\TH:i:sP') . ' run command' . PHP_EOL;
                echo $item->getExpression()->getExpression() . ' ' . $item->getCommand() . PHP_EOL;
            }

            // $item->execute(true);
            $command = $item->getCommand();
            if ($this->shell === null) { // use default shell
                $sh = proc_open($command, [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w']
                ], $pipes);
            } else { // use customer shell
                $shellExec = $this->shell;
                // $sh = proc_open($shellExec, [
                //     0 => ['pipe', 'r'],
                //     1 => ['pipe', 'w']
                // ], $pipes);
                // fwrite($pipes[0], $command . PHP_EOL);
                $shellExec = $shellExec . ' -c "' . str_replace('"', '\"', trim($command)) . '"';
                // echo $shellExec; echo PHP_EOL;
                $sh = proc_open($shellExec, [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w']
                ], $pipes);
                // fwrite($pipes[0], $command . PHP_EOL);
            }
            $pipes = [];
            $this->execList[] = $sh;
        }
        // echo count($this->execList);echo PHP_EOL;
        for ($i = count($this->execList) - 1; $i >= 0; $i--) {
            $status = proc_get_status($this->execList[$i]);
            // echo $i; echo "\t"; echo $status['pid']; echo "\t"; echo $status['running']; echo PHP_EOL;
            if ($status['running'] == false) {
                proc_close($this->execList[$i]);
                array_splice($this->execList, $i, 1);
            }
        }
    }
}

(new SingleCommandApplication())
    ->setName('My Super Command') // Optional
    ->setVersion('1.0.0') // Optional
    ->addOption('crontab', null, InputOption::VALUE_REQUIRED)
    ->addOption('shell', null, InputOption::VALUE_REQUIRED)
    ->addOption('daemon', 'd', InputOption::VALUE_NONE)
    ->addOption('debug', null, InputOption::VALUE_NONE)
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $options = $input->getOptions();
        $crontab = $options['crontab'] ?? '';
        $shell = $options['shell'] ?? '';
        $daemon = $options['daemon'] ?? false;
        $debug = $options['debug'] ?? false;

        if ($debug) {
            foreach ($input->getOptions() as $k => $v) {
                echo $k . "\t";
                var_dump($v);
            }
        }
        try {
            $crond = new Crond(
                (string)$crontab,
                (string)$shell,
                (bool)$daemon,
                (bool)$debug,
            );
            $cronTab = $crond->parser();
            if (count($cronTab) == 0) {
                if ($debug) {
                    echo 'no items' . PHP_EOL;
                }
                return Command::SUCCESS;
            }
        } catch (\Throwable $e) {
            echo $e->getMessage();echo PHP_EOL;
            echo $e->getTraceAsString();echo PHP_EOL;
            return Command::INVALID;
        }

        do {
            try {
                $crond->run($cronTab);
            } catch (\Exception $e) {
                echo $e->getMessage();echo PHP_EOL;
                echo $e->getTraceAsString();echo PHP_EOL;
            } catch (\Throwable $e) {
                echo $e->getMessage();echo PHP_EOL;
                echo $e->getTraceAsString();echo PHP_EOL;
                return Command::FAILURE;
            }
        } while ($daemon && sleep(60) !== false);

        return Command::SUCCESS;
    })
    ->run();
