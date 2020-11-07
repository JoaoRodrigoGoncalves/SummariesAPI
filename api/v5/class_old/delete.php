<?php
header('Content-type: application/json; charset=utf-8');
require_once("../functions.php");
$response['status'] = false;
$response['errors'] = "";


if(CheckIfSecure()){
	try {

		$connection = databaseConnect();
		$settings = new API_Settings();
		$authTokens = new AuthTokens();
		$userFunctions = new UserFunctions();
		$classFunctions = new ClassFunctions();

		if((isset($_SERVER['HTTP_X_API_KEY'])) || (isset($_POST['classID']))){
			$AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);

			$isValid = $authTokens->isTokenValid($AccessToken);
			if($isValid){
				if($userFunctions->isUserAdmin($isValid)){
					$classID = mysqli_real_escape_string($connection, $_POST['classID']);
					if($classID == 0){
						$response['status'] = false;
						$response['errors'] = "Class 0 is protected at a code level.";
					}else{
						if($classFunctions->DeleteClass($classID, $settings->resetUsersOnDelete)){
							$response['status'] = true;
							$response['errors'] = "";
						}else{
							$response['status'] = false;
							$response['errors'] = "An Error Occurred While Trying To Delete The Class.";
						}
					}
				}else{
					$response['status'] = false;
					$response['errors'] = "Permission Denied";
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