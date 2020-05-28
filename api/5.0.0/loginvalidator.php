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
		if((!isset($_POST['usrnm'])) || (!isset($_POST['psswd']))){
			throw new Exception("Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)");
		}

		$user = mysqli_real_escape_string($connection, $_POST['usrnm']);
		$pass = mysqli_real_escape_string($connection, $_POST['psswd']);

		$query = "SELECT * FROM users WHERE user='$user'";
		$result = mysqli_query($connection, $query);
		if($result){
			if(mysqli_num_rows($result) != 1){
				$response['status'] = false;
			}else{
				while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
					$dbpass = $row['password'];
					$userID = $row['id'];
					$loginName = $row['user'];
					$displayName = $row['displayName'];
					if($row['adminControl'] == 1){
						$adminControl = true;
					}else{
						$adminControl = false;
					}
				}
				if(password_verify($pass, $dbpass)){
					$response['status'] = true;
					$response['APIKEY'] = "1f984e2ed1545f287fe473c890266fea901efcd63d07967ae6d2f09f4566ddde930923ee9212ea815186b0c11a620a5cc85e";
					$response['userID'] = $userID;
					$response['username'] = $loginName;
					$response['displayName'] = $displayName;
					$response['adminControl'] = $adminControl;
				}else{
					$response['status'] = false;
				}
			}
		}else{
			$response['status'] = false;
			$response['errors'] = "" . mysqli_error($connection) . "";
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