<?php

namespace Tools\Media;

use Tools\LoggerAwareTrait;
use Tools\System\File;

/**
 * Class Photo
 * @package Tools\Media
 */
class Photo
{

    use LoggerAwareTrait;

    /** @var array */
    public $exif;

    /** @var  int */
    public $width;

    /** @var  int */
    public $height;

    /**
     * Photo constructor.
     * @param $file
     */
    public function __construct(File $file)
    {
        try {
            $this->exif = exif_read_data($file->src);
        } catch (\Exception $e) {

        }
        list($width, $height) = getimagesize($file->src);
        $this->width = $width;
        $this->height = $height;
    }


    /**
     * @param array $exif
     * @return false|int
     */
    public function getDate(array $exif)
    {

        $timestamp = 0;
        if (!empty($exif['DateTimeOriginal'])) {
            $timestamp = strtotime($exif['DateTimeOriginal']);
        } elseif (!empty($exif['DateTime'])) {
            $timestamp = strtotime($exif['DateTime']);
        } elseif (!empty($exif['DateTimeDigitized'])) {
            $timestamp = strtotime($exif['DateTimeDigitized']);
        }   elseif (!empty($exif['FileDateTime'])) {
            $timestamp = $exif['FileDateTime'];
        }
        // si le timestamp de la photo à plus de trois mois, il y a un soucis
        if (empty($timestamp)) {
            $this->logger->error('pb de date : ' . $exif['FileName']);
            return false;
        }
        return $timestamp;

    }

    /**
     * @param $size
     * @return bool
     */
    public function isGoodSize($size): bool
    {
        if (!empty($this->size)) {
            if ($size['width'] < $this->width || $size['height'] < $this->height) {
                return false;
            }
        }
        return true;

    }

}