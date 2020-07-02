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
		$userFunctions = new UserFunctions();
		$summaryFunctions = new SummaryFunctions();

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
						$response['status'] = true;
						$response['errors'] = "";
						$response['contents'] = $list;
					}else{
						$response['status'] = false;
						$response['errors'] = "An Error Occurred While Trying To Retrieve Summary Information";
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