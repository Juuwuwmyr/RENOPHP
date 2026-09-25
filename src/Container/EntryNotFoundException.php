<?php

declare(strict_types=1);

namespace Horizon\Container;

use Exception;
use Horizon\Contracts\Container\ContainerException;

class EntryNotFoundException extends Exception implements ContainerException
{
    //
}