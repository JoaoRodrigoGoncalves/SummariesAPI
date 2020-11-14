<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
require_once("./functions.php");
require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->setBasePath('/summaries/api/v5');
$app->addErrorMiddleware(false, false, false);

$customResponse['status'] = false;
$customResponse['errors'] = "";

/**
 * Login
 * Method -> POST
 * Parameters -> usrnm, psswd
 */

$app->post('/login', function (Request $request, Response $response, array $args){
    global $customResponse;
    $params = (array)$request->getParsedBody();
    $customResponse['errors'] = $params['usrnm'];
    $response->getBody()->write(json_encode($customResponse));
    return $response;
});

/**
 *  Logout
 *  Method -> GET
 *  Parameters -> (none)
 */

$app->get('/logout', function (Request $request, Response $response, array $args) {
    global $customResponse;

    $response->getBody()->write(json_encode($customResponse));
    return $response;
});


$app->run();
?>