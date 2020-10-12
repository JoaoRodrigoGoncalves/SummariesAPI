<?php
header('Content-type: application/json; charset=utf-8');
require_once("../functions.php");
$response['status'] = false;
$response['errors'] = "";



if(CheckIfSecure()){
	try {
		$connection = databaseConnect();
		$authTokens = new AuthTokens();

		if(isset($_POST['usrnm']) || isset($_POST['psswd'])){
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
						$adminControl = ($row['adminControl']==1 ? true : false);
					}
					if(password_verify($pass, $dbpass)){
						$response['status'] = true;
						$response['AccessToken'] = $authTokens->GenerateAccessToken($userID);
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
		}else{
			$response['status'] = false;
			$response['errors'] = "Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)";
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