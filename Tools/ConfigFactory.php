<?php

namespace Tools;

/**
 * Class ConfigFactory
 * @package Tools
 */
class ConfigFactory
{

    /** @var string */
    public $logfile = '';

    /** @var string */
    public $source = '';

    /** @var string */
    public $destination = '';

    /** @var array */
    public $paths_name = [];

    /** @var array */
    public $excludes = [];

    /** @var array */
    public $size_limits = [];

    /** @var bool */
    public $nokeep = false;

    /** @var bool */
    public $debug = false;

    /** @var */
    public $since;

    /**
     * ConfigFactory constructor.
     * @param string $config_src
     * @return ConfigFactory
     */
    public static function create(string $config_src): ConfigFactory
    {
        $oMe = new self();
        $json_config = file_get_contents($config_src);
        /** @var array $config */
        $config = json_decode($json_config, true);

        foreach ($config as $key => $value) {
            if (property_exists($oMe, $key)) {
                $oMe->{$key} = $value;
            }
        }
        return $oMe;
    }

}