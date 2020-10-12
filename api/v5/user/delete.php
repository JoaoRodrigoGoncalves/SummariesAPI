<?php
header('Content-type: application/json; charset=utf-8');
require_once("../functions.php");
$response['status'] = false;
$response['errors'] = "";


if(CheckIfSecure()){

	try {

		$connection = databaseConnect();
		$authTokens = new AuthTokens();
		$userFunctions = new UserFunctions();

		if((isset($_SERVER['HTTP_X_API_KEY'])) || (isset($_POST['userID']))){
			
			$AcessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);

			$isValid = $authTokens->isTokenValid($AcessToken);
			if($isValid){
				if($userFunctions->isUserAdmin($isValid)){
					$userID = mysqli_real_escape_string($connection, $_POST['userID']);
					if($userFunctions->isUserDeletionProtected($userID)){
						$response['status'] = false;
						$response['errors'] = "User Protected Against Accidental Deletion.";
					}else{
						if($userID != $isValid){
							if($userFunctions->DeleteUser($userID)){
								$response['status'] = true;
								$response['errors'] = "";
							}else{
								$response['status'] = false;
								$response['errors'] = "An Error Occurred While Trying to Delete the Specified User.";
							}
						}else{
							$response['status'] = false;
							$response['errors'] = "You cannot delete yourself.";
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