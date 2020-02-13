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
		if((!isset($_POST['API'])) || (!isset($_POST['name'])) || (!isset($_POST['readMode'])) || (!isset($_POST['writeMode']))){
			throw new Exception("Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)");
		}

		$APIkey = mysqli_real_escape_string($connection, $_POST['API']);

		$queryAPI = "SELECT apikey FROM APIkeys WHERE apikey='$APIkey'";
		$APIcheck = mysqli_query($connection, $queryAPI);
		if($APIcheck){
			if(mysqli_num_rows($APIcheck) == 1){
				
				$name = base64_decode($_POST['name']);
				$name = mysqli_real_escape_string($connection, $name);
				$readMode = mysqli_real_escape_string($connection, $_POST['readMode']);
				if(($readMode == "true") || ($readMode=="True")){
					$readMode = 1;
				}else{
					$readMode = 0;
				}
				$writeMode = mysqli_real_escape_string($connection, $_POST['writeMode']);
				if(($writeMode == "true") || ($writeMode=="True")){
					$writeMode = 1;
				}else{
					$writeMode = 0;
				}

				if(isset($_POST['workspaceID'])){
					$workspaceID = mysqli_real_escape_string($connection, $_POST['workspaceID']);
					$query = "UPDATE workspaces SET name='$name', read='$readMode', write='$writeMode' WHERE id='$workspaceID'";
					$result = mysqli_query($connection, $query);
					if(!$result){
						$response['errors'] = mysqli_error($connection);
					}else{
						$response['status'] = true;
					}
				}else{
					$query = "INSERT INTO workspaces (name, read, write) VALUES ('$name', '$readMode', '$writeMode')";
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