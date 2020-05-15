<?php
header('Content-type: application/json; charset=utf-8');
$response['status'] = false;
$response['errors'] = "";

function istypeBlocked($type){
	$blockedTypes = array("php", "js", "html");
	for ($i=0; $i<count($blockedTypes) ; $i++) { 
		if($type==$blockedTypes[$i]){
			return true;
		}
	}
	return false;
}

if($_SERVER['HTTP_USER_AGENT'] == "app"){

	require("../connection.php");

	function isSecure() {
	  return
	    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
	    || $_SERVER['SERVER_PORT'] == 443;
	}

	if(!isSecure()){
		$response['status'] = false;
		$response['errors'] = "A ligação não é segura! É necessário que a ligação seja feita sobre SSL (HTTPS) para continuar.";
		echo json_encode($response);
		exit();
	}

	//************************** SETTINGS ********************

	$filespath = "resources/usercontent/";

	//********************************************************

	try{
		$APIkey = mysqli_real_escape_string($connection, $_SERVER['HTTP_API']);

		$queryAPI = "SELECT apikey FROM APIkeys WHERE apikey='$APIkey'";
		$APIcheck = mysqli_query($connection, $queryAPI);
		if($APIcheck){
			if(mysqli_num_rows($APIcheck) == 1){
				if($_FILES["file"]["error"] == UPLOAD_ERR_OK){
					$tmp_name = $_FILES["file"]["tmp_name"];
					$fileName = $_FILES["file"]["name"];

					$explodedName = explode("/", $tmp_name);
					$filetype = explode(".", $fileName);

					if(istypeBlocked($filetype[count($filetype)-1])){
						$response['status'] = false;
						$response['errors'] = "File type not allowed!";
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
				$response['errors'] = "Invalid Key";
			}
		}else{
			$response['status'] = false;
			$response['errors'] = mysqli_error($connection);
		}
	}catch(Exception $e){
		$response['status'] = false;
		$response['errors'] = "Error: " . $e->getMessage();
	}

}else{
	$response['status'] = false;
	$response['errors'] = "403 Forbidden";
}

echo json_encode($response);
?>