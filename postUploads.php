<?php

use Tools\Renamer;

require_once __DIR__ . '/vendor/autoload.php';

$logger = new \Monolog\Logger('renamer');

$logHandler = new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::ERROR);
$logHandler->setFormatter(new \Bramus\Monolog\Formatter\ColoredLineFormatter());
$logger->pushHandler($logHandler);

$logger->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__ . DIRECTORY_SEPARATOR . 'logs/' . date('Y-m-d'),
    \Monolog\Logger::ERROR));
$logHandler->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true, true));

$args = $argv;
$opts = getopt('dec:');

$keep = true;
$excludes_path = $size = [];
if (!empty($opts['c'])) {
    $config = file_get_contents($opts['c']);
    $config = json_decode($config);
    $source = $config->source;
    $destination = $config->destination;
    $excludes_path = $config->excludes;
    $size = (array)$config->size;
    $keep = $config->keep;
    unset($config);
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
$renamer = (new Renamer($source, $destination))
    ->setLogger($logger)
    ->setExcludedPath($excludes_path)
    ->setKeepSource($keep)
    ->setSize($size);
// lance le renomage
$renamer->execute();
