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
        $user = mysqli_real_escape_string($connection, $params['usrnm']);
        $password = mysqli_real_escape_string($connection, $params['psswd']);
        $query = "SELECT * FROM users WHERE user='$user' LIMIT 1";
        $run = mysqli_query($connection, $query);
        if($run){
            if(mysqli_num_rows($run) == 1){
                while($row = mysqli_fetch_array($run, MYSQLI_ASSOC)){
                    $userID = $row['id'];
                    $dbpassword = $row['password'];
                    $username = $row['user'];
                    $displayName = $row['displayName'];
                    $adminControl = ($row['adminControl']==1 ? true : false);
                }
                if(password_verify($password, $dbpassword)){
                    $customResponse['status'] = true;
                    $customResponse['AccessToken'] = $authTokens->GenerateAccessToken($userID);
                    $customResponse['userID'] = $userID;
                    $customResponse['username'] = $username;
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

    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
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

    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
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

    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
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

    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $classID = mysqli_real_escape_string($connection, $args['classID']);
            if($classFunctions->ClassExists(null, $classID)){
                $query = mysqli_query($connection, "SELECT id FROM users WHERE classID=$classID");
                if($query){
                    if(mysqli_num_rows($query) > 0){
                        $list = null;
                        while($row = mysqli_fetch_array($query, MYSQLI_ASSOC)){
                            $list[] = $row['id'];
                        }
                        $customResponse['status'] = true;
                        $customResponse['contents'] = $list;
                        $response->getBody()->write(json_encode($response));
                        return $response->withStatus(200);
                    }else{
                        $customResponse['status'] = true;
                        $customResponse['contents'] = null;
                        $response->getBody()->write(json_encode($response));
                        return $response->withStatus(200);
                    }
                }else{
                    $customResponse['errors'] = mysqli_error($connection);
                    $response->getBody()->write(json_encode($response));
                    return $response->withStatus(500);
                }
            }else{
                $customResponse['errors'] = "Class Not Found";
                $response->getBody()->write(json_encode($response));
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

    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if($userFunctions->isUserAdmin($userID)){
                if(isset($params['name'])){
                    $className = mysqli_real_escape_string($connection, $params['name']);
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

    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if($userFunctions->isUserAdmin($userID)){
                if(isset($params['name']) && isset($params['classID'])){
                    $className = mysqli_real_escape_string($connection, $params['name']);
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
 * Parameters -> id
 */

$app->delete('/class/{id}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $classFunctions = new ClassFunctions();
    $userFunctions = new UserFunctions();
    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
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

    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
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
    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
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
    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
            if($userFunctions->isUserAdmin($userID) || $userID==$requestedUser){
                if($userFunctions->UserExists($requestedUser)){
                    $workspaceID = mysqli_real_escape_string($connection, $args['workspaceID']);
                    if($workspaceFunctions->WorkspaceExists($workspaceID)){
                        $list = $summaryFunctions->GetSummariesList($requestedUser, $workspaceID);
                        if($list){
                            $customResponse['stauts'] = true;
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
 * Parameters -> userID, workspaceID, summaryID
 */

$app->get('/user/{userID}/workspace/{workspaceID}/summary/{summaryID}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $workspaceFunctions = new WorkspaceFunctions();
    $userFunctions = new UserFunctions();
    $filesFunctions = new FilesFunctions();
    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
            if($userFunctions->isUserAdmin($userID) || $userID==$requestedUser){
                if($userFunctions->UserExists($requestedUser)){
                    $workspaceID = mysqli_real_escape_string($connection, $args['workspaceID']);
                    if($workspaceFunctions->WorkspaceExists($workspaceID)){
                        $summaryID = mysqli_real_escape_string($connection, $args['summaryID']);
                        $query = mysqli_query($connection, "SELECT * FROM summaries WHERE userid=$requestedUser AND workspace=$workspaceID AND summaryNumber=$summaryID LIMIT 1");
                        if($query){
                            if(mysqli_num_rows($query) == 1){
                                $contents = "";
                                while($row = mysqli_fetch_array($query, MYSQLI_ASSOC)){
                                    $contents['dbRow'] = $row['id'];
                                    $contents['userID'] = $row['userid'];
                                    $contents['date'] = $row['date'];
                                    $contents['summaryID'] = $row['summaryNumber'];
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
 * Get a Summary's File List
 * Method -> GET
 * Parameters -> userID, workspaceID, summaryID
 */

$app->get('/user/{userID}/workspace/{workspaceID}/summary/{summaryID}/files', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    $workspaceFunctions = new WorkspaceFunctions();
    $summaryFunctions = new SummaryFunctions();
    $filesFunctions = new FilesFunctions();

    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
            if($userFunctions->isUserAdmin($userID) || $userID==$requestedUser){
                if($userFunctions->UserExists($requestedUser)){
                    $workspaceID = mysqli_real_escape_string($connection, $args['workspaceID']);
                    if($workspaceFunctions->WorkspaceExists($workspaceID)){
                        $summaryID = mysqli_real_escape_string($connection, $args['summaryID']);
                        $dbRowID = $summaryFunctions->FindSummary($requestedUser, $summaryID, $workspaceID);
                        if($dbRowID){
                            $files = $filesFunctions->GetFilesList($dbRowID);
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
                            $customResponse['errors'] = "Summary Not Found";
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(404);
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
 * Get a Specific File From a Summary
 * Method -> GET
 * Parameters -> userID, workspaceID, summaryID, file
 */

$app->get('/user/{userID}/workspace/{workspaceID}/summary/{summaryID}/files/{file}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    $userFunctions = new UserFunctions();
    $workspaceFunctions = new WorkspaceFunctions();
    $summaryFunctions = new SummaryFunctions();
    $filesFunctions = new FilesFunctions();
    $API_Settings = new API_Settings();

    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
            if($userFunctions->isUserAdmin($userID) || $userID == $requestedUser){
                if($userFunctions->UserExists($requestedUser)){
                    $workspaceID = mysqli_real_escape_string($connection, $args['workspaceID']);
                    if($workspaceFunctions->WorkspaceExists($workspaceID)){
                        $summaryID = mysqli_real_escape_string($connection, $args['summaryID']);
                        $dbRowID = $summaryFunctions->FindSummary($requestedUser, $summaryID, $workspaceID);
                        if($dbRowID){
                            $fileName = mysqli_real_escape_string($connection, $args['file']);
                            if($filesFunctions->FileExists($fileName, $dbRowID)){
                                $path = $filesFunctions->GetPath($fileName, $dbRowID);
                                $filePath = $API_Settings->filesPath + $path;
                                list(, , $actualName) = explode("/", $path);

                                if(file_exists(ROOT_FOLDER . "/" . $filePath)){
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
                            $customResponse['errors'] = "Summary Not Found";
                            $response->getBody()->write(json_encode($customResponse));
                            return $response->withStatus(404);
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

    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
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

    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            if(isset($params['summaryID']) && isset($params['date']) && isset($params['bodyText'])){
                $requestedUser = mysqli_real_escape_string($connection, $args['userID']);
                if($userID == $requestedUser){
                    $workspaceID = mysqli_real_escape_string($connection, $args['workspaceID']);
                    if($workspaceFunctions->WorkspaceExists($workspaceID)){
                        $summaryID = mysqli_real_escape_string($connection, $params['summaryID']);
                        if(!$summaryFunctions->FindSummary($requestedUser, $summaryID, $workspaceID)){
                            $date = mysqli_real_escape_string($connection, base64_decode($params['date']));
                            if(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date)){
                                $bodyText = mysqli_real_escape_string($connection, base64_decode($params['bodyText']));
                                $rowID = $summaryFunctions->EditSummary(false, $requestedUser, $summaryID, $workspaceID, $date, $bodyText);
                                if($rowID){
                                    $customResponse['status'] = true;
                                    $customResponse['rowID'] = $rowID;
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

    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
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

    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
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

    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
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
 * Parameters -> userID, workspaceID, summaryID, date, contents, filesToAdopt, filesToRemove
 */

$app->put('/user/{userID}/workspace/{workspaceID}/summary/{summaryID}', function(Request $request, Response $response, array $args){
    global $customResponse;
    $connection = databaseConnect();
    $authTokens = new AuthTokens();
    if($response->hasHeader('HTTP_X_API_KEY')){
        $AccessToken = mysqli_real_escape_string($connection, $response->getHeaderLine('HTTP_X_API_KEY'));
        $userID = $authTokens->isTokenValid($AccessToken);
        if($userID){
            
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
 * Parameters -> id
 */

$app->delete('/user/{id}', function(Request $request, Response $response, array $agrs){
    global $customResponse;

    $response->getBody()->write(json_encode($customResponse));
    return $customResponse;
});

/**
 * Delete a Summary
 * Method -> DELETE
 * Parameters -> id, workspaceID, summaryID
 */

$app->delete('/user/{id}/workspace/{workspaceID}/summary/{summaryID}', function(Request $request, Response $response, array $agrs){
    global $customResponse;

    $response->getBody()->write(json_encode($customResponse));
    return $customResponse;
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

    $response->getBody()->write(json_encode($customResponse));
    return $customResponse;
});

/**
 * Get Workspace By id
 * Method -> GET
 * Parameters -> id 
 */

$app->get('/workspace/{workspaceID}', function(Request $request, Response $response, array $agrs){
    global $customResponse;

    $response->getBody()->write(json_encode($customResponse));
    return $customResponse;
});

/**
 * Create a New Workspace
 * Method -> POST
 * Parameters -> name, readMode, writeMode
 */

$app->post('/workspace', function(Request $request, Response $response, array $args){
    global $customResponse;

    $response->getBody()->write(json_encode($customResponse));
    return $customResponse;
});

/**
 * Edit Workspace
 * Method -> PUT
 * Parameters -> workspaceID, name, readMode, writeMode
 */

$app->put('/workspace/{workspaceID}', function(Request $request, Response $response, array $agrs){
    global $customResponse;

    $response->getBody()->write(json_encode($customResponse));
    return $customResponse;
});

/**
 * Delete a Workspace
 * Method -> DELETE
 * Parameters -> worksapceID
 */

$app->delete('workspace/{workspaceID}', function(Request $request, Response $response, array $agrs){
    global $customResponse;

    $response->getBody()->write(json_encode($customResponse));
    return $customResponse;
});

/**
 * Flush Summaries From Workspace
 * Method -> DELETE
 * Parameters -> workspaceID
 */

$app->delete('/workspace/{workspaceID}/flush', function(Request $request, Response $response, array $agrs){
    global $customResponse;

    $response->getBody()->write(json_encode($customResponse));
    return $customResponse;
});

$app->run();
?>