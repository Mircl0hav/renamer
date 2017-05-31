<?php

namespace Tools;

use Monolog\Handler\StreamHandler;
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
        $this->source = $source;
        $this->destination = $destination;

        $this->create_directory('images');
        $this->create_directory('gifs');
        $this->create_directory('videos');
        $this->create_directory('trash');

        $this->logger = new Logger("renamer");
        $this->logger->pushHandler(new StreamHandler(__DIR__ . DIRECTORY_SEPARATOR . "logs/renamer.log",
            Logger::DEBUG));
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
                $this->logger->debug($directory . " created");
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        } else {
            $this->logger->debug($directory . " exist");
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
        $newName = date('d-H-i-s', $fileDateTime) . uniqid('_') . $extension;
        return $directory_root . $year . DIRECTORY_SEPARATOR . $month . DIRECTORY_SEPARATOR . $newName;
    }

    /**
     * @param $src
     * @param $dest
     */
    public function move_files($src, $dest)
    {
        $src = $this->source . DIRECTORY_SEPARATOR . $src;
        $dest = $this->destination . DIRECTORY_SEPARATOR . $dest;

        $result = 0;
        if (copy($src, $dest)) {
            $this->logger->debug($src . ' :: ' . $dest);
            if (unlink($src)) {
                $result = 1;
            }
            $this->logger->debug(implode("||", [
                'action'      => 'move',
                'source'      => $src,
                'destination' => $dest,
                'state'       => $result,
            ]));
        }
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
                $this->logger->debug('path:' . realpath($entry));
                $this->execute($entry);
            } else {
                $this->logger->debug("entry:" . $entry);
                $mime_content_type = mime_content_type($entry);
                switch ($mime_content_type) {
                    case 'image/jpeg':
                        // images
                        $this->logger->debug($entry);
                        $exif_data = exif_read_data($entry);
                        $dateEntry = isset($exif_data['DateTimeOriginal']) ? strtotime($exif_data['DateTimeOriginal']) : $exif_data['FileDateTime'];
                        $this->logger->info($exif_data['FileName'] . ' : ' . date('Y-m-d H:i:s', $dateEntry));
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

}