<?php

namespace Tools\System;

/**
 * Class Directory
 * @package Tools
 */
class Directory
{

    /**
     * Création d'un répertoire si il n'existe pas
     * @param $directory
     * @throws \Exception
     */
    public static function create($directory)
    {
        if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le repertoire !');
        }
    }

    /**
     * @param $base
     * @param $excluded
     * @return array
     */
    public static function parse($base, $excluded): array
    {
        return array_diff(scandir($base, SCANDIR_SORT_ASCENDING), array_merge($excluded, ['.', '..']));
    }

}