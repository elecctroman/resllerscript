<?php declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\ResellerApi\Http\ApiKernel;
use App\ResellerApi\Http\Request;
use App\ResellerApi\Services\ApiGateway;

$kernel = new ApiKernel(new ApiGateway());
$response = $kernel->handle(Request::fromGlobals());
$response->send();
