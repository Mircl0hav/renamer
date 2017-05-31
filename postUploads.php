<?php

use Tools\Renamer;

require_once "./vendor/autoload.php";

$opts = getopt("s:d:");
if (!empty($opts['s'])) {
    $source = $opts['s'];
} else {
    throw new Exception("missing parameter -s [source_path]");
}
if (!empty($opts['d'])) {
    $destination = $opts['d'];
} else {
    throw new Exception("missing parameter -d [destination_path]");
}

$excludes_path = [];
if (!empty($opts['e'])) {
    $excludes_path = explode(':', $opts['e']);
}

$oRenamer = new Renamer($source, $destination);
$oRenamer->setExcludedPath($excludes_path);
$oRenamer->execute($source);
