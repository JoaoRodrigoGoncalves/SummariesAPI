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
		$summaryFunctions = new SummaryFunctions();

		if(isset($_SERVER['HTTP_X_API_KEY']) && isset($_POST['operation']) && isset($_POST['userID']) && isset($_POST['summaryID']) && isset($_POST['workspaceID']) && isset($_POST['date']) && isset($_POST['contents'])){
			$AcessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);

			$isValid = $authTokens->isTokenValid($AcessToken);

			if($isValid){
				$userID = mysqli_real_escape_string($connection, $_POST['userID']);
				if($userFunctions->isUserAdmin($isValid) || $userID==$isValid){

					$summaryID = mysqli_real_escape_string($connection, $_POST['summaryID']);
					$workspaceID = mysqli_real_escape_string($connection, $_POST['workspaceID']);
					$date = base64_decode($_POST['date']);
					$date = mysqli_real_escape_string($connection, $date);
					$contents = base64_decode($_POST['contents']);
					$contents = mysqli_real_escape_string($connection, $contents);
					$isEdit = ($_POST['operation'] == "edit" ? true : false);
					$dbrowID = (isset($_POST['dbrowID']) ? mysqli_real_escape_string($connection, $_POST['dbrowID']) : null);

					$rowID = $summaryFunctions->EditSummary($isEdit, $userID, $summaryID, $workspaceID, $date, $contents, $dbrowID);

					if($rowID){

						$response['status'] = true;
						$response['errors'] = "";

						if(isset($_POST['filesToAdopt'])){
							$files = base64_decode($_POST['filesToAdopt']);
							$filesToAdopt = json_decode($files);

							foreach ($filesToAdopt as $id) {
								if(!$summaryFunctions->AdoptFile($id, $rowID)){
									$response['status'] = false;
									$response['errors'] = "Failed to Adopt File";
								}
							}
						}

						if($dbrowID != null){
							if(isset($_POST['filesToRemove'])){
								$files = base64_decode($_POST['filesToRemove']);
								$filesToRemove = json_decode($files);

								foreach($filesToRemove as $file){
									if(!$summaryFunctions->DeleteFile($rowID, $file)){
										$response['status'] = false;
										$response['errors'] = "Failed to Delete File";
									}
								}
							}
						}
					}else{
						$response['status'] = false;
						$response['errors'] = "Error While Trying To Save Summary.";
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