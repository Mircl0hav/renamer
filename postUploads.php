<?php

use Tools\Renamer;

require_once "./vendor/autoload.php";

$opts = getopt("s:d:");
$directory_source = __DIR__ . DIRECTORY_SEPARATOR . "upload";
if (!empty($opts['s'])) {
    $directory_source = $opts['s'];
} else {
    throw new Exception("missing parameter -s [source_path]");
}
if (!empty($opts['d'])) {
    $destination_path = $opts['d'];
} else {
    throw new Exception("missing parameter -d [destination_path]");
}


$log = new \Monolog\Logger("renamer");
$log->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__ . DIRECTORY_SEPARATOR . "logs/renamer.log",
    \Monolog\Logger::DEBUG));

define('IMAGES_PATH', $destination_path . DIRECTORY_SEPARATOR . 'Images' . DIRECTORY_SEPARATOR);
define('GIFS_PATH', IMAGES_PATH . 'Gifs' . DIRECTORY_SEPARATOR);
define('VIDEOS_PATH', $destination_path . DIRECTORY_SEPARATOR . 'Videos' . DIRECTORY_SEPARATOR);

$oRenamer = new Renamer($log);

// create directories
$oRenamer->create_directory(IMAGES_PATH);
$oRenamer->create_directory(GIFS_PATH);
$oRenamer->create_directory(VIDEOS_PATH);

if (!is_dir($directory_source)) {
    throw new Exception("folder $directory_source doesn't exist !");
}
$oRenamer->parse_directory($directory_source);

$oRenamer->create_directory('logs');
