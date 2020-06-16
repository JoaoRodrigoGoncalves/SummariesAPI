<?php
header('Content-type: application/json; charset=utf-8');
require_once("../functions.php");
$response['status'] = false;
$response['errors'] = "";
$response['contents'] = null;


if(CheckIfSecure()){
	try {

		$connection = databaseConnect();
		$authTokens = new AuthTokens();
		$classFunctions = new ClassFunctions();

		if(isset($_SERVER['HTTP_X_API_KEY'])){
			$AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);

			if($authTokens->isTokenValid($AccessToken)){
				$list = $classFunctions->GetClassList();
				if($list){
					$response['status'] = true;
					$response['errors'] = "";
					$response['contents'] = $list;
				}else{
					$response['status'] = false;
					$response['errors'] = "An Error Occurred While Trying to Retrieve The List Of Classes";
				}
			}else{
				$response['status'] = false;
				$response['errors'] = "Invalid Token";
			}
		}else{
			throw new Exception("Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)");
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