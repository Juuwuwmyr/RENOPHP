<?php

declare(strict_types=1);

namespace Horizon\Config;

use Exception;

class ConfigurationException extends Exception
{
    /**
     * Get a suggested solution for this configuration error.
     */
    public function getSolution(): string
    {
        return "Check your .env file and ensure all required configuration values are set.";
    }
}