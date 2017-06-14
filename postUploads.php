<?php

use Tools\Renamer;

require_once __DIR__ . './vendor/autoload.php';

$opts = getopt('s:d:e:');
if (!empty($opts['s'])) {
    $source = $opts['s'];
} else {
    throw new RuntimeException('missing parameter -s [source_path]');
}
if (!empty($opts['d'])) {
    $destination = $opts['d'];
} else {
    throw new RuntimeException('missing parameter -d [destination_path]');
}

$excludes_path = [];
if (!empty($opts['e'])) {
    $excludes_path = explode(':', $opts['e']);
}

$logger = new \Monolog\Logger('renamer');

$logHandler = new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::DEBUG);
$logHandler->setFormatter(new \Bramus\Monolog\Formatter\ColoredLineFormatter());
$logger->pushHandler($logHandler);

$logger->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__ . DIRECTORY_SEPARATOR . 'logs/renamer.log',
    \Monolog\Logger::DEBUG));
$logHandler->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true, true));

$renamer = new Renamer($source, $destination);
$renamer->setLogger($logger);
$renamer->setExcludedPath($excludes_path);
$renamer->execute($source);
