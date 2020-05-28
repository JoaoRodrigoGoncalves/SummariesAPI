<?php
header('Content-type: application/json; charset=utf-8');
$response['status'] = false;
$response['errors'] = "";
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

	try {
		if((!isset($_POST['API'])) || (!isset($_POST['username'])) || (!isset($_POST['displayName'])) || (!isset($_POST['classID'])) || (!isset($_POST['admin'])) || (!isset($_POST['deletionProtection']))){
			throw new Exception("Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)");
		}

		$APIkey = mysqli_real_escape_string($connection, $_POST['API']);

		$queryAPI = "SELECT apikey FROM APIkeys WHERE apikey='$APIkey'";
		$APIcheck = mysqli_query($connection, $queryAPI);
		if($APIcheck){
			if(mysqli_num_rows($APIcheck) == 1){
				
				$username = base64_decode($_POST['username']);
				$username = mysqli_real_escape_string($connection, $username);
				$displayName = base64_decode($_POST['displayName']);
				$displayName = mysqli_real_escape_string($connection, $displayName);
				$classID = base64_decode($_POST['classID']);
				$classID = mysqli_real_escape_string($connection, $classID);
				$isAdmin = mysqli_real_escape_string($connection, $_POST['admin']);
				if(($isAdmin == "true") || ($isAdmin=="True")){
					$isAdmin = 1;
				}else{
					$isAdmin = 0;
				}
				$isDeletionProtected = mysqli_real_escape_string($connection, $_POST['deletionProtection']);
				if(($isDeletionProtected == "true") || ($isDeletionProtected=="True")){
					$isDeletionProtected = 1;
				}else{
					$isDeletionProtected = 0;
				}

				if(isset($_POST['userID'])){
					$userID = mysqli_real_escape_string($connection, $_POST['userID']);
					$query = "UPDATE users SET user='$username', classID='$classID', displayName='$displayName', adminControl='$isAdmin', isDeletionProtected='$isDeletionProtected' WHERE id='$userID'";
					$result = mysqli_query($connection, $query);
					if(!$result){
						$response['errors'] = mysqli_error($connection);
					}else{
						$response['status'] = true;
					}
				}else{
					$defaultPW = password_hash("defaultPW", PASSWORD_BCRYPT);
					$query = "INSERT INTO users (user, classID, password, displayName, adminControl, isDeletionProtected) VALUES ('$username', '$classID', '$defaultPW', '$displayName', '$isAdmin', '$isDeletionProtected')";
					$result = mysqli_query($connection, $query);
					if($result){
						$response['status'] = true;
					}else{
						$response['status'] = false;
						$response['errors'] = "" . mysqli_error($connection) . "";
					}
				}
			}else{
				$response['status'] = false;
				$response['errors'] = "Invalid key";
			}
		}else{
			$response['status'] = false;
			$response['errors'] = mysqli_error($connection);
		}
	} catch (Exception $e) {
		$response['status'] = false;
		$response['errors'] = "Error: " . $e->getMessage();
	}
}else{
	$response['status'] = false;
	$response['errors'] = "403 Forbidden";
}
echo json_encode($response);
?>