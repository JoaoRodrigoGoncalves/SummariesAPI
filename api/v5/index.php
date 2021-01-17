<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
require_once("./functions.php");
require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->setBasePath('/summaries/api/v5');
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$customResponse['status'] = false;
$customResponse['errors'] = "";

/**
 * Custom Error Handler for Slim Framework
 * Inspired by https://stackoverflow.com/a/57648863/10935376
 */

$customErrorHandler = function (Psr\Http\Message\ServerRequestInterface $request, \Throwable $exception, bool $displayErrorDetails, bool $logErrors, bool $logErrorDetails) use($app){
    global $customResponse;
    $code = 500;

    if($exception instanceof HttpNotFoundException){
        $customResponse['errors'] = "Not Found";
        $code = 404;
    }elseif ($exception instanceof HttpMethodNotAllowedException) {
        $customResponse['errors'] = "Method Not Allowed";
        $code = 405;
    }else{
        $customResponse['errors'] = "Internal Server Error";
    }

    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(json_encode($customResponse));
    return $response->withStatus($code);
};

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);

if(!CheckIfSecure()){
    header("HTTP/1.0 400 Bad Request");
    $customResponse['errors'] = "Connection Must Be Made With HTTPS";
    exit();
}

/**
 * Login
 * Method -> POST
 * Parameters -> usrnm, psswd
 */

$app->post('/login', function (Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $params = (array)$request->getParsedBody();
    
    if(isset($params['usrnm']) && isset($params['psswd'])){
        $user = mysqli_real_escape_string($connection, base64_decode($params['usrnm']));
        $password = mysqli_real_escape_string($connection, base64_decode($params['psswd']));
        $query = "SELECT * FROM users WHERE user='$user' LIMIT 1";
        $run = mysqli_query($connection, $query);
        if($run){
            if(mysqli_num_rows($run) == 1){
                while($row = mysqli_fetch_array($run, MYSQLI_ASSOC)){
                    $userID = $row['id'];
                    $dbpassword = $row['password'];
                    $username = $row['user'];
                    $classID = $row['classID'];
                    $displayName = $row['displayName'];
                    $adminControl = ($row['adminControl']==1 ? true : false);
                }
                if(password_verify($password, $dbpassword)){
                    $customResponse['status'] = true;
                    $customResponse['AccessToken'] = $authTokens->GenerateAccessToken($userID);
                    $customResponse['userID'] = $userID;
                    $customResponse['username'] = $username;
                    $customResponse['classID'] = $classID;
                    $customResponse['displayName'] = $displayName;
                    $customResponse['adminControl'] = $adminControl;
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(200);
                }else{
                    $customResponse['errors'] = "Authentication Failed";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(401);    
                }
            }else{
                $customResponse['errors'] = "Authentication Failed";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(401);
            }
        }else{
            $customResponse['errors'] = mysqli_error($connection);
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(500);
        }
    }else{
        $customResponse['errors'] = "Missing Parameters";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(400);
    }
});

/**
 *  Logout
 *  Method -> GET
 *  Parameters -> (none)
 */

$app->get('/logout', function (Request $request, Response $response, array $args) {
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    
    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if($authTokens->DeleteToken($AccessToken)){
                $customResponse['status'] = true;
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(200);
            }else{
                $customResponse['errors'] = "Failed to Delete Token";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(500);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/////////////////////////////////////////////
//                  CLASS
//      All class-related endpoints
/////////////////////////////////////////////

/**
 * Class List
 * Method -> GET
 * Parameters -> (none)
 */

$app->get('/class', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $classFunctions = new ClassFunctions();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $list = $classFunctions->GetClassList();
            if($list){
                $customResponse['status'] = true;
                $customResponse['contents'] = $list;
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(200);
            }else{
                $customResponse['errors'] = "An Error Occurred While Trying to Retrive the List of Classes";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(500);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

 /**
  * Class Info by ID
  * Method -> GET
  * Parameters -> classID
  */

$app->get('/class/{classID}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $classFunctions = new ClassFunctions();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $classID = mysqli_real_escape_string($connection, $args['classID']);
            if($classFunctions->ClassExists(null, $classID)){
                $info = $classFunctions->GetClass($classID);
                if($info){
                    $customResponse['status'] = true;
                    $customResponse['contents'] = $info;
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(200);
                }else{
                    $customResponse['errors'] = "An Error Occurred while Trying to Get Class Info";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(500);
                }
            }else{
                $customResponse['errors'] = "Class Not Found";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(404);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
    $response->getBody()->write(json_encode($customResponse));
    return $response;
});

/**
 * Get All Users That Are Part Of The Specified Class
 * Method -> GET
 * Parameters -> classID
 */

$app->get('/class/{classID}/users', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $classFunctions = new ClassFunctions();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $classID = mysqli_real_escape_string($connection, $args['classID']);
            if($classFunctions->ClassExists(null, $classID)){
                $query = mysqli_query($connection, "SELECT id, user, displayName, classID, AdminControl, isDeletionProtected FROM users WHERE classID=$classID");
                if($query){
                    if(mysqli_num_rows($query) > 0){
                        $list = null;
                        $i = 0;
                        while($row = mysqli_fetch_array($query, MYSQLI_ASSOC)){
                            $list[$i]['userid'] = $row['id'];
                            $list[$i]['user'] = $row['user'];
                            $list[$i]['displayName'] = $row['displayName'];
                            $list[$i]['classID'] = $row['classID'];
                            $list[$i]['isAdmin'] = ($row['adminControl'] == 1 ? true : false);
                            $list[$i]['isDeletionProtected'] = ($row['isDeletionProtected'] == 1 ? true : false);
                            $i++;
                        }
                        $customResponse['status'] = true;
                        $customResponse['contents'] = $list;
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(200);
                    }else{
                        $customResponse['status'] = true;
                        $customResponse['contents'] = null;
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(200);
                    }
                }else{
                    $customResponse['errors'] = mysqli_error($connection);
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(500);
                }
            }else{
                $customResponse['errors'] = "Class Not Found";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(404);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Create New Class
 * Method -> POST
 * Parameters -> name
 */

$app->post('/class', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    $classFunctions = new ClassFunctions();
    $params = (array)$request->getParsedBody();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if($userFunctions->isUserAdmin($userID)){
                if(isset($params['className'])){
                    $className = mysqli_real_escape_string($connection, $params['className']);
                    if($classFunctions->EditClass($className)){
                        $customResponse['status'] = true;
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(201);
                    }else{
                        $customResponse['errors'] = "An Error Occurred While Trying to Create a Class";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(500);
                    }
                }else{
                    $customResponse['errors'] = "Missing Parameters";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(400);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Edit Class
 * Method -> PUT
 * Parameters -> classID, name
 */

$app->put('/class/{classID}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    $classFunctions = new ClassFunctions();
    $params = (array)$request->getParsedBody();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if($userFunctions->isUserAdmin($userID)){
                if(isset($params['className'])){
                    $className = mysqli_real_escape_string($connection, $params['className']);
                    $classID = mysqli_real_escape_string($connection, $args['classID']);
                    if($classFunctions->ClassExists(null, $classID)){
                        if($classFunctions->EditClass($className, $classID)){
                            $customResponse['status'] = true;
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(201);
                        }else{
                            $customResponse['errors'] = "An Error Occurred While Trying to Edit a Class";
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(500);
                        }
                    }else{
                        $customResponse['errors'] = "Class Not Found";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(404);
                    }
                }else{
                    $customResponse['errors'] = "Missing Parameters";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(400);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Delete Class
 * Method -> DELETE
 * Parameters -> classID
 */

$app->delete('/class/{classID}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $classFunctions = new ClassFunctions();
    $userFunctions = new UserFunctions();
    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if($userFunctions->isUserAdmin($userID)){
                $classID = mysqli_real_escape_string($connection, $args['classID']);
                if($classFunctions->DeleteClass($classID)){
                    $customResponse['status'] = true;
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(200);
                }else{
                    $customResponse['errors'] = "An Error Occurred While Trying to Delete a Class";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(500);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});


/////////////////////////////////////////////
//                  USER
// All User-realted (incl. summaries) endpoints
/////////////////////////////////////////////

/**
 * Get All Users
 * Method -> GET
 * Parameters -> (none)
 */

$app->get('/user', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if($userFunctions->isUserAdmin($userID)){
                $list = $userFunctions->GetUserList();
                if($list){
                    $customResponse['status'] = true;
                    $customResponse['contents'] = $list;
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(200);
                }else{
                    $customResponse['errors'] = "An Error Occurred While Trying to Retrive the User List";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(500);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * User Info By ID
 * Method -> GET
 * Parameters -> userID
 */

$app->get('/user/{userID}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
            if($userFunctions->isUserAdmin($userID) || $userID==$requestedUser){
                if($userFunctions->UserExists($requestedUser)){
                    $contents = $userFunctions->GetUser($requestedUser);
                    if($contents){
                        $customResponse['status'] = true;
                        $customResponse['contents'] = $contents;
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(200);
                    }else{
                        $customResponse['errors'] = "An Error Occured While Trying to Get User Info";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(500);
                    }
                }else{
                    $customResponse['errors'] = "User Not Found";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(404);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Get User's Summary List
 * Method -> GET
 * Parameters -> userID, workspaceID
 */

$app->get('/user/{userID}/workspace/{workspaceID}/summary', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    $workspaceFunctions = new WorkspaceFunctions();
    $summaryFunctions = new SummaryFunctions();
    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
            if($userFunctions->isUserAdmin($userID) || $userID==$requestedUser){
                if($userFunctions->UserExists($requestedUser)){
                    $workspaceID = mysqli_real_escape_string($connection, $args['workspaceID']);
                    if($workspaceFunctions->WorkspaceExists($workspaceID) || $workspaceID == 0){
                        $workspaceID = ($workspaceID==0) ? null : $workspaceID;
                        $list = $summaryFunctions->GetSummariesList($requestedUser, $workspaceID);
                        if($list || $list == null){
                            $customResponse['status'] = true;
                            $customResponse['contents'] = $list;
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(200);
                        }else{
                            $customResponse['errors'] = "An Error Occurred While Trying to Get All User's Summaries";
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(500);
                        }
                    }else{
                        $customResponse['errors'] = "Workspace Not Found";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(404);
                    }
                }else{
                    $customResponse['errors'] = "User Not Found";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(404);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Get a Summary
 * Method -> GET
 * Parameters -> userID, summaryID
 */

$app->get('/user/{userID}/summary/{summaryID}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    $filesFunctions = new FilesFunctions();
    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
            if($userFunctions->isUserAdmin($userID) || $userID==$requestedUser){
                if($userFunctions->UserExists($requestedUser)){
                    $summaryID = mysqli_real_escape_string($connection, $args['summaryID']);
                    $query = mysqli_query($connection, "SELECT * FROM summaries WHERE userid=$requestedUser AND id=$summaryID LIMIT 1");
                    if($query){
                        if(mysqli_num_rows($query) == 1){
                            $contents = "";
                            while($row = mysqli_fetch_array($query, MYSQLI_ASSOC)){
                                $contents['summaryID'] = $row['id'];
                                $contents['userID'] = $row['userid'];
                                $contents['date'] = $row['date'];
                                $contents['summaryNumber'] = $row['summaryNumber'];
                                $contents['workspace'] = $row['workspace'];
                                $contents['contents'] = $row['contents'];
                            }
                            $files = $filesFunctions->GetFilesList($contents['dbRow']);
                            if($files){
                                $contents['files'] = $files;
                            }else{
                                $contents['files'] = null;
                            }
                            $customResponse['status'] = true;
                            $customResponse['contents'] = $contents;
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(200);
                        }else{
                            $customResponse['errors'] = "Summary Not Found";
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(404);
                        }
                    }else{
                        $customResponse['errors'] = mysqli_error($connection);
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(500);
                    }
                }else{
                    $customResponse['errors'] = "User Not Found";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(404);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Get a Summary's File List
 * Method -> GET
 * Parameters -> userID, summaryID
 */

$app->get('/user/{userID}/summary/{summaryID}/files', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    $summaryFunctions = new SummaryFunctions();
    $filesFunctions = new FilesFunctions();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
            if($userFunctions->isUserAdmin($userID) || $userID==$requestedUser){
                if($userFunctions->UserExists($requestedUser)){
                    $summaryID = mysqli_real_escape_string($connection, $args['summaryID']);
                    if($summaryFunctions->SummaryExists($summaryID)){
                        if($summaryFunctions->CheckSummaryOwnership($requestedUser, $summaryID) || $userFunctions->isUserAdmin($userID)){
                            $files = $filesFunctions->GetFilesList($summaryID);
                            if($files){
                                if($files == ""){
                                    $customResponse['errors'] = "No Files Found";
                                    $response->getBody()->write(json_encode($customResponse));
                                    return $response->withStatus(404);
                                }else{
                                    $customResponse['status'] = true;
                                    $customResponse['contents'] = $files;
                                    $response->getBody()->write(json_encode($customResponse));
                                    return $response->withStatus(200);
                                }
                            }else{
                                $customResponse['errors'] = "An Error Occurred While Trying to Retrieve the Files List";
                                $response->getBody()->write(json_encode($customResponse));
                                return $response->withStatus(500);
                            }
                        }else{
                            $customResponse['errors'] = "Permission Denied";
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(403);
                        }
                    }else{
                        $customResponse['errors'] = "Summary Not Found";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(404);
                    }
                }else{
                    $customResponse['errors'] = "User Not Found";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(404);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Get a Specific File From a Summary
 * Method -> GET
 * Parameters -> userID, summaryID, file
 */

$app->get('/user/{userID}/summary/{summaryID}/files/{file}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    $summaryFunctions = new SummaryFunctions();
    $filesFunctions = new FilesFunctions();
    $API_Settings = new API_Settings();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
            if($userFunctions->isUserAdmin($userID) || $userID == $requestedUser){
                if($userFunctions->UserExists($requestedUser)){
                        $summaryID = mysqli_real_escape_string($connection, $args['summaryID']);
                        if($summaryFunctions->SummaryExists($summaryID)){
                            if($userFunctions->isUserAdmin($userID) || $summaryFunctions->CheckSummaryOwnership($requestedUser, $summaryID)){
                                $fileInServerName = mysqli_real_escape_string($connection, $args['file']);
                                if($filesFunctions->FileExists($fileInServerName)){
                                    $filePath = $API_Settings->filesPath . $fileInServerName;

                                    if(file_exists(ROOT_FOLDER . "/" . $filePath)){
                                        $actualName = $filesFunctions->GetName($filePath);
                                        if($actualName){
                                            // https://www.php.net/manual/en/function.readfile.php
                                            header('Content-Description: File Transfer');
                                            header('Content-Type: application/octet-stream');
                                            header('Content-Disposition: attachment; filename="'. $actualName .'"');
                                            header('Expires: 0');
                                            header('Cache-Control: must-revalidate');
                                            header('Pragma: public');
                                            header('Content-Length: ' . filesize(ROOT_FOLDER . "/" . $filePath));
                                            readfile(ROOT_FOLDER . "/" . $filePath);
                                            exit();
                                        }else{
                                            $customResponse['errors'] = "Could Not Get File Name";
                                            $response->getBody()->write(json_encode($customResponse));
                                            return $response->withStatus(500);
                                        }
                                    }else{
                                        $customResponse['errors'] = "Resource Not Found";
                                        $response->getBody()->write(json_encode($customResponse));
                                        return $response->withStatus(404);
                                    }
                                }else{
                                    $customResponse['errors'] = "File Not Found";
                                    $response->getBody()->write(json_encode($customResponse));
                                    return $response->withStatus(404);
                                }
                            }else{
                                $customResponse['errors'] = "Permission Denied";
                                $response->getBody()->write(json_encode($customResponse));
                                return $response->withStatus(403);
                            }
                        }else{
                            $customResponse['errors'] = "Summary Not Found";
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(404);
                        }
                }else{
                    $customResponse['errors'] = "User Not Found";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(404);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Create a New User
 * Method -> POST
 * Parameters -> username, displayName, classID, isAdmin, isDeletionProtected
 */

$app->post('/user', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    $params = (array)$request->getParsedBody();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if($userFunctions->isUserAdmin($userID)){
                if(isset($params['username']) && isset($params['displayName']) && isset($params['classID']) && isset($params['isAdmin']) && isset($params['isDeletionProtected'])){
                    $username = mysqli_real_escape_string($connection, $params['username']);
                    $displayName = mysqli_real_escape_string($connection, $params['displayName']);
                    $classID = mysqli_real_escape_string($connection, $params['classID']);
                    $isAdmin = mysqli_real_escape_string($connection, $params['isAdmin']);
                    $isDeletionProtected = mysqli_real_escape_string($connection, $params['isDeletionProtected']);
                    $isAdmin = ($isAdmin == 1 || $isAdmin == "true" || $isAdmin == "True" ? true : false);
                    $isDeletionProtected = ($isDeletionProtected == 1 || $isDeletionProtected == "true" || $isDeletionProtected == "True" ? true : false);
                    if(is_numeric($classID)){
                        if($userFunctions->EditUser($username, $displayName, $classID, $isAdmin, $isDeletionProtected)){
                            $customResponse['status'] = true;
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(200);
                        }else{
                            $customResponse['errors'] = "An error occurred while trying to create a new user";
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(500);
                        }
                    }else{
                        $customResponse['errors'] = "Invalid Parameters";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(400);
                    }
                }else{
                    $customResponse['errors'] = "Missing Parameters";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(400);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Create a New Summary
 * Method -> POST
 * Parameters -> userID, workspaceID, summaryID, date, bodyText
 */

$app->post('/user/{userID}/workspace/{workspaceID}/summary', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $workspaceFunctions = new WorkspaceFunctions();
    $summaryFunctions = new SummaryFunctions();
    $params = (array)$request->getParsedBody();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if(isset($params['summaryNumber']) && isset($params['date']) && isset($params['bodyText'])){
                $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
                if($userID == $requestedUser){
                    $workspaceID = mysqli_real_escape_string($connection, $args['workspaceID']);
                    if($workspaceFunctions->WorkspaceExists($workspaceID)){
                        $summaryNumber = mysqli_real_escape_string($connection, $params['summaryNumber']);
                        if(!$summaryFunctions->FindSummary($requestedUser, $summaryNumber, $workspaceID)){
                            $date = mysqli_real_escape_string($connection, base64_decode($params['date']));
                            if(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date)){
                                $bodyText = mysqli_real_escape_string($connection, base64_decode($params['bodyText']));
                                $summaryID = $summaryFunctions->EditSummary(false, $requestedUser, $summaryNumber, $workspaceID, $date, $bodyText);
                                if($summaryID){
                                    if(isset($params['filesToAdopt'])){
                                        $files = base64_decode($_POST['filesToAdopt']);
                                        $filesToAdopt = json_decode($files);

                                        foreach ($filesToAdopt as $id) {
                                            $adopt = mysqli_query($connection, "UPDATE attachmentMapping SET summaryID='$summaryID' WHERE id='$id'");
                                            if(!$adopt){
                                                $customResponse['errors'] = "ADPFI: " . mysqli_error($connection);
                                                $response->getBody()->write(json_encode($customResponse));
                                                return $response->withStatus(500);
                                            }
                                        }
                                    }
                                    $customResponse['status'] = true;
                                    $customResponse['rowID'] = $summaryID;
                                    $response->getBody()->write(json_encode($customResponse));
                                    return $response->withStatus(200);
                                }else{
                                    $customResponse['errors'] = "An error occurred while trying to create the summary";
                                    $response->getBody()->write(json_encode($customResponse));
                                    return $response->withStatus(500);
                                }
                            }else{
                                $customResponse['errors'] = "Invalid Date Syntax";
                                $response->getBody()->write(json_encode($customResponse));
                                return $response->withStatus(400);
                            }
                        }else{
                            $customResponse['errors'] = "Summary ID already in use";
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(409);
                        }
                    }else{
                        $customResponse['errors'] = "Workspace Not Found";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(404);
                    }
                }else{
                    $customResponse['errors'] = "Permission Denied";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(403);
                }
            }else{
                $customResponse['errors'] = "Missing Parameters";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(400);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Upload File
 * Method -> POST
 * Parameters -> userID, file
 */
$app->post('/user/{userID}/uploadfile', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $filesFunctions = new FilesFunctions();
    $settings = new API_Settings();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
            if($userID==$requestedUser){
                $uploadDirectory = ROOT_FOLDER . "/" . $settings->filesPath;
                $uploadedFiles = $request->getUploadedFiles();
                $uploadedFile = $uploadedFiles["file"];

                if($uploadedFile->getError() === UPLOAD_ERR_OK){
                    $extension = pathinfo($uploadedFile->getClientFileName(), PATHINFO_EXTENSION);

                    if($filesFunctions->isFileTypeBlocked($extension) || $uploadedFile->getSize() > $settings->maxFileSize){
                        $customResponse['errors'] = "File type not allowed or is too large!";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(406);
                    }else{
                        $basename = bin2hex(random_bytes(16));
                        $uploadedFile->moveTo($uploadDirectory . $basename);

                        $storedpath = mysqli_real_escape_string($connection, $settings->filesPath . $basename);

                        $query = "INSERT INTO attachmentMapping (filename, path) VALUES ('" . $uploadedFile->getClientFileName() . "', '$storedpath')";
                        $run = mysqli_query($connection, $query);
                        if($run){
                            $getRow = mysqli_query($connection, "SELECT id FROM attachmentMapping WHERE path='$storedpath'");
                            if($getRow){
                                if(mysqli_num_rows($getRow) > 0){
                                    while($row = mysqli_fetch_array($getRow, MYSQLI_ASSOC)){
                                        $customResponse['rowID'] = $row['id'];
                                    }
                                    $customResponse['status'] = true;
                                    $response->getBody()->write(json_encode($customResponse));
                                    return $response->withStatus(200);
                                }else{
                                    $customResponse['errors'] = "Record not found";
                                    $response->getBody()->write(json_encode($customResponse));
                                    return $response->withStatus(404);
                                }
                            }else{
                                $customResponse['errors'] = mysqli_error($connection);
                                $response->getBody()->write(json_encode($customResponse));
                                return $response->withStatus(500);
                            }
                        }else{
                            $customResponse['errors'] = "Error: " . mysqli_error($connection);
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(500);
                        }
                    }
                }else{
                    $customResponse['errors'] = "An Error Occurred While Trying to Upload The File";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(500);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Edit User
 * Method -> PUT
 * Parameters -> userID, username, displayName, classID, isAdmin, isDeletionProtected
 */

$app->put('/user/{userID}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    $params = (array)$request->getParsedBody();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if($userFunctions->isUserAdmin($userID)){
                if(isset($params['username']) && isset($params['displayName']) && isset($params['classID']) && isset($params['isAdmin']) && isset($params['isDeletionProtected'])){
                    $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
                    $username = mysqli_real_escape_string($connection, $params['username']);
                    $displayName = mysqli_real_escape_string($connection, $params['displayName']);
                    $classID = mysqli_real_escape_string($connection, $params['classID']);
                    $isAdmin = mysqli_real_escape_string($connection, $params['isAdmin']);
                    $isDeletionProtected = mysqli_real_escape_string($connection, $params['isDeletionProtected']);
                    $isAdmin = ($isAdmin == 1 || $isAdmin == "true" || $isAdmin == "True" ? true : false);
                    $isDeletionProtected = ($isDeletionProtected == 1 || $isDeletionProtected == "true" || $isDeletionProtected == "True" ? true : false);
                    if(is_numeric($classID)){
                        if($userFunctions->EditUser($username, $displayName, $classID, $isAdmin, $isDeletionProtected, $requestedUser)){
                            $customResponse['status'] = true;
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(200);
                        }else{
                            $customResponse['errors'] = "An Error Occurred While Trying to Edit the User";
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(500);
                        }
                    }else{
                        $customResponse['errors'] = "Invalid Parameters";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(400);
                    }
                }else{
                    $customResponse['errors'] = "Missing Parameters";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(400);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Change User Password
 * Method -> PUT
 * Parameters -> userID, oldpasswd, newpasswd
 */

$app->put('/user/{userID}/changepassword', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    $params = (array)$request->getParsedBody();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if(isset($params['oldpasswd']) && isset($params['newpasswd'])){
                $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
                if($userID == $requestedUser || $userFunctions->isUserAdmin($userID)){
                    $oldpw = mysqli_real_escape_string($connection, base64_decode($params['oldpasswd']));
                    $newpw = mysqli_real_escape_string($connection, base64_decode($params['newpasswd']));
                    if($userFunctions->CheckUserPassword($requestedUser, $oldpw)){
                        if($userFunctions->UpdateUserPassword($requestedUser, password_hash($newpw, PASSWORD_BCRYPT))){
                            $customResponse['status'] = true;
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(200);
                        }else{
                            $customResponse['errors'] = "An Error Occurred While Trying to Change The Password";
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(500);
                        }
                    }else{
                        $customResponse['errors'] = "Invalid Credentials";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(403);
                    }
                }else{
                    $customResponse['errors'] = "Permission Denied";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(403);
                }
            }else{
                $customResponse['errors'] = "Missing Parameters";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(400);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Reset User Password
 * Method -> PUT
 * Parameters -> userID
 */

$app->put('/user/{userID}/changepassword/reset', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $settings = new API_Settings();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if($userFunctions->isUserAdmin($userID)){
                $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
                if($userFunctions->UpdateUserPassword($requestedUser, password_hash($settings->defaultPassword, PASSWORD_BCRYPT))){
                    $customResponse['status'] = true;
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(200);
                }else{
                    $customResponse['errors'] = "An Error Occurred While Trying to Reset the Password";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(500);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Edit a Specific Summary
 * Method -> PUT
 * Parameters -> userID, summaryID, workspaceID, summaryNumber, date, contents, filesToAdopt, filesToRemove
 */

$app->put('/user/{userID}/summary/{summaryID}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    $workspaceFunctions = new WorkspaceFunctions();
    $summaryFunctions = new SummaryFunctions();
    $params = (array)$request->getParsedBody();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
            if($userID == $requestedUser || $userFunctions->isUserAdmin($userID)){
                $summaryID = mysqli_real_escape_string($connection, $args['summaryID']);
                if($summaryFunctions->SummaryExists($summaryID)){
                    if($summaryFunctions->CheckSummaryOwnership($requestedUser, $summaryID) || $userFunctions->isUserAdmin($userID)){
                        if($summaryFunctions->CheckSummaryOwnership($userID, $summaryID)){
                            $workspaceID = mysqli_real_escape_string($connection, $params['workspaceID']);
                            if($workspaceFunctions->WorkspaceExists($workspaceID)){
                                $summaryNumber = mysqli_real_escape_string($connection, $params['summaryNumber']);
                                $date = mysqli_real_escape_string($connection, base64_decode($params['date']));
                                if(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date)){
                                    $bodyText = mysqli_real_escape_string($connection, base64_decode($params['bodyText']));
                                    if($summaryFunctions->EditSummary(true, $requestedUser, $summaryNumber, $workspaceID, $date, $bodyText, $summaryID)){
                                        if(isset($params['filesToAdopt']) || isset($params['filesToRemove'])){

                                            if(isset($params['filesToAdopt'])){
                                                $files = base64_decode($params['filesToAdopt']);
                                                $filesToAdopt = json_decode($files);
    
                                                foreach ($filesToAdopt as $id) {
                                                    if(!mysqli_query($connection, "UPDATE attachmentMapping SET summaryID='$summaryID' WHERE id='$id'")){
                                                        $customResponse['errors'] = "ADPFI: " . mysqli_error($connection);
                                                        $response->getBody()->write(json_encode($customResponse));
                                                        return $response->withStatus(500);
                                                    }
                                                }
                                            }
    
                                            if(isset($params['filesToRemove'])){
                                                $files = base64_decode($params['filesToRemove']);
                                                $filesToRemove = json_decode($files);
    
                                                $getAttachmentsQuery = "SELECT * FROM attachmentMapping WHERE summaryID='$summaryID'";
                                                $getAttachments = mysqli_query($connection, $getAttachmentsQuery);
                                                if($getAttachments){
                                                    if(mysqli_num_rows($getAttachments) > 0){
                                                        while($row = mysqli_fetch_array($getAttachments, MYSQLI_ASSOC)){
                                                            $map[$row['filename']] = $row['path'];
                                                        }
    
                                                        foreach ($filesToRemove as $file) {
                                                            $fileToQuery = mysqli_real_escape_string($connection, $file);
                                                            $check = mysqli_query($connection, "DELETE FROM attachmentMapping WHERE filename='$fileToQuery' AND summaryID='$summaryID'");
                                                            if($check){
                                                                if(isset($map[$file])){
                                                                    if(!unlink(ROOT_FOLDER . "/" . $map[$file])){
                                                                        $customResponse['errors'] = "Error while trying to delete file " . $map[$file];
                                                                        $response->getBody()->write(json_encode($customResponse));
                                                                        return $response->withStatus(500);
                                                                    }
                                                                }else{
                                                                    $customResponse['errors'] = "Not set.";
                                                                    $response->getBody()->write(json_encode($customResponse));
                                                                    return $response->withStatus(500);
                                                                }
                                                            }else{
                                                                $customResponse['errors'] = "DELFI: " . mysqli_error($connection);
                                                                $response->getBody()->write(json_encode($customResponse));
                                                                return $response->withStatus(500);
                                                            }
                                                        }
                                                    }else{
                                                        $customResponse['errors'] = "No matches found.";
                                                        $response->getBody()->write(json_encode($customResponse));
                                                        return $response->withStatus(404);
                                                    }
                                                }else{
                                                    $customResponse['errors'] = "GETAT: " . mysqli_error($connection);
                                                    $response->getBody()->write(json_encode($customResponse));
                                                    return $response->withStatus(500);
                                                }
                                            }
                                        }
                                        $customResponse['status'] = true;
                                        $response->getBody()->write(json_encode($customResponse));
                                        return $response->withStatus(200);
                                    }else{
                                        $customResponse['errors'] = "An error occurred while trying to create the summary";
                                        $response->getBody()->write(json_encode($customResponse));
                                        return $response->withStatus(500);
                                    }
                                }else{
                                    $customResponse['errors'] = "Invalid Date Syntax";
                                    $response->getBody()->write(json_encode($customResponse));
                                    return $response->withStatus(400);
                                }
                            }else{
                                $customResponse['errors'] = "Workspace Not Found";
                                $response->getBody()->write(json_encode($customResponse));
                                return $response->withStatus(404);
                            }
                        }else{
                            $customResponse['errors'] = "Permission Denied";
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(403);
                        }
                    }else{
                        $customResponse['errors'] = "Permission Denied";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(403);
                    }
                }else{
                    $customResponse['errors'] = "Summary Not Found";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(404);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Delete User
 * Method -> DELETE
 * Parameters -> userID
 */

$app->delete('/user/{userID}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $userFunctions = new UserFunctions();
    $authTokens = new AuthTokens();
    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
            if($userFunctions->isUserAdmin($userID)){
                if($userID == $requestedUser){
                    $customResponse['errors'] = "You cannot delete yourself";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(406);
                }else{
                    if($userFunctions->UserExists($requestedUser)){
                        if($userFunctions->isUserDeletionProtected($requestedUser)){
                            $customResponse['errors'] = "User is Deletion Protected";
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(406);
                        }else{
                            if($userFunctions->DeleteUser($requestedUser)){
                                $customResponse['status'] = true;
                                $response->getBody()->write(json_encode($customResponse));
                                return $response->withStatus(200);
                            }else{
                                $customResponse['errors'] = "An Error Occurred While Trying to Delete the User";
                                $response->getBody()->write(json_encode($customResponse));
                                return $response->withStatus(500);
                            }
                        }
                    }else{
                        $customResponse['errors'] = "User Not Found";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(404);
                    }
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Delete a Summary
 * Method -> DELETE
 * Parameters -> userID, summaryID
 */

$app->delete('/user/{userID}/summary/{summaryID}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    $summaryFunctions = new SummaryFunctions();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
            if($userID == $requestedUser || $userFunctions->isUserAdmin($userID)){
                $summaryID = mysqli_real_escape_string($connection, $args['summaryID']);
                if($summaryFunctions->SummaryExists($summaryID)){
                    if($summaryFunctions->DeleteSummaries($summaryID)){
                        $customResponse['status'] = true;
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(200);
                    }else{
                        $customResponse['errors'] = "An Error Occurred While Trying to Delete the Summary";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(500);
                    }
                }else{
                    $customResponse['errors'] = "Summary Not Found";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(404);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }     
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/////////////////////////////////////////////
//              WORKSPACE
//      Workspace related endpoints
/////////////////////////////////////////////

/**
 * Workspace List
 * Method -> GET
 * Parameters -> (none)
 */

$app->get('/workspace', function(Request $request, Response $response, array $agrs){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $workspaceFunctions = new WorkspaceFunctions();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $list = $workspaceFunctions->GetWorkspaceList();
            if($list){
                $customResponse['status'] = true;
                $customResponse['contents'] = $list;
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(200);
            }else{
                $customResponse['errors'] = "An Error Occurred While Trying to Get the List of Workspaces";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(500);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Get Workspace By id
 * Method -> GET
 * Parameters -> workspaceID 
 */

$app->get('/workspace/{workspaceID}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $workspaceFunctions = new WorkspaceFunctions();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $workspaceID = mysqli_real_escape_string($connection, $args['workspaceID']);
            if($workspaceFunctions->WorkspaceExists($workspaceID)){
                $contents = $workspaceFunctions->GetWorkspace($workspaceID);
                if($contents){
                    $customResponse['status'] = true;
                    $customResponse['contents'] = $contents;
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(200);
                }else{
                    $customResponse['errors'] = "An Error Occurred While Trying to Fetch the Workspace Details";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(500);
                }
            }else{
                $customResponse['errors'] = "Workspace Not Found";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(404);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Create a New Workspace
 * Method -> POST
 * Parameters -> name, readMode, writeMode
 */

$app->post('/workspace', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $workspaceFunctions = new WorkspaceFunctions();
    $userFunctions = new UserFunctions();
    $params = (array)$request->getParsedBody();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if($userFunctions->isUserAdmin($userID)){
                $name = mysqli_real_escape_string($connection, $params['workspaceName']);
                $readMode = mysqli_real_escape_string($connection, $params['readMode']);
                $writeMode = mysqli_real_escape_string($connection, $params['writeMode']);
                $readMode = ($readMode == 1 || $readMode == "true" || $readMode == "True" ? true : false);
                $writeMode = ($writeMode == 1 || $writeMode == "true" || $writeMode == "True" ? true : false);

                if($workspaceFunctions->EditWorkspace($name, $readMode, $writeMode)){
                    $customResponse['status'] = true;
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(200);
                }else{
                    $customResponse['errors'] = "An Error Occurred While Trying to Create the Workspace";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(500);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Edit Workspace
 * Method -> PUT
 * Parameters -> workspaceID, name, readMode, writeMode
 */

$app->put('/workspace/{workspaceID}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    $workspaceFunctions = new WorkspaceFunctions();
    $params = (array)$request->getParsedBody();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if($userFunctions->isUserAdmin($userID)){
                $workspaceID = mysqli_real_escape_string($connection, $args['workspaceID']);
                if($workspaceFunctions->WorkspaceExists($workspaceID)){
                    $name = mysqli_real_escape_string($connection, $params['workspaceName']);
                    $readMode = mysqli_real_escape_string($connection, $params['readMode']);
                    $writeMode = mysqli_real_escape_string($connection, $params['writeMode']);
                    $readMode = ($readMode == 1 || $readMode == "true" || $readMode == "True" ? true : false);
                    $writeMode = ($writeMode == 1 || $writeMode == "true" || $writeMode == "True" ? true : false);
                    if($workspaceFunctions->EditWorkspace($name, $readMode, $writeMode, $workspaceID)){
                        $customResponse['status'] = true;
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(200);
                    }else{
                        $customResponse['errors'] = "An Error Occurred While Trying to Edit the Workspace";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(500);
                    }
                }else{
                    $customResponse['errors'] = "Workspace Not Found";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(404);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Delete a Workspace
 * Method -> DELETE
 * Parameters -> worksapceID
 */

$app->delete('workspace/{workspaceID}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $workspaceFunctions = new WorkspaceFunctions();
    $userFunctions = new UserFunctions();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if($userFunctions->isUserAdmin($userID)){
                $workspaceID = mysqli_real_escape_string($connection, $args['workspaceID']);
                if($workspaceFunctions->WorkspaceExists($workspaceID)){
                    if(isset($args['d'])){
                        $flushSummaries = ($args['d'] == "true" || $args['d'] == "True" ? true : false);
                    }else{
                        $flushSummaries = false;
                    }

                    if($workspaceFunctions->DeleteWorkspace($workspaceID, $flushSummaries)){
                        $customResponse['status'] = true;
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(200);
                    }else{
                        $customResponse['errors'] = "An Error Occurred While Trying To Delete the Workspace";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(500);
                    }
                }else{
                    $customResponse['errors'] = "Workspace Not Found";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(404);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

/**
 * Flush Summaries From Workspace
 * Method -> DELETE
 * Parameters -> workspaceID
 */

$app->delete('/workspace/{workspaceID}/flush', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    $workspaceFunctions = new WorkspaceFunctions();

    if($request->hasHeader('HTTP-X-API-KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $request->getHeaderLine('HTTP-X-API-KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if($userFunctions->isUserAdmin($userID)){
                $workspaceID = mysqli_real_escape_string($connection, $args['workspaceID']);
                if($workspaceFunctions->WorkspaceExists($workspaceID)){
                    if($workspaceFunctions->FlushWorkspace($workspaceID)){
                        $customResponse['status'] = true;
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(200);
                    }else{
                        $customResponse['errors'] = "An Error Occurred While Trying to Flush the Workspace";
                        $response->getBody()->write(json_encode($customResponse));
                        return $response->withStatus(500);
                    }
                }else{
                    $customResponse['errors'] = "Workspace Not Found";
                    $response->getBody()->write(json_encode($customResponse));
                    return $response->withStatus(404);
                }
            }else{
                $customResponse['errors'] = "Permission Denied";
                $response->getBody()->write(json_encode($customResponse));
                return $response->withStatus(403);
            }
        }else{
            $customResponse['errors'] = "Authentication Failed";
            $response->getBody()->write(json_encode($customResponse));
            return $response->withStatus(401);
        }
    }else{
        $customResponse['errors'] = "Authentication Failed";
        $response->getBody()->write(json_encode($customResponse));
        return $response->withStatus(401);
    }
});

$app->run();
?>