<?php

namespace Tools;

use Monolog\Logger;

/**
 * Class Renamer
 */
class Renamer
{

    /** @var Logger */
    protected $logger;

    /** @var  boolean */
    protected $keep_source;

    /** @var string */
    protected $source;

    /** @var string */
    protected $destination;

    /** @var array */
    protected $excludes_path = ['.', '..'];

    /** @var array */
    protected $size = [];

    /**
     * Renamer constructor.
     * @param $source
     * @param $destination
     * @throws \Exception
     */
    public function __construct($source = null, $destination = null)
    {

        $this->keep_source = true;

        $this->source = realpath($source) . '/';
        $this->destination = realpath($destination) . '/';

        // création des répertoire de base
        $this->create_directory('images');
        $this->create_directory('gifs');
        $this->create_directory('videos');
        $this->create_directory('trash');
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
     * @param bool $keep_source
     * @return $this
     */
    public function setKeepSource(bool $keep_source)
    {
        $this->keep_source = $keep_source;
        return $this;
    }

    /**
     * @param array $excludes_path
     * @return $this
     */
    public function setExcludedPath(array $excludes_path)
    {
        $this->excludes_path = array_merge($this->excludes_path, $excludes_path);
        return $this;
    }

    /**
     * @return array
     */
    public function getExcludedPath(): array
    {
        return $this->excludes_path;
    }

    /**
     * @return string
     */
    public function getDestination(): string
    {
        return $this->destination;
    }

    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @param array $size
     * @return $this
     */
    public function setSize(array $size)
    {
        $this->size = $size;
        return $this;
    }

    /**
     * @return array
     */
    public function getSize(): array
    {
        return $this->size;
    }

    /**
     * Création d'un répertoire si il n'existe pas
     * @param $directory
     * @throws \Exception
     */
    public function create_directory($directory)
    {
        $directory = $this->destination . DIRECTORY_SEPARATOR . $directory;
        if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le repertoire !');
        }
    }

    /**
     * Classe les fichiers par année et par mois
     * @param $fileDateTime
     * @param $directory_root
     * @param string $extension
     * @return string
     * @throws \Exception
     */
    public function classify($fileDateTime, $directory_root, $extension = '.jpg'): string
    {
        $year = date('Y', $fileDateTime);
        $month = date('m', $fileDateTime);

        // création des repertoires de classification BASE/YYYY/mm/
        $this->create_directory($directory_root . $year);
        $this->create_directory($directory_root . $year . DIRECTORY_SEPARATOR . $month);

        // génère un nouveau nom pour le fichier
        $newName = date('d-H-i-s', $fileDateTime) . $extension;
        return $directory_root . $year . DIRECTORY_SEPARATOR . $month . DIRECTORY_SEPARATOR . $newName;
    }

    /**
     * Déplace ou copie un fichier et déduplique
     * @param $src
     * @param $dest
     * @return bool
     */
    public function move_files($src, $dest): bool
    {
        $dest = realpath($this->destination) . $dest;

        $this->logger->debug("src=$src:dest=$dest");

        // check si le fichier de destination existe et qu'il est identique
        if ($this->checkIdentical($src, $dest)) {
            $this->logger->debug("identical : $src : $dest");
            return false;
        }

        // selon le mode on copie ou on déplace le fichier (attention aux permissions)
        copy($src, $dest);
        if ($this->keep_source === false) {
            unlink($src);
            $this->logger->debug('delete : ' . $src);
        }
        return true;
    }

    /**
     * @param $src
     * @param $dest
     */
    protected function stream_copy($src, $dest)
    {
        $src = fopen($src, 'rb');
        $dest = fopen($dest, 'wb');
        stream_copy_to_stream($src, $dest);
        fclose($src);
        fclose($dest);
    }

    /**
     * Renomage de masse des fichiers depuis le dossier source
     * @param $base
     * @throws \Exception
     */
    public function execute($base = null)
    {
        if (null === $base) {
            $base = $this->source;
        }
        $this->logger->info('parse directory ' . $base);
        $scanned_directory = array_diff(scandir($base, SCANDIR_SORT_NONE), $this->excludes_path);

        // parcours le repertoire
        foreach ($scanned_directory as $entry) {
            $currentEntry = $base . $entry;

            // si l'entrée est un repertoire on rentre dans l'arborescence
            if (is_dir(realpath($currentEntry))) {
                $this->execute($currentEntry . DIRECTORY_SEPARATOR);
            } else {
                // on récupère les infos du fichier (EXIF si une image)
                $dataEntry = $this->getEntryInfo($currentEntry);

                $mime_content_type = $dataEntry['mime'];

                // selon le type de fichier
                switch ($mime_content_type) {
                    case 'image/jpeg':
                        $images_path = $this->isGoodSize($dataEntry['size']) ? '/images/' : '/trash/';
                        $exif_data = $dataEntry['exif'];
                        $dateEntry = isset($exif_data['DateTimeOriginal']) ? strtotime($exif_data['DateTimeOriginal']) : $exif_data['FileDateTime'];
                        $new_file = $this->classify($dateEntry, $images_path);
                        $this->move_files($currentEntry, $new_file);

                        break;
                    case 'image/gif':
                        $this->move_files($currentEntry,
                            '/gifs/' . $entry);
                        break;
                    case 'video/mp4':
                    case 'application/octet-stream':
                        $dest = realpath($this->destination) . '/videos/' . $entry;
                        $this->stream_copy($currentEntry, $dest);
                        break;
                    case 'application/vnd.oasis.opendocument.text':
                    case 'image/png':
                    case 'text/plain':
                    case 'application/xml':
                    case 'inode/x-empty':
                    case 'application/CDFV2-unknown':
                        $this->move_files($currentEntry,
                            '/trash/' . $entry);
                        break;
                    case 'text/x-shellscript':
                    case 'text/x-php':
                    case 'application/CDFV2-corrupt':
                        break;
                    default:
                        $this->logger->info($currentEntry . ': ' . $mime_content_type);
                        die;
                        break;
                }
            }
        }
    }

    /**
     * Récupère les infos d'un fichier (mime et EXIF)
     * @param $entry
     * @return array
     */
    private function getEntryInfo($entry): array
    {
        $dataSet['exif'] = [];
        $dataSet['mime'] = mime_content_type($entry);
        if ($dataSet['mime'] === 'image/jpeg') {
            try {
                $dataSet['exif'] = exif_read_data($entry);
            } catch (\Exception $e) {
                $this->logger->debug($e->getMessage());
            }
            list($width, $height) = getimagesize($entry);
            $dataSet['size']['width'] = $width;
            $dataSet['size']['height'] = $height;
        }
        return $dataSet;
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
     * @param $size
     * @return bool
     */
    private function isGoodSize($size): bool
    {
        if (!empty($this->size)) {
            if ($size['width'] < $this->size['width'] || $size['height'] < $this->size['height']) {
                return false;
            }
        }
        return true;

    }

}