<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
require_once("./functions.php");

$response['status'] = false;
$response['errors'] = "";

try{
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = explode( '/', $uri );
    if(!CheckIfSecure()){
        header("HTTP/1.0 Bad Request");
        $response['errors'] = "Insecure Connection! The connection must be done over HTTPS (TLS).";
        echo json_encode($response);
        exit();
    }
    
    $apiDIR = array_search("api", $uri);
    if($apiDIR === false){
        header("HTTP/1.0 400 Bad Request");
        $response["errors"] = "Malformed request";
    }else{
    
        $connection = databaseConnect();
        $settings = new API_Settings();
        $authTokens = new AuthTokens();
        $classFunctions = new ClassFunctions();
        $userFunctions = new UserFunctions();
        $summaryFunctions = new SummaryFunctions();
        $workspaceFunctions = new WorkspaceFunctions();
    
        $opertation = $apiDIR+2;
        switch($uri[$opertation]){
            case "login":
                if($_SERVER['REQUEST_METHOD'] == "POST"){
                    if(isset($_POST['usrnm']) || isset($_POST['psswd'])){
                        $user = mysqli_real_escape_string($connection, $_POST['usrnm']);
                        $pass = mysqli_real_escape_string($connection, $_POST['psswd']);
                    
                        $query = "SELECT * FROM users WHERE user='$user'";
                        $result = mysqli_query($connection, $query);
                        if($result){
                            if(mysqli_num_rows($result) != 1){
                                $response['status'] = false;
                            }else{
                                while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
                                    $dbpass = $row['password'];
                                    $userID = $row['id'];
                                    $loginName = $row['user'];
                                    $displayName = $row['displayName'];
                                    $adminControl = ($row['adminControl']==1 ? true : false);
                                }
                                if(password_verify($pass, $dbpass)){
                                    header("HTTP/1.0 200 OK");
                                    $response['status'] = true;
                                    $response['AccessToken'] = $authTokens->GenerateAccessToken($userID);
                                    $response['userID'] = $userID;
                                    $response['username'] = $loginName;
                                    $response['displayName'] = $displayName;
                                    $response['adminControl'] = $adminControl;
                                }else{
                                    header("HTTP/1.0 401 Unauthorized");
                                    $response['status'] = false;
                                }
                            }
                        }else{
                            header("HTTP/1.0 500 Internal Server Error");
                            $response['status'] = false;
                            $response['errors'] = "" . mysqli_error($connection) . "";
                        }
                    }else{
                        header("HTTP/1.0 400 Bad Request");
                        $response['status'] = false;
                        $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                    }
                }else{
                    header("HTTP/1.0 405 Method Not Allowed");
                    $response["status"] = false;
                    $response["errors"] = "Method Not Allowed" . $_SERVER["REQUEST_METHOD"];
                }
            break;
            
            case "logout":
                if($_SERVER["REQUEST_METHOD"] == "GET"){
                    if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                        header("HTTP/1.0 Bad Request");
                        $response["errors"] = "Malformed request";
                    }else{
                        if(isset($_SERVER['HTTP_X_API_KEY'])){
                            $AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                        
                            if($authTokens->isTokenValid($AccessToken)){
                                if($authTokens->DeleteToken($AccessToken)){
                                    header("HTTP/1.0 200 OK");
                                    $response['status'] = true;
                                    $response['errors'] = "";
                                }else{
                                    header("HTTP/1.0 500 Internal Server Error");
                                    $response['status'] = false;
                                    $response['errors'] = "An Error Occurred While Trying to Delete The Specified Token";
                                }
                            }else{
                                header("HTTP/1.0 401 Unauthorized");
                                $response['status'] = false;
                                $response['errors'] = "Invalid Token";
                            }
                        }else{
                            header("HTTP/1.0 400 Bad Request");
                            $response['status'] = false;
                            $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                        }
                    }
                }else{
                    header("HTTP/1.0 405 Method Not Allowed");
                    $response["status"] = false;
                    $response["errors"] = "Method Not Allowed";
                }
            break;
            
            case "class":
                switch($_SERVER['REQUEST_METHOD']){
                    
                    case "GET":
                        if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                            if(isset($_SERVER['HTTP_X_API_KEY'])){
                                $AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                    
                                if($authTokens->isTokenValid($AccessToken)){
                                    $list = $classFunctions->GetClassList();
                                    if($list){
                                        header("HTTP/1.0 200 OK");
                                        $response['status'] = true;
                                        $response['errors'] = "";
                                        $response['contents'] = $list;
                                    }else{
                                        header("HTTP/1.0 500 Internal Server Error");
                                        $response['status'] = false;
                                        $response['errors'] = "An Error Occurred While Trying to Retrieve The List Of Classes";
                                    }
                                }else{
                                    header("HTTP/1.0 401 Unauthorized");
                                    $response['status'] = false;
                                    $response['errors'] = "Invalid Token";
                                }
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response["status"] = false;
                                $response["errors"] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                            }
                        }else{
                            if(is_numeric($uri[$opertation+1])){
                                //TODO: get class by id
                                header("HTPP/1.0 501 Not Implemented");
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response["errors"] = "Malformed request";
                            }
                        }
                    break;
    
                    case "POST":
                        if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                            if((isset($_SERVER['HTTP_X_API_KEY'])) || (isset($_POST['name']))){
                                $AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                    
                                $isValid = $authTokens->isTokenValid($AccessToken);
                                if($isValid){
                                    $name = base64_decode($_POST['name']);
                                    $name = mysqli_real_escape_string($connection, $name);
                                    if($classFuntions->EditClass($name)){
                                        header("HTTP/1.0 200 OK");
                                        $response['status'] = true;
                                        $response['errors'] = "";
                                    }else{
                                        header("HTTP/1.0 500 Internal Server Error");
                                        $response['status'] = false;
                                        $response['errors'] = "An Error Occurred While Trying To Add a Class";
                                    }
                                }else{
                                    header("HTTP/1.0 401 Unauthorized");
                                    $response['status'] = false;
                                    $response['errors'] = "Invalid Token";
                                }
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response['status'] = false;
                                $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                            }
                        }else{
                            header("HTTP/1.0 400 Bad Request");
                            $response["errors"] = "Malformed request";
                        }
                    break;
    
                    case "PUT":
                        if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                            header("HTTP/1.0 400 Bad Request");
                            $response["errors"] = "Malformed request";
                        }else{
                            if(is_numeric($uri[$opertation+1])){
                                if((isset($_SERVER['HTTP_X_API_KEY'])) || (isset($_POST['name']) || (isset($_POST['classID'])))){
                                    $AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                        
                                    $isValid = $authTokens->isTokenValid($AccessToken);
                                    if($isValid){
                                        $name = base64_decode($_POST['name']);
                                        $name = mysqli_real_escape_string($connection, $name);
                                        $classID = mysqli_real_escape_string($connection, $_POST['classID']);
                                        if($classFuntions->EditClass($name, $classID)){
                                            header("HTTP/1.0 200 OK");
                                            $response['status'] = true;
                                            $response['errors'] = "";
                                        }else{
                                            header("HTTP/1.0 500 Internal Server Error");
                                            $response['status'] = false;
                                            $response['errors'] = "An Error Occurred While Trying To Edit a Class";
                                        }
                                    }else{
                                        header("HTTP/1.0 401 Unauthorized");
                                        $response['status'] = false;
                                        $response['errors'] = "Invalid Token";
                                    }
                                }else{
                                    header("HTTP/1.0 400 Bad Request");
                                    $response['status'] = false;
                                    $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                                }
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response["errors"] = "Malformed request";
                            }
                        }
                    break;
    
                    case "DELETE":
                        if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                            header("HTTP/1.0 400 Bad Request");
                            $response["errors"] = "Malformed request";
                        }else{
                            if(is_numeric($uri[$opertation+1])){
                                if((isset($_SERVER['HTTP_X_API_KEY'])) || (isset($_POST['classID']))){
                                    $AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                        
                                    $isValid = $authTokens->isTokenValid($AccessToken);
                                    if($isValid){
                                        if($userFunctions->isUserAdmin($isValid)){
                                            $classID = mysqli_real_escape_string($connection, $_POST['classID']);
                                            if($classID == 0){
                                                header("HTTP/1.0 403 Forbidden");
                                                $response['status'] = false;
                                                $response['errors'] = "Class 0 is protected at a code level.";
                                            }else{
                                                if($classFunctions->DeleteClass($classID, $settings->resetUsersOnDelete)){
                                                    header("HTTP/1.0 200 OK");
                                                    $response['status'] = true;
                                                    $response['errors'] = "";
                                                }else{
                                                    header("HTTP/1.0 500 Internal Server Error");
                                                    $response['status'] = false;
                                                    $response['errors'] = "An Error Occurred While Trying To Delete The Class.";
                                                }
                                            }
                                        }else{
                                            header("HTTP/1.0 403 Forbidden");
                                            $response['status'] = false;
                                            $response['errors'] = "Permission Denied";
                                        }
                                    }else{
                                        header("HTTP/1.0 401 Unauthorized");;
                                        $response['status'] = false;
                                        $response['errors'] = "Invalid Token";
                                    }
                                }else{
                                    header("HTTP/1.0 400 Bad Request");
                                    $response['status'] = false;
                                    $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                                }
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response["errors"] = "Malformed request";
                            }
                        }
                    break;
    
                    default:
                        header("HTTP/1.0 405 Method Not Allowed");
                        $response["errors"] = "Malformed request";
                }
            break;
    
            case "user":
                switch($_SERVER['REQUEST_METHOD']){
                    case "GET":
                        if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                            if(isset($_SERVER['HTTP_X_API_KEY'])){
                                $AcessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                    
                                $isValid = $authToken->isTokenValid($AcessToken);
                    
                                if($isValid){
                                    if($userFunctions->isUserAdmin($isValid)){
                                        $list = $userFunctions->GetUserList();
                                        if($list){
                                            header("HTTP/1.0 200 OK");
                                            $response['status'] = true;
                                            $response['errors'] = "";
                                            $response['contents'] = $list;
                                        }else{
                                            header("HTTP/1.0 500 Internal Server Error");
                                            $response['status'] = false;
                                            $response['errors'] = "An Error Occurred While Trying To Retrive The List";
                                        }
                                    }else{
                                        header("HTTP/1.0 403 Forbidden");
                                        $response['status'] = false;
                                        $response['errors'] = "Permission Denied.";
                                    }
                                }else{
                                    header("HTTP/1.0 401 Unauthorized");
                                    $response['status'] = false;
                                    $response['errors'] = "Invalid Token";
                                }
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response['status'] = false;
                                $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                            }
                        }else{
                            if(is_numeric($uri[$opertation+1])){
                                //TODO: get user info by id
                                header("HTTP/1.0 501 Not Implemented");
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response["errors"] = "Malformed request";
                            }
                        }
                    break;
    
                    case "POST":
                        if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                            if((isset($_SERVER['HTTP_X_API_KEY'])) || (isset($_POST['username'])) || (!isset($_POST['displayName'])) || (!isset($_POST['classID'])) || (!isset($_POST['admin'])) || (!isset($_POST['deletionProtection']))){
                                $AcessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                    
                                $isValid = $authTokens->isTokenValid($AcessToken);
                    
                                if($isValid){
                                    $username = base64_decode($_POST['username']);
                                    $username = mysqli_real_escape_string($connection, $username);
                                    $displayName = base64_decode($_POST['displayName']);
                                    $displayName = mysqli_real_escape_string($connection, $displayName);
                                    $classID = base64_decode($_POST['classID']);
                                    $classID = mysqli_real_escape_string($connection, $classID);
                                    $isAdmin = mysqli_real_escape_string($connection, $_POST['admin']);
                                    $isAdmin = (($isAdmin == "true" || $isAdmin == "True" || $isAdmin === true) ? true : false);
                                    $isDeletionProtected = mysqli_real_escape_string($connection, $_POST['deletionProtection']);
                                    $isDeletionProtected = (($isDeletionProtected == "true" || $isDeletionProtected == "True" || $isDeletionProtected === true) ? true : false);
                    
                                    if($UserFunctions->EditUser($username, $displayName, $classID, $isAdmin, $isDeletionProtected, null)){
                                        header("HTTP/1.0 200 OK");
                                        $response['status'] = true;
                                        $response['errors'] = "";
                                    }else{
                                        header("HTTP/1.0 500 Internal Server Error");
                                        $response['status'] = false;
                                        $response['errors'] = "An Error Occurred While Trying to Create the User.";
                                    }
                                }else{
                                    header("HTTP/1.0 401 Unauthorized");
                                    $response['status'] = false;
                                    $response['errors'] = "Invalid Token";
                                }
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response['status'] = false;
                                $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                            }
                        }else{
                            header("HTTP/1.0 Bad Request");
                            $response["errors"] = "Malformed request";
                        }
                    break;
    
                    case "PUT":
                        if(is_numeric($uri[$opertation+1]) && $uri[$opertation+2] === "changepassword"){
                            // TODO: Change password
                        }else if(is_numeric($uri[$opertation+1])){
                            if((isset($_SERVER['HTTP_X_API_KEY'])) || (isset($_POST['userID'])) || (isset($_POST['username'])) || (!isset($_POST['displayName'])) || (!isset($_POST['classID'])) || (!isset($_POST['admin'])) || (!isset($_POST['deletionProtection']))){
                                $AcessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                    
                                $isValid = $authTokens->isTokenValid($AcessToken);
                    
                                if($isValid){
                                    $username = base64_decode($_POST['username']);
                                    $username = mysqli_real_escape_string($connection, $username);
                                    $displayName = base64_decode($_POST['displayName']);
                                    $displayName = mysqli_real_escape_string($connection, $displayName);
                                    $classID = base64_decode($_POST['classID']);
                                    $classID = mysqli_real_escape_string($connection, $classID);
                                    $isAdmin = mysqli_real_escape_string($connection, $_POST['admin']);
                                    $isAdmin = (($isAdmin == "true" || $isAdmin == "True" || $isAdmin === true) ? true : false);
                                    $isDeletionProtected = mysqli_real_escape_string($connection, $_POST['deletionProtection']);
                                    $isDeletionProtected = (($isDeletionProtected == "true" || $isDeletionProtected == "True" || $isDeletionProtected === true) ? true : false);
                                    $userID = mysqli_real_escape_string($connection, $_POST['userID']);
                    
                                    if($UserFunctions->EditUser($username, $displayName, $classID, $isAdmin, $isDeletionProtected, $userID)){
                                        header("HTTP/1.0 200 OK");
                                        $response['status'] = true;
                                        $response['errors'] = "";
                                    }else{
                                        header("HTTP/1.0 500 Internal Server Error");
                                        $response['status'] = false;
                                        $response['errors'] = "An Error Occurred While Trying to Edit the User.";
                                    }
                                }else{
                                    header("HTTP/1.0 401 Unauthorized");
                                    $response['status'] = false;
                                    $response['errors'] = "Invalid Token";
                                }
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response['status'] = false;
                                $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                            }
                        }else{
                            header("HTTP/1.0 400 Bad Request");
                            $response["errors"] = "Malformed request";
                        }
                    break;
    
                    case "DELETE":
                        if(is_numeric($uri[$opertation+1])){
                            if((isset($_SERVER['HTTP_X_API_KEY'])) || (isset($_POST['userID']))){
			
                                $AcessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                    
                                $isValid = $authTokens->isTokenValid($AcessToken);
                                if($isValid){
                                    if($userFunctions->isUserAdmin($isValid)){
                                        $userID = mysqli_real_escape_string($connection, $_POST['userID']);
                                        if($userFunctions->isUserDeletionProtected($userID)){
                                            header("HTTP/1.0 403 Forbidden");
                                            $response['status'] = false;
                                            $response['errors'] = "User Protected Against Accidental Deletion.";
                                        }else{
                                            if($userID != $isValid){
                                                if($userFunctions->DeleteUser($userID)){
                                                    header("HTTP/1.0 200 OK");
                                                    $response['status'] = true;
                                                    $response['errors'] = "";
                                                }else{
                                                    header("HTTP/1.0 500 Internal Server Error");
                                                    $response['status'] = false;
                                                    $response['errors'] = "An Error Occurred While Trying to Delete the Specified User.";
                                                }
                                            }else{
                                                header("HTTP/1.0 403 Forbidden");
                                                $response['status'] = false;
                                                $response['errors'] = "You cannot delete yourself.";
                                            }
                                        }
                                    }else{
                                        header("HTTP/1.0 403 Forbidden");
                                        $response['status'] = false;
                                        $response['errors'] = "Permission Denied";
                                    }
                                }else{
                                    header("HTTP/1.0 401 Unauthorized");
                                    $response['status'] = false;
                                    $response['errors'] = "Invalid Token";
                                }
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response['status'] = false;
                                $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                            }
                        }else{
                            header("HTTP/1.0 400 Bad Request");
                            $response["errors"] = "Malformed request";
                        }
                    break;
    
                    default:
                        header("HTTP/1.0 405 Method Not Allowed");
                        $response["errors"] = "Malformed request";
                }
            break;
    
            case "summary":
                switch($_SERVER['REQUEST_METHOD']){
                    
                    case "GET":
                        if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                            if(isset($_SERVER['HTTP_X_API_KEY']) && isset($_GET['userid']) && isset($_GET['workspace'])){
                                $AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                    
                                $isValid = $authTokens->isTokenValid($AccessToken);
                    
                                if($isValid){
                                    $userID = mysqli_real_escape_string($connection, $_GET['userid']);
                    
                                    if($userFunctions->isUserAdmin($isValid) || $userID == $isValid){
                    
                                        $workspace = mysqli_real_escape_string($connection, $_GET['workspace']);
                                        $workspace = ($workspace == 0 || $workspace == null ? null : $workspace);
                    
                                        $list = $summaryFunctions->GetSummariesList($userID, $workspace);
                    
                                        if($list != false || $list == null){
                                            header("HTTP/1.0 200 OK");
                                            $response['status'] = true;
                                            $response['errors'] = "";
                                            $response['contents'] = $list;
                                        }else{
                                            header("HTTP/1.0 500 Internal Server Error");
                                            $response['status'] = false;
                                            $response['errors'] = "An Error Occurred While Trying To Retrieve Summary Information";
                                        }
                                    }else{
                                        header("HTTP/1.0 403 Forbidden");
                                        $response['status'] = false;
                                        $response['errors'] = "Permission Denied.";
                                    }
                                }else{
                                    header("HTTP/1.0 401 Unauthorized");
                                    $response['status'] = false;
                                    $response['errors'] = "Invalid Token";
                                }
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response['status'] = false;
                                $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                            }
                        }else{
                            if(is_numeric($uri[$opertation+1])){
                                // summary
                                header("HTTP/1.0 501 Not Implemented");
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response["errors"] = "Malformed request";
                            }
                        }
                    break;
    
                    case "POST":
                        if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                            if(isset($_SERVER['HTTP_X_API_KEY']) && isset($_POST['userID']) && isset($_POST['summaryID']) && isset($_POST['workspaceID']) && isset($_POST['date']) && isset($_POST['contents']) && isset($_POST['dbrowID'])){
                                $AcessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                    
                                $isValid = $authTokens->isTokenValid($AcessToken);
                                if($isValid){
                                    $userID = mysqli_real_escape_string($connection, $_POST['userID']);
                                    if($userFunctions->isUserAdmin($isValid) || $userID==$isValid){
                    
                                        $summaryID = mysqli_real_escape_string($connection, $_POST['summaryID']);
                                        $workspaceID = mysqli_real_escape_string($connection, $_POST['workspaceID']);
                                        $date = base64_decode($_POST['date']);
                                        $date = mysqli_real_escape_string($connection, $date);
                                        $contents = base64_decode($_POST['contents']);
                                        $contents = mysqli_real_escape_string($connection, $contents);
                                        $dbrowID = mysqli_real_escape_string($connection, $_POST['dbrowID']);
                    
                                        $rowID = $summaryFunctions->EditSummary(false, $userID, $summaryID, $workspaceID, $date, $contents, $dbrowID);
                    
                                        if($rowID){
                    
                                            $response['status'] = true;
                                            $response['errors'] = "";
                    
                                            if(isset($_POST['filesToAdopt'])){
                                                $files = base64_decode($_POST['filesToAdopt']);
                                                $filesToAdopt = json_decode($files);
                    
                                                foreach ($filesToAdopt as $id) {
                                                    if(!$summaryFunctions->AdoptFile($id, $rowID)){
                                                        header("HTTP/1.0 500 Internal Server Error");
                                                        $response['status'] = false;
                                                        $response['errors'] = "Failed to Adopt File";
                                                    }
                                                }
                                            }
                    
                                            if($dbrowID != null){
                                                if(isset($_POST['filesToRemove'])){
                                                    $files = base64_decode($_POST['filesToRemove']);
                                                    $filesToRemove = json_decode($files);
                    
                                                    foreach($filesToRemove as $file){
                                                        if(!$summaryFunctions->DeleteFile($rowID, $file)){
                                                            header("HTTP/1.0 500 Internal Server Error");
                                                            $response['status'] = false;
                                                            $response['errors'] = "Failed to Delete File";
                                                        }
                                                    }
                                                }
                                            }
                                        }else{
                                            header("HTTP/1.0 500 Internal Server Error");
                                            $response['status'] = false;
                                            $response['errors'] = "Error While Trying To Save Summary.";
                                        }
                                    }else{
                                        header("HTTP/1.0 403 Forbidden");
                                        $response['status'] = false;
                                        $response['errors'] = "Permission Denied";
                                    }
                                }else{
                                    header("HTTP/1.0 401 Unauthorized");
                                    $response['status'] = false;
                                    $response['errors'] = "Invalid Token";
                                }
                            }else{
                                header("HTTP/1.0 500 Internal Server Error");
                                $response['status'] = false;
                                $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                            }
                        }else{
                            header("HTTP/1.0 400 Bad Request");
                            $response["errors"] = "Malformed request";
                        }
                    break;
    
                    case "PUT":
                        if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                            header("HTTP/1.0 400 Bad Request");
                            $response["errors"] = "Malformed request";
                        }else{
                            if(is_numeric($uri[$opertation+1])){
                                if(isset($_SERVER['HTTP_X_API_KEY']) && isset($_POST['userID']) && isset($_POST['summaryID']) && isset($_POST['workspaceID']) && isset($_POST['date']) && isset($_POST['contents'])){
                                    $AcessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                        
                                    $isValid = $authTokens->isTokenValid($AcessToken);
                                    if($isValid){
                                        $userID = mysqli_real_escape_string($connection, $_POST['userID']);
                                        if($userFunctions->isUserAdmin($isValid) || $userID==$isValid){
                        
                                            $summaryID = mysqli_real_escape_string($connection, $_POST['summaryID']);
                                            $workspaceID = mysqli_real_escape_string($connection, $_POST['workspaceID']);
                                            $date = base64_decode($_POST['date']);
                                            $date = mysqli_real_escape_string($connection, $date);
                                            $contents = base64_decode($_POST['contents']);
                                            $contents = mysqli_real_escape_string($connection, $contents);

                                            $rowID = $summaryFunctions->EditSummary(true, $userID, $summaryID, $workspaceID, $date, $contents, null);
                        
                                            if($rowID){
                        
                                                $response['status'] = true;
                                                $response['errors'] = "";
                        
                                                if(isset($_POST['filesToAdopt'])){
                                                    $files = base64_decode($_POST['filesToAdopt']);
                                                    $filesToAdopt = json_decode($files);
                        
                                                    foreach ($filesToAdopt as $id) {
                                                        if(!$summaryFunctions->AdoptFile($id, $rowID)){
                                                            header("HTTP/1.0 500 Internal Server Error");
                                                            $response['status'] = false;
                                                            $response['errors'] = "Failed to Adopt File";
                                                        }
                                                    }
                                                }
                        
                                                if($dbrowID != null){
                                                    if(isset($_POST['filesToRemove'])){
                                                        $files = base64_decode($_POST['filesToRemove']);
                                                        $filesToRemove = json_decode($files);
                        
                                                        foreach($filesToRemove as $file){
                                                            if(!$summaryFunctions->DeleteFile($rowID, $file)){
                                                                header("HTTP/1.0 500 Internal Server Error");
                                                                $response['status'] = false;
                                                                $response['errors'] = "Failed to Delete File";
                                                            }
                                                        }
                                                    }
                                                }
                                            }else{
                                                header("HTTP/1.0 500 Internal Server Error");
                                                $response['status'] = false;
                                                $response['errors'] = "Error While Trying To Save Summary.";
                                            }
                                        }else{
                                            header("HTTP/1.0 403 Forbidden");
                                            $response['status'] = false;
                                            $response['errors'] = "Permission Denied";
                                        }
                                    }else{
                                        header("HTTP/1.0 401 Unauthorized");
                                        $response['status'] = false;
                                        $response['errors'] = "Invalid Token";
                                    }
                                }else{
                                    header("HTTP/1.0 500 Internal Server Error");
                                    $response['status'] = false;
                                    $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                                }
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response["errors"] = "Malformed request";
                            }
                        }
                    break;
    
                    case "DELETE":
                        if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                            header("HTTP/1.0 400 Bad Request");
                            $response["errors"] = "Malformed request";
                        }else{
                            if(is_numeric($uri[$opertation+1])){
                                if(isset($_SERVER['HTTP_X_API_KEY']) && isset($_POST['userID']) && isset($_POST['workspaceID']) && isset($_POST['summaryID'])){
                                    $AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                        
                                    $isValid = $authTokens->isTokenValid($AccessToken);
                        
                                    if($isValid){
                                        if($userFunctions->isUserAdmin($isValid)){
                                            $userID = mysqli_real_escape_string($connection, $_POST['userID']);
                                            $summaryID = mysqli_real_escape_string($connection, $_POST['summaryID']);
                                            $workspace = mysqli_real_escape_string($connection, $_POST['workspaceID']);
                        
                                            if($summaryFunctions->DeleteSummaries($summaryFunctions->FindSummary($userID, $summaryID, $workspace))){
                                                header("HTTP/1.0 200 OK");
                                                $response['status'] = true;
                                                $response['errors'] = "";
                                            }else{
                                                header("HTTP/1.0 500 Internal Server Error");
                                                $response['status'] = false;
                                                $response['errors'] = "An Error Occurred While Trying To Delete The Summary.";
                                            }
                                        }else{
                                            header("HTTP/1.0 403 Forbidden");
                                            $response['status'] = false;
                                            $response['errors'] = "Permission Denied.";
                                        }
                                    }else{
                                        header("HTTP/1.0 401 Unauthorized");
                                        $response['status'] = false;
                                        $response['errors'] = "Invalid Key";
                                    }
                                }else{
                                    header("HTTP/1.0 400 Bad Request");
                                    $response['status'] = false;
                                    $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                                }
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response["errors"] = "Malformed request";
                            }
                        }
                    break;
    
                    default:
                        header("HTTP/1.0 405 Method Not Allowed");
                        $response["errors"] = "Malformed request";
                }
            break;

            case "file":
                switch($_SERVER['REQUEST_METHOD']){
                    case "GET":
                        if(isset($_SERVER['HTTP_X_API_KEY']) || isset($_POST['FILE'])){
                            $AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                
                            $isValid = $authTokens->isTokenValid($AccessToken);
                            if($isValid){
                                $filePath = mysqli_real_escape_string($connection, $_POST['FILE']);
                        
                                    $query = "SELECT * FROM attachmentMapping WHERE path='$filePath'";
                        
                                    $run = mysqli_query($connection, $query);
                                    if($run){
                                        if(mysqli_num_rows($run) > 0){
                                            while($row = mysqli_fetch_array($run, MYSQLI_ASSOC)){
                                                $actualName = $row['filename'];
                                            }
                        
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
                                                header("HTTP/1.0 404 Not Found");
                                                $response['status'] = false;
                                                $response['errors'] = "File Not Found";
                                            }
                        
                                        }else{
                                            header("HTTP/1.0 404 Not Found");
                                            $response['status'] = false;
                                            $response['errors'] = "File not found.";
                                        }
                                    }else{
                                        header("HTTP/1.0 500 Internal Server Error");
                                        $response['status'] = false;
                                        $response['errors'] = "Error: " . mysqli_error($connection);
                                    }
                            }else{
                                header("HTTP/1.0 401 Unauthorized");
                                $response['status'] = false;
                                $response['errors'] = "Invalid Token";
                            }
                        }else{
                            header("HHTTP/1.0 400 Bad Request");
                            $response['status'] = false;
                            $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                        }
                    break;

                    case "POST":
                        if(isset($_SERVER['HTTP_X_API_KEY']) && isset($_FILES["file"])){
                            $AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                
                            $isValid = $AuthTokens->isTokenValid($AccessToken);
                            if($isValid){
                                if($_FILES["file"]["error"] == UPLOAD_ERR_OK){
                                    $tmp_name = $_FILES["file"]["tmp_name"];
                                    $fileName = $_FILES["file"]["name"];
                
                                    $explodedName = explode("/", $tmp_name);
                                    $filetype = explode(".", $fileName);
                
                                    if($filesFunctions->isFileTypeBlocked($filetype[count($filetype)-1]) || $_FILES["file"]["size"] > $settings->maxFileSize){
                                        header("HTTP/1.0 413 Payload Too Large");
                                        $response['status'] = false;
                                        $response['errors'] = "File type not allowed or is too large!";
                                    }else{
                                        $finalFileName = sha1_file($tmp_name) . sha1(time());
                                        move_uploaded_file($tmp_name, ROOT_FOLDER . "/" . $settings->filesPath . $finalFileName);
                                        $storedpath = mysqli_real_escape_string($connection, $settings->filesPath . $finalFileName);
                
                                        $query = "INSERT INTO attachmentMapping (filename, path) VALUES ('$fileName', '$storedpath')";
                                        $run = mysqli_query($connection, $query);
                                        if($run){
                                            $getRow = mysqli_query($connection, "SELECT id FROM attachmentMapping WHERE path='$storedpath'");
                                            if($getRow){
                                                if(mysqli_num_rows($getRow) > 0){
                                                    while($row = mysqli_fetch_array($getRow, MYSQLI_ASSOC)){
                                                        $response['rowID'] = $row['id'];
                                                    }
                                                    header("HTTP/1.0 200 OK");
                                                    $response['status'] = true;
                                                    $response['errors'] = "";
                                                }else{
                                                    header("HTTP/1.0 404 Not Found");
                                                    $response['status'] = false;
                                                    $response['errors'] = "Record not found";
                                                }
                                            }else{
                                                header("HTTP/1.0 500 Internal Server Error");
                                                $response['status'] = false;
                                                $response['errors'] = mysqli_error($connection);
                                            }
                                        }else{
                                            header("HTTP/1.0 500 Internal Server Error");
                                            $response['status'] = false;
                                            $response['errors'] = "Error: " . mysqli_error($connection);
                                        }
                                    }
                                }else{
                                    header("HTTP/1.0 500 Internal Server Error");
                                    $response['status'] = false;
                                    $response['errors'] = "Error: " . $_FILES["file"]["error"];
                                }
                            }else{
                                header("HTTP/1.0 401 Unauthorized");
                                $response['status'] = false;
                                $response['errors'] = "Invalid Token";
                            }
                        }else{
                            header("HTTP/1.0 400 Bad Request");
                            $response['status'] = false;
                            $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                        }
                    break;

                    case "DELETE":
                        header("HTTP/1.0 501 Not Implemented");
                    break;

                    default:
                        header("HTTP/1.0 405 Method Not Allowed");
                        $response["errors"] = "Malformed request";
                }
            break;
    
            case "workspace":
                switch($_SERVER['REQUEST_METHOD']){
                    case "GET":
                        if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                            if(isset($_SERVER['HTTP_X_API_KEY'])){
                                $AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                    
                                $isValid = $authTokens->isTokenValid($AccessToken);
                                if($isValid){
                                    $list = $workspaceFunctions->GetWorkspaceList();
                                    if($list == null || $list != false){
                                        header("HTTP/1.0 200 OK");
                                        $response['status'] = true;
                                        $response['errors'] = "";
                                        $response['contents'] = $list;
                                    }else{
                                        header("HTTP/1.0 500 Internal Server Error");
                                        $response['status'] = false;
                                        $response['errors'] = "An Error Occurred While Trying To Retrieve The Workspace List";
                                    }
                                }else{
                                    header("HTTP/1.0 401 Unauthorized");
                                    $response['status'] = false;
                                    $response['errors'] = "Invalid Token";
                                }
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response['status'] = false;
                                $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                            }
                        }else{
                            if(is_numeric($uri[$opertation+1])){
                                header("HTTP/1.0 501 Not Implemented");
                                //TODO: get workspace info by id
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response["errors"] = "Malformed request";
                            }                        
                        }
                    break;
                    
                    case "POST":
                        if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                            if((isset($_SERVER['HTTP_X_API_KEY'])) || (isset($_POST['name'])) || (isset($_POST['readMode'])) || (isset($_POST['writeMode']))){
                                $AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                    
                                $isValid = $authTokens->isTokenValid($AccessToken);
                    
                                if($isValid){
                                    if($userFunctions->isUserAdmin($isValid)){
                                        $name = base64_decode($_POST['name']);
                                        $name = mysqli_real_escape_string($connection, $name);
                                        $readMode = mysqli_real_escape_string($connection, $_POST['readMode']);
                                        $writeMode = mysqli_real_escape_string($connection, $_POST['writeMode']);
                    
                                        if($workspaceFunctions->EditWorkspace($name, $readMode, $writeMode, null)){
                                            header("HTTP/1.0 200 OK");
                                            $response['status'] = true;
                                            $response['errors'] = "";
                                        }else{
                                            header("HTTP/1.0 500 Internal Server Error");
                                            $response['status'] = false;
                                            $response['errors'] = "An Error Occurred While Trying To Add/Edit The Workspace.";
                                        }
                                    }else{
                                        header("HTTP/1.0 403 Forbidden");
                                        $response['status'] = false;
                                        $response['errors'] = "Permission Denied.";
                                    }
                                }else{
                                    header("HTTP/1.0 401 Unauthorized");
                                    $response['status'] = false;
                                    $response['errors'] = "Invalid Token";
                                }
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response['status'] = false;
                                $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                            }
                        }else{
                            header("HTTP/1.0 400 Bad Request");
                            $response["errors"] = "Malformed request";
                        }
                    break;
    
                    case "PUT":
                        if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                            header("HTTP/1.0 400 Bad Request");
                            $response["errors"] = "Malformed request";
                        }else{
                            if((isset($_SERVER['HTTP_X_API_KEY'])) || (isset($_POST['name'])) || (isset($_POST['readMode'])) || (isset($_POST['writeMode']) || (isset($_POST['workspaceID'])))){
                                $AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                    
                                $isValid = $authTokens->isTokenValid($AccessToken);
                    
                                if($isValid){
                                    if($userFunctions->isUserAdmin($isValid)){
                                        $name = base64_decode($_POST['name']);
                                        $name = mysqli_real_escape_string($connection, $name);
                                        $readMode = mysqli_real_escape_string($connection, $_POST['readMode']);
                                        $writeMode = mysqli_real_escape_string($connection, $_POST['writeMode']);
                                        $workspaceID = mysqli_real_escape_string($connection, $_POST['workspaceID']);
                    
                                        if($workspaceFunctions->EditWorkspace($name, $readMode, $writeMode, $workspaceID)){
                                            header("HTTP/1.0 200 OK");
                                            $response['status'] = true;
                                            $response['errors'] = "";
                                        }else{
                                            header("HTTP/1.0 500 Internal Server Error");
                                            $response['status'] = false;
                                            $response['errors'] = "An Error Occurred While Trying To Add/Edit The Workspace.";
                                        }
                                    }else{
                                        header("HTTP/1.0 403 Forbidden");
                                        $response['status'] = false;
                                        $response['errors'] = "Permission Denied.";
                                    }
                                }else{
                                    header("HTTP/1.0 401 Unauthorized");
                                    $response['status'] = false;
                                    $response['errors'] = "Invalid Token";
                                }
                            }else{
                                header("HTTP/1.0 400 Bad Request");
                                $response['status'] = false;
                                $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                            }
                        }
                    break;
    
                    case "DELETE":
                        if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                            header("HTTP/1.0 400 Bad Request");
                            $response["errors"] = "Malformed request";
                        }else{
                            if(is_numeric($uri[$opertation+1])){
                                if((isset($_SERVER['HTTP_X_API_KEY'])) || (isset($_POST['workspaceID']))){
                                    $AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                        
                                    $isValid = $authTokens->isTokenValid($AccessToken);
                                    if($isValid){
                                        if($userFunctions->isUserAdmin($isValid)){
                                            $workspaceID = mysqli_real_escape_string($connection, $_POST['workspaceID']);
                                            if(isset($_POST['deleteSummaries']))
                                            {
                                                $deleteSummaries = mysqli_real_escape_string($connection, $_POST['deleteSummaries']);
                                                $deleteSummaries = ($deleteSummaries == true || $deleteSummaries == "true" || $deleteSummaries == "True" ? true : false);
                                            }else{
                                                $deleteSummaries = false;
                                            }
                        
                                            if($workspaceFunctions->DeleteWorkspace($workspaceID, $deleteSummaries)){
                                                header("HTTP/1.0 200 OK");
                                                $response['status'] = true;
                                                $response['errors'] = "";
                                            }else{
                                                header("HTTP/1.0 500 Internal Server Error");
                                                $response['status'] = false;
                                                $response['errors'] = "An Error Occurred While Trying To Delete The Workspace";
                                            }
                                        }else{
                                            header("HTTP/1.0 403 Forbidden");
                                            $response['status'] = false;
                                            $response['errors'] = "Permission Denied";
                                        }
                                    }else{
                                        header("HTTP/1.0 401 Unauthorized");
                                        $response['status'] = false;
                                        $response['errors'] = "Invalid Token";
                                    }
                                }else{
                                    header("HTTP/1.0 400 Bad Request");
                                    $response['status'] = false;
                                    $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                                }
                            }else{
                                if($uri[$opertation+1] == "flush" && is_numeric($uri[$opertation+2])){
                                    if((isset($_SERVER['HTTP_X_API_KEY'])) || (isset($_POST['workspaceID']))){
                                        $AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
                            
                                        $isValid = $authTokens->isTokenValid($AccessToken);
                            
                                        if($isValid){
                                            if($userFunctions->isUserAdmin($isValid)){
                                                $workspaceID = mysqli_real_escape_string($connection, $_POST['workspaceID']);
                                                if($wokrspaceFunctions->FlushWorkspace($workspaceID)){
                                                    header("HTTP/1.0 200 OK");
                                                    $response['status'] = true;
                                                    $response['errors'] = "";
                                                }else{
                                                    header("HTTP/1.0 500 Internal Server Error");
                                                    $response['status'] = false;
                                                    $response['errors'] = "An Error Occurred While Trying To Flush The Summaries.";
                                                }
                                            }else{
                                                header("HTTP/1.0 403 Forbidden");
                                                $response['status'] = false;
                                                $response['errors'] = "Permission Denied.";
                                            }
                                        }else{
                                            header("HTTP/1.0 401 Unauthorized");
                                            $response['status'] = false;
                                            $response['errors'] = "Invalid Token";
                                        }
                                    }else{
                                        header("HTTP/1.0 400 Bad Request");
                                        $response['status'] = false;
                                        $response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
                                    }
                                }else{
                                    header("HTTP/1.0 400 Bad Request");
                                    $response["errors"] = "Malformed request";
                                }
                            }                        
                        }
                    break;
    
                    default:
                        header("HTTP/1.0 405 Method Not Allowed");
                        $response["errors"] = "Malformed request";
                }
            break;
    
            default:
                header("HTTP/1.0 404 Not Found");
                $response["errors"] = "Operation does not exist";
        }
    }
}catch(Exception $e){
    header("HTTP/1.0 500 Internal Server Error");
    $response["status"] = false;
    $response["error"] = "An unspecified error occurred: " . $e->getMessage();
}
echo json_encode($response);
?>