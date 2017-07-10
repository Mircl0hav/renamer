<?php


namespace Tools\System;

use Monolog\Logger;
use Tools\LoggerAwareTrait;

/**
 * Class File
 * @package Tools\System
 */
class File
{

    use LoggerAwareTrait;

    /** @var  string */
    public $src;

    /** @var  array */
    public $exif = [];

    /** @var  string */
    public $mime;

    /**
     * File constructor.
     * @param string $file
     */
    public function __construct(string $file)
    {
        $this->src = $file;
        $this->mime = mime_content_type($file);
    }

    /**
     * @return string
     */
    public function getMime(): string
    {
        return $this->mime;
    }

    /**
     * @param Logger $logger
     * @return $this
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Déplace ou copie un fichier et déduplique
     * @param $dest
     * @param bool $nokeep
     * @return bool
     */
    public function move($dest, $nokeep = true): bool
    {
        $this->logger->debug("src=$this->src:dest=$dest");

        // check si le fichier de destination existe et qu'il est identique
        if (!$this->checkIdentical($this->src, $dest)) {
            $this->logger->debug("$this->src different de $dest");
            // copy du fichier
            if (!copy($this->src, $dest)) {
                $this->logger->error('la copie à échoué');
                return false;
            }
        }

        if ($nokeep === true) {
            $this->logger->info('delete : ' . $this->src);
            @unlink($this->src);
        }
        return true;
    }

    /**
     * Contrôle si le fichier $fn1 est identique à $fn2 si il existe
     * @param $fn1
     * @param $fn2
     * @return bool
     */
    private function checkIdentical($fn1, $fn2): bool
    {
        // si le fichier $fn1 ou $fn2 n'existe pas, ils ne peuvent pas être identique
        if (!file_exists($fn1) || !file_exists($fn2)) {
            $this->logger->debug('no file_exists');
            return false;
        }

        // controle le sha1 des deux fichiers
        if (sha1_file($fn1) === sha1_file($fn2)) {
            $this->logger->debug('sha1_file');
            return true;
        }

        return false;
    }

    /**
     * @param $dest
     */
    public function stream_copy($dest)
    {
        $src = fopen($this->src, 'rb');
        $dest = fopen($dest, 'wb');
        stream_copy_to_stream($src, $dest);
        fclose($src);
        fclose($dest);
    }

}