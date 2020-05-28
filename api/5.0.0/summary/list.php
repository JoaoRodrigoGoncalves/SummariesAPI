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
		if(!isset($_POST['API']) || !isset($_POST['userid']) || !isset($_POST['workspace'])){
			throw new Exception("Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)");
		}

		$APIkey = mysqli_real_escape_string($connection, $_POST['API']);

		$queryAPI = "SELECT apikey FROM APIkeys WHERE apikey='$APIkey'";
		$APIcheck = mysqli_query($connection, $queryAPI);
		if($APIcheck){
			if(mysqli_num_rows($APIcheck) == 1){
				
				$userID = mysqli_real_escape_string($connection, $_POST['userid']);
				$workspace = mysqli_real_escape_string($connection, $_POST['workspace']);

				if($workspace != 0){
					$query = "SELECT * FROM summaries WHERE userid=$userID AND workspace=$workspace";
				}else{
					$query = "SELECT * FROM summaries WHERE userid=$userID";
				}

				$run = mysqli_query($connection, $query);
				if($run){
					$response['status'] = true;
					if(mysqli_num_rows($run) > 0){
						$i = 0;
						while($row = mysqli_fetch_array($run, MYSQLI_ASSOC)){

							$response['contents'][$i]['id'] = $row['id'];
							$rowID = $row['id'];
							$response['contents'][$i]['userid'] = $row['userid'];
							$response['contents'][$i]['date'] = $row['date'];
							$response['contents'][$i]['summaryNumber'] = $row['summaryNumber'];
							$response['contents'][$i]['workspace'] = $row['workspace'];
							$response['contents'][$i]['contents'] = $row['contents'];
							$queryFiles = "SELECT filename, path FROM attachmentMapping WHERE summaryID='$rowID'";
							$runFiles = mysqli_query($connection, $queryFiles);
							if($runFiles){
								if(mysqli_num_rows($runFiles) > 0){
									$j = 0;
									while($file = mysqli_fetch_array($runFiles, MYSQLI_ASSOC)){
										$response['contents'][$i]['attachments'][$j]['filename'] = $file['filename'];
										$response['contents'][$i]['attachments'][$j]['path'] = $file['path'];
										$j++;
									}
								}
							}else{
								$response['status'] = false;
								$response['errors'] = mysqli_error($connection);
							}
							$i++;
						}
					}
				}else{
					$response['status'] = false;
					$response['errors'] = "Error: " . mysqli_error($connection);
				}

			}else{
				$response['status'] = false;
				$response['errors'] = "Invalid Key";
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