<?php
header('Content-type: application/json; charset=utf-8');
require_once("../functions.php");
$response['status'] = false;
$response['errors'] = "";
if(CheckIfSecure()){
	try {

		$connection = databaseConnect();
		$authTokens = new AuthTokens();
		$settings = new API_Settings();
		$userFunctions = new UserFunctions();

		if((isset($_POST['userID'])) || (isset($_SERVER['HTTP_X_API_KEY']))){

			$AcessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);
			$userID = mysqli_real_escape_string($connection, $_POST['userID']);
	
			$tokenValid = $authTokens->isTokenValid($AcessToken);
			if($tokenValid){
				if(isset($_POST['reset'])){
					if($tokenValid != $userID){
						if($userFunctions->isUserAdmin($tokenValid)){
							if($userFunctions->UpdateUserPassword($userID, password_hash($settings->defaultPassword, PASSWORD_BCRYPT))){
								$response['status'] = true;
								$response['errors'] = "";
							}else{
								$response['status'] = false;
								$response['errors'] = "An Error occured when trying to change the password.";
							}
						}else{
							$response['status'] = false;
							$response['errors'] = "Permission Deneid";
						}
					}else{
						$response['status'] = false;
						$response['errors'] = "Permission Deneid";
					}
				}else{
					if($tokenValid == $userID){
						$oldpsswd = base64_decode($_POST['oldpsswd']);
						$oldpsswd = mysqli_real_escape_string($connection, $oldpsswd);
						$newpsswd = base64_decode($_POST['newpsswd']);
						$newpsswd = mysqli_real_escape_string($connection, $newpsswd);

						if($userFunctions->CheckUserPassword($userID, $oldpsswd)){
							if($userFunctions->UpdateUserPassword($userID, password_hash($newpsswd, PASSWORD_BCRYPT))){
								$response['status'] = true;
								$response['errors'] = "";
							}else{
								$response['status'] = false;
								$response['errors'] = "An Error occured when trying to change the password.";
							}
						}else{
							$response['status'] = false;
							$response['errors'] = "Incorrect Password";
						}
					}else{
						$response['status'] = false;
						$response['errors'] = "Permission Deneid";
					}
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