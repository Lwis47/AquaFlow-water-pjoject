<?php

declare(strict_types=1);

use AquaFlow\Database;
use AquaFlow\QuoteCalculator;
use AquaFlow\Repository;
use AquaFlow\Response;
use InvalidArgumentException;
use Throwable;

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/QuoteCalculator.php';
require_once __DIR__ . '/../src/Repository.php';
require_once __DIR__ . '/../src/Response.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$route = trim((string) ($_GET['route'] ?? ''), '/');

try {
    $repository = new Repository(Database::connection());

    if ($method === 'GET' && $route === 'health') {
        Response::send(['ok' => true, 'data' => ['service' => 'AquaFlow API']]);
    }

    if ($method === 'GET' && $route === 'clients') {
        $search = (string) ($_GET['search'] ?? '');
        Response::send(['ok' => true, 'data' => $repository->clients($search)]);
    }

    if ($method === 'POST' && $route === 'clients') {
        $client = $repository->createClient(Response::input());
        Response::send(['ok' => true, 'data' => $client], 201);
    }

    if ($method === 'PUT' && preg_match('#^clients/(\\d+)$#', $route, $matches)) {
        Response::send(['ok' => true, 'data' => $repository->updateClient((int) $matches[1], Response::input())]);
    }

    if ($method === 'DELETE' && preg_match('#^clients/(\\d+)$#', $route, $matches)) {
        $repository->deleteClient((int) $matches[1]);
        Response::send(['ok' => true, 'data' => ['deleted' => true]]);
    }

    if ($method === 'GET' && $route === 'services') {
        Response::send(['ok' => true, 'data' => $repository->services()]);
    }

    if ($method === 'POST' && $route === 'services') {
        Response::send(['ok' => true, 'data' => $repository->createService(Response::input())], 201);
    }

    if ($method === 'PUT' && preg_match('#^services/(\\d+)$#', $route, $matches)) {
        Response::send(['ok' => true, 'data' => $repository->updateService((int) $matches[1], Response::input())]);
    }

    if ($method === 'GET' && $route === 'applications') {
        $search = (string) ($_GET['search'] ?? '');
        $status = (string) ($_GET['status'] ?? '');
        Response::send(['ok' => true, 'data' => $repository->applications($search, $status)]);
    }

    if ($method === 'POST' && $route === 'applications') {
        $application = $repository->createApplication(Response::input());
        Response::send(['ok' => true, 'data' => $application], 201);
    }

    if ($method === 'PUT' && preg_match('#^applications/(\\d+)$#', $route, $matches)) {
        Response::send(['ok' => true, 'data' => $repository->updateApplication((int) $matches[1], Response::input())]);
    }

    if ($method === 'POST' && $route === 'quotes/calculate') {
        $quote = QuoteCalculator::calculate(Response::input());
        Response::send(['ok' => true, 'data' => $quote]);
    }

    if ($method === 'POST' && $route === 'quotes') {
        $quote = $repository->saveQuote(Response::input());
        Response::send(['ok' => true, 'data' => $quote], 201);
    }

    if ($method === 'GET' && $route === 'reports/overview') {
        Response::send(['ok' => true, 'data' => $repository->overview()]);
    }

    Response::error('Route not found.', 404);
} catch (InvalidArgumentException $exception) {
    Response::error($exception->getMessage(), 422);
} catch (Throwable) {
    Response::error('The server could not process this request.', 500);
}
