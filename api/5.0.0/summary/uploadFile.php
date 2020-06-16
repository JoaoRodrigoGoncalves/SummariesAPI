<?php
header('Content-type: application/json; charset=utf-8');
require_once("../functions.php");
$response['status'] = false;
$response['errors'] = "";

if(CheckIfSecure()){
	try{
		$connection = databaseConnect();
		$AuthTokens = new AuthTokens();
		$filesFunctions = new FilesFunctions();

	if(isset($_SERVER['HTTP_X_API_KEY']))
		$AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);

		$isValid = $AuthTokens->isTokenValid($AccessToken);
		if($isValid){
			if($_FILES["file"]["error"] == UPLOAD_ERR_OK){
				$tmp_name = $_FILES["file"]["tmp_name"];
				$fileName = $_FILES["file"]["name"];

				$explodedName = explode("/", $tmp_name);
				$filetype = explode(".", $fileName);

				if($filesFunctions->isFileTypeBlocked($filetype[count($filetype)-1]) || $_FILES["file"]["size"] > $settings->maxFileSize){
					$response['status'] = false;
					$response['errors'] = "File type not allowed or is too large!";
				}else{
					$finalFileName = sha1_file($tmp_name) . sha1(time());
					move_uploaded_file($tmp_name, "../" . $filespath . $finalFileName);
					$storedpath = mysqli_real_escape_string($connection, $filespath . $finalFileName);

					$query = "INSERT INTO attachmentMapping (filename, path) VALUES ('$fileName', '$storedpath')";
					$run = mysqli_query($connection, $query);
					if($run){
						$getRow = mysqli_query($connection, "SELECT id FROM attachmentMapping WHERE path='$storedpath'");
						if($getRow){
							if(mysqli_num_rows($getRow) > 0){
								while($row = mysqli_fetch_array($getRow, MYSQLI_ASSOC)){
									$response['rowID'] = $row['id'];
								}
								$response['status'] = true;
								$response['errors'] = "";
							}else{
								$response['status'] = false;
								$response['errors'] = "Record not found";
							}
						}else{
							$response['status'] = false;
							$response['errors'] = mysqli_error($connection);
						}
					}else{
						$response['status'] = false;
						$response['errors'] = "Error: " . mysqli_error($connection);
					}
				}
			}else{
				$response['status'] = false;
				$response['errors'] = "Error: " . $_FILES["file"]["error"];
			}
		}else{
			$response['status'] = false;
			$response['errors'] = "Invalid Token";
		}
	}catch(Exception $e){
		$response['status'] = false;
		$response['errors'] = "Error: " . $e->getMessage();
	}
}else{
	$response['status'] = false;
	$response['errors'] = "A ligação não é segura! É necessário que a ligação seja feita sobre SSL (HTTPS) para continuar.";
}
echo json_encode($response);
?>