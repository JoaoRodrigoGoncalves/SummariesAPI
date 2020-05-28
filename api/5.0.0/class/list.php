<?php
header('Content-type: application/json; charset=utf-8');
$response['status'] = false;
$response['errors'] = "";
$response['contents'] = null;

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
		if(!isset($_POST['API'])){
			throw new Exception("Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)");
		}

		$APIkey = mysqli_real_escape_string($connection, $_POST['API']);

		$queryAPI = "SELECT apikey FROM APIkeys WHERE apikey='$APIkey'";
		$APIcheck = mysqli_query($connection, $queryAPI);
		if($APIcheck){
			if(mysqli_num_rows($APIcheck) == 1){

				$query = "SELECT classesList.*, (SELECT COUNT(*) FROM users WHERE users.classID=classesList.id) AS totalUsers FROM classesList";
				$run = mysqli_query($connection, $query);
				if($run){

					$response['status'] = true;
					$i = 0;
					while($row = mysqli_fetch_array($run, MYSQLI_ASSOC)){
						$response['contents'][$i]['classID'] = $row['id'];
						$response['contents'][$i]['className'] = $row['name'];
						$response['contents'][$i]['totalUsers'] = $row['totalUsers'];
						$i++;
					}

				}else{
					$response['status'] = false;
					$response['errors'] = "Error: " . mysqli_error($connection);
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