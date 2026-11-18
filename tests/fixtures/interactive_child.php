<?php

/**
 * A stand-in for an interactive artisan command, run as a real child process by
 * ProcessBrokerInteractiveTest. It asks through RemotisanOutputStyle exactly like a command would.
 */

require __DIR__ . "/../../vendor/autoload.php";

use PayMe\Remotisan\Interactive\RemotisanOutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

$io = new RemotisanOutputStyle(new ArrayInput([]), new StreamOutput(STDOUT));

$io->writeln("starting import");
$name    = $io->ask("Your name", "nobody");
$secret  = $io->askHidden("Transfer key");
$proceed = $io->confirm("Proceed?", false);

$io->writeln("name={$name}");
$io->writeln("secret-length=" . strlen((string)$secret));
$io->writeln("proceed=" . ($proceed ? "yes" : "no"));
