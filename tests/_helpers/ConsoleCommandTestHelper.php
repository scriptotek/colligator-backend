<?php

namespace Tests\Helpers;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Source: <https://github.com/ngmy/webloyer/blob/22ccec32be2a0e7d86549a44818c7a2b3de5ffb1/tests/_helpers/ConsoleCommandTestHelper.php>
 * (MIT)
 * Class ConsoleCommandTestHelper
 * @package Tests\Helpers
 */
trait ConsoleCommandTestHelper
{
    protected function runConsoleCommand(Command $command, $arguments = [], $options = [])
    {
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $tester->execute($arguments, $options);
        return $tester;
    }
}
