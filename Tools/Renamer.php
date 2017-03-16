<?php

namespace Tools;

use Monolog\Logger;

/**
 * Class Renamer
 */
class Renamer
{

    /**
     * @var
     */
    protected $rapport;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Renamer constructor.
     * @param Logger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }


    /**
     * @param $directory
     */
    public function create_directory($directory)
    {
        if (!is_dir($directory)) {
            try {
                mkdir($directory, 0755, true);
                $this->logger->debug($directory . " créer");
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        } else {
            $this->logger->debug($directory . " existe");
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
    public function parse_directory($directory)
    {
        $this->logger->info("parse directory " . $directory);
        $scanned_directory = array_diff(scandir($directory), ['..', '.', '@eaDir']);
        foreach ($scanned_directory as $entry) {
            if (is_dir(realpath($directory . DIRECTORY_SEPARATOR . $entry))) {
                $this->logger->debug('path:' . realpath($entry));
                $this->parse_directory($directory . DIRECTORY_SEPARATOR . $entry);
            } else {
                $this->logger->debug("entry:" . $entry);
                $mime_content_type = mime_content_type($directory . DIRECTORY_SEPARATOR . $entry);
                switch ($mime_content_type) {
                    case 'image/jpeg':
                        $this->logger->debug($entry);
                        $exif_data = exif_read_data($directory . DIRECTORY_SEPARATOR . $entry);
                        $dateEntry = isset($exif_data['DateTimeOriginal']) ? strtotime($exif_data['DateTimeOriginal']) : $exif_data['FileDateTime'];
                        $this->logger->info($exif_data['FileName'] . ' : ' . date('Y-m-d H:i:s', $dateEntry));
                        $new_file = $this->classify($dateEntry, IMAGES_PATH, '.jpg');
                        $this->move_files($directory . DIRECTORY_SEPARATOR . $entry, $new_file);
                        break;
                    case 'image/gif':
                        $this->move_files($directory . DIRECTORY_SEPARATOR . $entry,
                            GIFS_PATH . uniqid('gifs_') . '.gif');
                        break;
                    case 'video/mp4':
                        $this->move_files($directory . DIRECTORY_SEPARATOR . $entry,
                            VIDEOS_PATH . uniqid('video_') . '.mp4');
                        break;
                    case 'application/octet-stream':
                        $this->move_files($directory . DIRECTORY_SEPARATOR . $entry,
                            VIDEOS_PATH . uniqid('mts_') . '.mp4');
                        break;
                    case 'application/vnd.oasis.opendocument.text':
                    case 'image/png':
                    case 'text/plain':
                    case 'application/xml':
                    case 'inode/x-empty':
                    case 'application/CDFV2-unknown':
                        unlink($directory . DIRECTORY_SEPARATOR . $entry);
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