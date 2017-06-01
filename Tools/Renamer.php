<?php

namespace Tools;

use Monolog\Logger;

/**
 * Class Renamer
 */
class Renamer
{

    protected static $READ_LEN = 4096;

    /** @var Logger */
    protected $logger;

    /** @var string */
    protected $source = '';

    /** @var string */
    protected $destination = '';

    /** @var array */
    protected $excludes_path = ['.', '..'];

    /**
     * Renamer constructor.
     * @param $source
     * @param $destination
     */
    public function __construct($source, $destination)
    {
        $this->source = realpath($source);
        $this->destination = realpath($destination);

        $this->create_directory('images');
        $this->create_directory('gifs');
        $this->create_directory('videos');
        $this->create_directory('trash');
    }

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param array $excludes_path
     */
    public function setExcludedPath(array $excludes_path)
    {
        $this->excludes_path = array_merge($this->excludes_path, $excludes_path);
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
     * @param $directory
     */
    public function create_directory($directory)
    {
        $directory = $this->destination . DIRECTORY_SEPARATOR . $directory;
        if (!is_dir($directory)) {
            try {
                mkdir($directory, 0755, true);
            } catch (\Exception $e) {
                echo $e->getMessage();
            }
        }
    }

    /**
     * @param $fileDateTime
     * @param $directory_root
     * @param string $extension
     * @return string
     */
    public function classify($fileDateTime, $directory_root, $extension = '.jpg')
    {
        $year = date('Y', $fileDateTime);
        $month = date('m', $fileDateTime);
        $this->create_directory($directory_root . $year);
        $this->create_directory($directory_root . $year . DIRECTORY_SEPARATOR . $month);
        $newName = date('d-H-i-s', $fileDateTime) . $extension;
        return $directory_root . $year . DIRECTORY_SEPARATOR . $month . DIRECTORY_SEPARATOR . $newName;
    }

    /**
     * @param $src
     * @param $dest
     * @return bool
     */
    public function move_files($src, $dest): bool
    {
        $dest = realpath($this->destination) . $dest;

        $this->logger->debug("src=$src:dest=$dest");
        if ($this->checkIdentical($src, $dest)) {
            $this->logger->debug("identical : $src : $dest");
            return false;
        }

        if (!rename($src, $dest)) {
            $this->logger->error("delete : " . $src);
        }
        return true;
    }

    /**
     * @param $base
     */
    public function execute($base)
    {
        $this->logger->info("parse directory " . $base);
        $scanned_directory = array_diff(scandir($base), $this->excludes_path);

        // parcours le repertoire
        foreach ($scanned_directory as $entry) {
            $currentEntry = $base.$entry;
            if (is_dir(realpath($currentEntry))) {
                $this->execute($currentEntry.DIRECTORY_SEPARATOR);
            } else {
                $dataEntry = $this->getEntryInfo($currentEntry);

                $mime_content_type = $dataEntry['mime'];
                switch ($mime_content_type) {
                    case 'image/jpeg':
                        // images
                        $exif_data = $dataEntry['exif'];
                        $dateEntry = isset($exif_data['DateTimeOriginal']) ? strtotime($exif_data['DateTimeOriginal']) : $exif_data['FileDateTime'];
                        $new_file = $this->classify($dateEntry, '/images/', '.jpg');
                        $this->move_files($currentEntry, $new_file);
                        break;
                    case 'image/gif':
                        // gif
                        $this->move_files($currentEntry,
                            '/gifs/' . $entry);
                        break;
                    case 'video/mp4':
                    case 'application/octet-stream':
                        // videos
                        $this->move_files($currentEntry,
                            '/movies/' . $entry);
                        break;
                    case 'application/vnd.oasis.opendocument.text':
                    case 'image/png':
                    case 'text/plain':
                    case 'application/xml':
                    case 'inode/x-empty':
                    case 'application/CDFV2-unknown':
                        // delete no media files
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
     * @param $entry
     * @return array
     */
    private function getEntryInfo($entry): array
    {
        $dataSet['exif'] = [];
        $dataSet['mime'] = mime_content_type($entry);
        if ($dataSet['mime'] == 'image/jpeg') {
            $dataSet['exif'] = exif_read_data($entry);
        }

        return $dataSet;
    }

    /**
     * @param $fn1
     * @param $fn2
     * @return bool
     */
    private function checkIdentical($fn1, $fn2)
    {
        if (!file_exists($fn1) || !file_exists($fn2)) {
            $this->logger->debug('no file_exists');
            return false;
        }

        if (sha1_file($fn1) === sha1_file($fn2)) {
            $this->logger->debug("sha1_file");
            return true;
        }

        return false;
    }

}