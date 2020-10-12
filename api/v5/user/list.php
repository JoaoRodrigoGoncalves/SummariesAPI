<?php
header('Content-type: application/json; charset=utf-8');
require_once("../functions.php");
$response['status'] = false;
$response['errors'] = "";
$response['contents'] = null;

if(CheckIfSecure()){
	try {

		$connection = databaseConnect();
		$authToken = new AuthTokens();
		$userFunctions = new UserFunctions();

		if(isset($_SERVER['HTTP_X_API_KEY'])){
			$AcessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);

			$isValid = $authToken->isTokenValid($AcessToken);

			if($isValid){
				if($userFunctions->isUserAdmin($isValid)){
					$list = $userFunctions->GetUserList();
					if($list){
						$response['status'] = true;
						$response['errors'] = "";
						$response['contents'] = $list;
					}else{
						$response['status'] = false;
						$response['errors'] = "An Error Occurred While Trying To Retrive The List";
					}
				}else{
					$response['status'] = false;
					$response['errors'] = "Permission Denied.";
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