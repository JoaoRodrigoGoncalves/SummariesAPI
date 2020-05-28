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
		if(!isset($_POST['API']) || !isset($_POST['userid']) || !isset($_POST['workspace']) || !isset($_POST['summaryID'])){
			throw new Exception("Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)");
		}

		$APIkey = mysqli_real_escape_string($connection, $_POST['API']);

		$queryAPI = "SELECT apikey FROM APIkeys WHERE apikey='$APIkey'";
		$APIcheck = mysqli_query($connection, $queryAPI);
		if($APIcheck){
			if(mysqli_num_rows($APIcheck) == 1){

				$userID = mysqli_real_escape_string($connection, $_POST['userid']);
				$summaryID = mysqli_real_escape_string($connection, $_POST['summaryID']);
				$workspace = mysqli_real_escape_string($connection, $_POST['workspace']);

				$getSummaryInfo = mysqli_query($connection, "SELECT id FROM summaries WHERE userid='$userID' AND summaryNumber='$summaryID' AND workspace='$workspace' LIMIT 1");
				if($getSummaryInfo){
					if(mysqli_num_rows($getSummaryInfo) > 0){
						while($row = mysqli_fetch_array($getSummaryInfo, MYSQLI_ASSOC)){
							$dbrowID = $row['id'];
						}

						$getSummaryPaths = mysqli_query($connection, "SELECT path FROM attachmentMapping WHERE summaryID='$dbrowID'");
						if($getSummaryPaths){
							if(mysqli_num_rows($getSummaryPaths) > 0){
								while($row = mysqli_fetch_array($getSummaryPaths, MYSQLI_ASSOC)){
									$paths[] = $row['path'];
								}
								foreach ($paths as $pth) {
									if(!unlink("../" . $pth)){
										$response['status'] = false;
										$response['errors'] = "DELFI: Error while trying to delete file " . $pth;
										echo json_encode($response);
										exit();
									}
								}
							}

							$deleteRecords = mysqli_multi_query($connection, "DELETE FROM summaries WHERE id='$dbrowID'; DELETE FROM attachmentMapping WHERE summaryID='$dbrowID'");
							if($deleteRecords){
								if(mysqli_affected_rows($connection) > 0){
									$response['status'] = true;
									$response['errors'] = "";
								}else{
									$response['status'] = false;
									$response['errors'] = "DELREC: No affected records.";
								}
							}else{
								$response['status'] = false;
								$response['errors'] = "DELREC: " . mysqli_error($connection);
							}

						}else{
							$response['status'] = false;
							$response['errors'] = "GETPTH: " . mysqli_error($connection);
						}

					}else{
						$response['status'] = false;
						$response['errors'] = "GETINF: No records";
					}
				}else{
					$response['status'] = false;
					$response['errors'] = "GETINF: " . mysqli_error($connection);
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