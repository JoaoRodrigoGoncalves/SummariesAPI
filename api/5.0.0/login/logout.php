<?php
header('Content-type: application/json; charset=utf-8');
require_once("../functions.php");
$response['status'] = false;
$response['errors'] = "";

if(CheckIfSecure()){
	try {
		$connection = databaseConnect();
		$authTokens = new AuthTokens();

		if(isset($_SERVER['HTTP_X_API_KEY'])){
			$AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
        
            if($authTokens->isTokenValid($AccessToken)){
                if($authTokens->DeleteToken($AccessToken)){
                    $response['status'] = true;
                    $response['errors'] = "";
                }else{
                    $response['status'] = false;
                    $response['errors'] = "An Error Occurred While Trying to Delete The Specified Token";
                }
            }else{
                $response['status'] = false;
                $response['errors'] = "Invalid Token";
            }
		}else{
			$response['status'] = false;
			$response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
		}
	} catch (Exception $e) {
		$response['status'] = false;
		$response['errors'] = "Error: " . $e->getMessage();
	}
}else{

	$response['status'] = false;
	$response['errors'] = "A ligação não é segura! É necessário que a ligação seja feita sobre SSL (HTTPS) para continuar.";
}
echo json_encode($response);

?>