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
		if((!isset($_POST['userID'])) || (!isset($_POST['API']))){
			throw new Exception("Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)");
		}

		$APIkey = mysqli_real_escape_string($connection, $_POST['API']);

		$queryAPI = "SELECT apikey FROM APIkeys WHERE apikey='$APIkey'";
		$APIcheck = mysqli_query($connection, $queryAPI);
		if($APIcheck){
			if(mysqli_num_rows($APIcheck) == 1){
				$userID = mysqli_real_escape_string($connection, $_POST['userID']);
				if(isset($_POST['reset'])){
					$newpsswd = password_hash("defaultPW", PASSWORD_BCRYPT);
					$query = "UPDATE users SET password='$newpsswd' WHERE id='$userID'";
					$result = mysqli_query($connection, $query);
					if(!$result){
						$response['errors'] = mysqli_error($connection);
					}else{
						$response['status'] = true;
					}
				}else{
					$oldpsswd = base64_decode($_POST['oldpsswd']);
					$oldpsswd = mysqli_real_escape_string($connection, $oldpsswd);
					$newpsswd = base64_decode($_POST['newpsswd']);
					$newpsswd = mysqli_real_escape_string($connection, $newpsswd);

					$query = "SELECT password FROM users WHERE id='$userID'";
					$result = mysqli_query($connection, $query);
					if($result){
						if(mysqli_num_rows($result) != 1){
							$response['status'] = false;
							$response['errors'] = "Utilizador não encontrado!";
						}else{
							while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
								$dbpass = $row['password'];
							}
							if(password_verify($oldpsswd, $dbpass)){
								$newpsswd = password_hash($newpsswd, PASSWORD_BCRYPT);
								$query = "UPDATE users SET password='$newpsswd' WHERE id='$userID'";
								$result = mysqli_query($connection, $query);
								if(!$result){
									$response['errors'] = mysqli_errors($connection);
								}else{
									$response['status'] = true;
								}
							}else{
								$response['status'] = false;
							}
						}
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