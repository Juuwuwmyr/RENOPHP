<?php

declare(strict_types=1);

namespace Horizon\Container\Exceptions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class BindingResolutionException extends Exception implements NotFoundExceptionInterface
{
    //
}