<?php

namespace OzanKurt\Tracker\Data\Repositories;

use Kurt\Support\Config;

class Route extends Repository
{
    public function __construct($model, Config $config)
    {
        parent::__construct($model);

        $this->config = $config;
    }
}
