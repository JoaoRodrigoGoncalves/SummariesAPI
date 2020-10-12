<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode( '/', $uri );

$apiDIR = array_search("api", $uri);
if($apiDIR === false){
    header("HTTP/1.0 400 Bad Request");
}else{
    $opertation = $apiDIR+2;
    echo $uri[$opertation];
    switch($uri[$opertation]){
        case "login":
            if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                header("HTTP/1.0 Bad Request");
            }else{
                // login
            }
        break;
        
        case "logout":
            if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                header("HTTP/1.0 Bad Request");
            }else{
                // logout
            }
        break;
        
        case "class":
            switch($_SERVER['REQUEST_METHOD']){
                
                case "GET":
                break;

                case "POST":
                break;

                case "PUT":
                break;

                case "DELETE":
                break;

                default:
                    header("HTTP/1.0 405 Method Not Allowed");
            }
        break;

        case "user":
            switch($_SERVER['REQUEST_METHOD']){
                case "GET":
                    if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                        // user list
                    }else{
                        if(is_numeric($uri[$opertation+1])){
                            // user info
                        }else{
                            header("HTTP/1.0 400 Bad Request");
                        }
                    }
                break;

                case "POST":
                    if(is_null($uri[$opertation+1]) || empty($uri[$opertation+1])){
                        // user create
                    }else{
                        header("HTTP/1.0 Bad Request");
                    }
                break;

                case "PUT":
                    if(is_numeric($uri[$opertation+1])){
                        // user edit
                    }else{
                        header("HTTP/1.0 400 Bad Request");
                    }
                break;

                case "DELETE":
                    if(is_numeric($uri[$opertation+1])){
                        // user delete
                    }else{
                        header("HTTP/1.0 400 Bad Request");
                    }
                break;

                default:
                    header("HTTP/1.0 405 Method Not Allowed");
            }
        break;

        case "summary":
            switch($_SERVER['REQUEST_METHOD']){
                
                case "GET":
                break;

                case "POST":
                break;

                case "PUT":
                break;

                case "DELETE":
                break;

                default:
                    header("HTTP/1.0 405 Method Not Allowed");
            }
        break;

        case "workspace":
            switch($_SERVER['REQUEST_METHOD']){
                case "GET":
                break;
                
                case "POST":
                break;

                case "PUT":
                break;

                case "DELETE":
                break;

                default:
                    header("HTTP/1.0 405 Method Not Allowed");
            }
        break;

        default:
            header("HTTP/1.0 404 Not Found");
    }
}
?>