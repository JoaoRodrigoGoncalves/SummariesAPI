<?php
header('Content-type: application/json; charset=utf-8');
require_once("../functions.php");
$response['status'] = false;
$response['errors'] = "";


if(CheckIfSecure()){
	try {

		$connection = databaseConnect();
		$authTokens = new AuthTokens();
		$UserFunctions = new UserFunctions();

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
				$isAdmin = (($isAdmin == "true" || $isAdmin == "True" || $isAdmin==true) ? true : false);
				$isDeletionProtected = mysqli_real_escape_string($connection, $_POST['deletionProtection']);
				$isDeletionProtected = (($isDeletionProtected == "true" || $isDeletionProtected == "True" || $isDeletionProtected==true) ? true : false);
				$userID = (isset($_POST['userID']) ? $_POST['userID'] : null);

				if($UserFunctions->EditUser($username, $displayName, $classID, $isAdmin, $isDeletionProtected, $userID)){
					$response['status'] = true;
					$response['errors'] = "";
				}else{
					$response['status'] = false;
					$response['errors'] = "An Error Occurred While Trying to Create/Edit the User.";
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