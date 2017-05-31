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
        $newName = date('d-H-i', $fileDateTime) . uniqid('_') . $extension;
        return $directory_root . $year . DIRECTORY_SEPARATOR . $month . DIRECTORY_SEPARATOR . $newName;
    }

    /**
     * @param $src
     * @param $dest
     * @return bool
     */
    public function move_files($src, $dest) :bool
    {
        $src = $this->source . DIRECTORY_SEPARATOR . $src;
        $dest = realpath($this->destination) . DIRECTORY_SEPARATOR . $dest;

        $result = 0;

        if($this->files_identical($src, $dest)){
            $this->logger->debug("identical : $src / $dest");
            return false;
        }

        if (copy($src, $dest)) {
            if (!@unlink($src)) {
                $this->logger->error('delete impossible : ' . $src);
            }
        }
        return true;
    }

    /**
     * @param $directory
     */
    public function execute($directory)
    {
        $this->logger->info("parse directory " . $directory);
        $scanned_directory = array_diff(scandir($directory), $this->excludes_path);

        // parcours le repertoire
        foreach ($scanned_directory as $entry) {
            if (is_dir(realpath($entry))) {
                $this->execute($entry);
            } else {
                $dataEntry = $this->getEntryInfo($entry);

                $mime_content_type = $dataEntry['mime'];
                switch ($mime_content_type) {
                    case 'image/jpeg':
                        // images
                        $exif_data = $dataEntry['exif'];
                        $dateEntry = isset($exif_data['DateTimeOriginal']) ? strtotime($exif_data['DateTimeOriginal']) : $exif_data['FileDateTime'];
                        $new_file = $this->classify($dateEntry, '/images/', '.jpg');
                        $this->move_files($entry, $new_file);
                        break;
                    case 'image/gif':
                        // gif
                        $this->move_files($entry,
                            '/gifs/' . uniqid('gifs_') . '.gif');
                        break;
                    case 'video/mp4':
                    case 'application/octet-stream':
                        // videos
                        $this->move_files($entry,
                            '/movies/' . uniqid('mts_') . '.mp4');
                        break;
                    case 'application/vnd.oasis.opendocument.text':
                    case 'image/png':
                    case 'text/plain':
                    case 'application/xml':
                    case 'inode/x-empty':
                    case 'application/CDFV2-unknown':
                        // delete no media files
                        $this->move_files($entry,
                            '/trash/' . $entry);
                        break;
                    case 'text/x-shellscript':
                    case 'text/x-php':
                    case 'application/CDFV2-corrupt':
                        break;
                    default:
                        $this->logger->info($entry . ': ' . $mime_content_type);
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
        $dataSet['mime'] = mime_content_type($this->source . '/' . $entry);
        if ($dataSet['mime'] == 'image/jpeg') {
            $dataSet['exif'] = exif_read_data($this->source . '/' . $entry);
        }

        return $dataSet;
    }

    /**
     * @param $fn1
     * @param $fn2
     * @return bool
     */
    private function files_identical($fn1, $fn2)
    {
        if (!file_exists($fn1) || !file_exists($fn2)) {
            $this->logger->debug('file_exists');
            return false;
        }

        if (filetype($fn1) !== filetype($fn2)) {
            $this->logger->debug('file_type');
            return false;
        }

        if (filesize($fn1) !== filesize($fn2)) {
            $this->logger->debug('file_size');
            return false;
        }

        if (!$fp1 = fopen($fn1, 'rb')) {
            return false;
        }

        if (!$fp2 = fopen($fn2, 'rb')) {
            fclose($fp1);
            return false;
        }

        $same = true;
        while (!feof($fp1) and !feof($fp2)) {
            if (fread($fp1, READ_LEN) !== fread($fp2, READ_LEN)) {
                $same = false;
                break;
            }
        }

        if (feof($fp1) !== feof($fp2)) {
            $same = false;
        }

        fclose($fp1);
        fclose($fp2);

        return $same;
    }

}