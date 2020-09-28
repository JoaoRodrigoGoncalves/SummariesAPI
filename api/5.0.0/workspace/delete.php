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
		$workspaceFunctions = new WorkspaceFunctions();

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
						$response['status'] = true;
						$response['errors'] = "";
					}else{
						$response['status'] = false;
						$response['errors'] = "An Error Occurred While Trying To Delete The Workspace";
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