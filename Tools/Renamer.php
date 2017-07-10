<?php

namespace Tools;

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Tools\Media\Photo;
use Tools\System\Directory;
use Tools\System\File;

/**
 * Class Renamer
 */
class Renamer
{

    use LoggerAwareTrait;

    /** @var  string */
    protected $base_path;

    /** @var  boolean */
    protected $keep_source;

    /** @var string */
    protected $source;

    /** @var string */
    protected $destination;

    /** @var array */
    protected $size = [];

    /** @var ConfigFactory */
    protected $config = [];

    /**
     * Renamer constructor.
     * @param ConfigFactory $configFactory
     * @internal param $source
     * @internal param $destination
     * @throws \Exception
     */
    public function __construct(ConfigFactory $configFactory)
    {
        $this->keep_source = true;
        $this->applyConfig($configFactory);
    }

    /**
     * @param ConfigFactory $configFactory
     * @return $this
     * @throws \Exception
     */
    public function applyConfig(ConfigFactory $configFactory)
    {
        $this->config = $configFactory;
        $this->source = realpath($configFactory->source) . '/';
        $this->destination = realpath($configFactory->destination) . '/';
        $this->base_path = $this->destination . DIRECTORY_SEPARATOR;
        $paths = [];
        if (!empty($configFactory->paths_name)) {
            $paths = $configFactory->paths_name;
        }
        foreach ($paths as $path) {
            // création des répertoire de base
            Directory::create($this->base_path . $path);
        }
        $this->setLogger();
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function setLogger()
    {
        $logger = new Logger('renamer');
        switch ($this->config->debug){
            case 'ERROR':
                $level = Logger::ERROR;
                break;
            case 'INFO':
                $level = Logger::INFO;
                break;
            default:
            case 'WARNING':
                $level = Logger::WARNING;
                break;
        }
        $logHandler = new StreamHandler('php://stdout', $level);
        $logHandler->setFormatter(new ColoredLineFormatter());
        $logger->pushHandler($logHandler);
        $this->logger = $logger;
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
        Directory::create($this->base_path . $directory_root . $year);
        Directory::create($this->base_path . $directory_root . $year . DIRECTORY_SEPARATOR . $month);

        // génère un nouveau nom pour le fichier
        $newName = date('d-H-i-s', $fileDateTime) . $extension;
        return $directory_root . $year . DIRECTORY_SEPARATOR . $month . DIRECTORY_SEPARATOR . $newName;
    }


    /**
     * Renomage de masse des fichiers depuis le dossier source
     * @param $base
     * @throws \Exception
     */
    public function execute($base = null)
    {
        if (null === $base) {
            $base = $this->config->source;
        }
        $this->logger->info('parse directory ' . $base);
        $scanned_directory = Directory::parse($base, $this->config->excludes);
        // parcours le repertoire
        foreach ($scanned_directory as $entry) {
            $currentEntry = $base . DIRECTORY_SEPARATOR . $entry;
            // si l'entrée est un repertoire on rentre dans l'arborescence
            if (is_dir(realpath($currentEntry))) {
                $this->execute($currentEntry . DIRECTORY_SEPARATOR);
            } else {
                $this->logger->info('::' . $currentEntry);
                $file = new File($currentEntry);
                $file->logger = $this->logger;
                $this->logger->debug($file->getMime());
                // selon le type de fichier
                switch ($file->getMime()) {
                    case 'image/jpeg':
                        $photo = new Photo($file);
                        $photo->logger = $this->logger;
                        // on récupère les infos du fichier (EXIF si une image)
                        $exif_data = $photo->exif;
                        if ($dateEntry = $photo->getDate($exif_data)) {
                            $images_path = $photo->isGoodSize($this->size) ? '/' . $this->config->paths_name['pictures'] . '/' : '/' . $this->config->paths_name['trash'] . '/';
                            $new_file = $this->classify($dateEntry, $images_path);
                            $file->move($this->destination . $new_file, $this->config->nokeep);
                        }
                        break;
                    case 'image/gif':
                        $file->move($this->destination . $this->config->paths_name['gifs'] . '/' . $entry, true);
                        break;
                    case 'video/mp4':
                        $file->move($this->destination . $this->config->paths_name['movies'] . '/' . $entry, true);
                        break;
                    case 'application/octet-stream':
                        $dest = $this->destination . $this->config->paths_name['movies'] . '/' . $entry;
                        $file->stream_copy($dest);
                        break;
                    case 'application/vnd.oasis.opendocument.text':
                    case 'image/png':
                    case 'text/plain':
                    case 'application/xml':
                    case 'inode/x-empty':
                    case 'application/CDFV2-unknown':
                        $file->move($this->destination . $this->config->paths_name['trash'] . '/' . $entry);
                        break;
                    case 'text/x-shellscript':
                    case 'text/x-php':
                    case 'application/CDFV2-corrupt':
                        break;
                    default:
                        $file->move($this->base_path . $this->config->paths_name['trash'] . '/' . $entry);
                        $this->logger->info($currentEntry . ': ' . $file->getMime());
                        die;
                        break;
                }
            }
        }
    }

}