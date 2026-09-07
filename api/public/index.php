<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

$kernel = Kernel::boot(dirname(__DIR__));
$response = $kernel->handle(Request::createFromGlobals());
$response->send();
