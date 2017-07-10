<?php

use Tools\Renamer;

require_once __DIR__ . '/vendor/autoload.php';

$args = $argv;
$opts = getopt('dec:');

$keep = true;
$debug = \Monolog\Logger::ERROR;
$logfile = 'renamer';
$excludes_path = $size = $config = [];
if (!empty($opts['c'])) {
    $config = \Tools\ConfigFactory::create($opts['c']);
} elseif (!empty($args['1']) && !empty($args['2'])) {
    echo "usage : php postUploads.php SOURCE DEST\r\n";
    echo "-d : supprime les fichiers source après copy\r\n";
    echo "-e : exclus les repertoires séparé par des \":\"\r\n";
    echo "-c : utilise un fichier de configuration\r\n";
    die;
} else {
    $source = $args['1'];
    $destination = $args['2'];
    // on supprime les anciens fichiers
    if (!empty($opts['d'])) {
        $keep = false;
    }
    // on exclu les repertoires suivants
    if (!empty($opts['e'])) {
        $excludes_path = explode(':', $opts['e']);
    }
}

$logger = new \Monolog\Logger('renamer');

$logHandler = new \Monolog\Handler\StreamHandler('php://stdout', $debug);
$logHandler->setFormatter(new \Bramus\Monolog\Formatter\ColoredLineFormatter());
$logger->pushHandler($logHandler);

$logger->pushHandler(new \Monolog\Handler\StreamHandler($logfile . '-' . date('Y-m-d'), \Monolog\Logger::INFO));
$logHandler->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true, true));

$renamer = new Renamer($config);
// lance le renomage
$renamer->execute();
