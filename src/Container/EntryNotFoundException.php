<?php

declare(strict_types=1);

namespace Reno\Container;

use Exception;
use Reno\Contracts\Container\ContainerException;

class EntryNotFoundException extends Exception implements ContainerException
{
    //
}