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
		if((!isset($_POST['API'])) || (!isset($_POST['workspaceID']))){
			throw new Exception("Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)");
		}

		$APIkey = mysqli_real_escape_string($connection, $_POST['API']);

		$queryAPI = "SELECT apikey FROM APIkeys WHERE apikey='$APIkey'";
		$APIcheck = mysqli_query($connection, $queryAPI);
		if($APIcheck){
			if(mysqli_num_rows($APIcheck) == 1){

				$workspaceID = mysqli_real_escape_string($connection, $_POST['workspaceID']);

				$getSummaries = mysqli_query($connection, "SELECT id FROM summaries WHERE workspace='$workspaceID'");
				if($getSummaries){
					if(mysqli_num_rows($getSummaries) > 0){
						while($row = mysqli_fetch_array($getSummaries, MYSQLI_ASSOC)){
							$rowList[] = $row['id'];
						}

						$queryEnd = "";

						for ($i=0; $i < count($rowList); $i++) { 
							$queryEnd = $queryEnd . $rowList[$i] . ", ";
						}
						$queryEnd = substr($queryEnd, 0, -1); // removes last space
						$queryEnd = substr($queryEnd, 0, -1); // removes last comma

						$flushWorkspace = mysqli_query($connection, "DELETE FROM summaries WHERE workspace='$workspaceID'");
						if($flushWorkspace){
							if(mysqli_affected_rows($connection) > 0){
								$temp = "SELECT id, path FROM attachmentMapping WHERE summaryID IN(" . $queryEnd . ")";
								$getFiles = mysqli_query($connection, $temp);
								if($getFiles){
									if(mysqli_num_rows($getFiles) > 0){

										while($row = mysqli_fetch_array($getFiles, MYSQLI_ASSOC)){
											$paths[] = $row['path'];
											$rowIDs[] = $row['id'];
										}

										$queryEnd = "";
										for ($i=0; $i < count($rowIDs); $i++) { 
											$queryEnd = $queryEnd . $rowIDs[$i] . ", ";
										}
										$queryEnd = substr($queryEnd, 0, -1); // removes last space
										$queryEnd = substr($queryEnd, 0, -1); // removes last comma

										$deleteMapping = mysqli_query($connection, "DELETE FROM attachmentMapping WHERE id IN(" . $queryEnd . ")");
										if($deleteMapping){
											if(mysqli_affected_rows($connection) > 0){
												foreach ($paths as $pth) {
													if(!unlink("../" . $pth)){
														$response['status'] = false;
														$response['errors'] = "DELFI: Error while trying to delete file " . $pth;
														echo json_encode($response);
														exit();
													}
												}
											}else{
												$response['status'] = false;
												$response['errors'] = "DELMAP: No affected rows";
											}
										}else{
											$response['status'] = false;
											$response['errors'] = "DELMAP: " . mysqli_error($connection);
										}
									}
									$response['status'] = true;
									$response['errors'] = "";
								}else{
									$response['status'] = false;
									$response['errors'] = "GETFIL: " . mysqli_error($connection) . "\n" . $temp;
								}
							}else{
								$response['status'] = false;
								$response['errors'] = "DELSUM: No affected records";
							}
						}else{
							$response['status'] = false;
							$response['errors'] = "DELSUM: " . mysqli_error($connection);
						}
					}else{
						$response['status'] = false;
						$response['errors'] = "GETREC: No records found.";
					}
				}else{
					$response['status'] = false;
					$response['errors'] = "GETREC: " . mysqli_error($connection);
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