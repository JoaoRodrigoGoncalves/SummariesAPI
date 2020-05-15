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
		if(!isset($_POST['API']) || !isset($_POST['operation']) || !isset($_POST['userID']) || !isset($_POST['summaryID']) || !isset($_POST['workspace']) || !isset($_POST['date']) || !isset($_POST['contents'])){
			throw new Exception("Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)");
		}

		$APIkey = mysqli_real_escape_string($connection, $_POST['API']);

		$queryAPI = "SELECT apikey FROM APIkeys WHERE apikey='$APIkey'";
		$APIcheck = mysqli_query($connection, $queryAPI);
		if($APIcheck){
			if(mysqli_num_rows($APIcheck) == 1){
				
				$userID = mysqli_real_escape_string($connection, $_POST['userID']);
				$summaryID = mysqli_real_escape_string($connection, $_POST['summaryID']);
				$workspace = mysqli_real_escape_string($connection, $_POST['workspace']);
				$date = base64_decode($_POST['date']);
				$date = mysqli_real_escape_string($connection, $date);
				$contents = base64_decode($_POST['contents']);
				$contents = mysqli_real_escape_string($connection, $contents);

				if($_POST['operation'] == "edit"){
					$dbrowID = mysqli_real_escape_string($connection, $_POST['dbrowID']);
					$query = "UPDATE summaries SET date='$date', summaryNumber='$summaryID', workspace='$workspace', contents='$contents' WHERE userid='$userID' AND id='$dbrowID'";
				}else{
					$query = "INSERT INTO summaries (userid, date, summaryNumber, workspace, contents) VALUES ('$userID', '$date', '$summaryID', '$workspace', '$contents')";
				}

				$run = mysqli_query($connection, $query);
				if($run){

					if(!isset($dbrowID)){
						$getRowID = mysqli_query($connection, "SELECT id FROM summaries WHERE summaryNumber='$summaryID' AND workspace='$workspace' AND userid='$userID' LIMIT 1");
						if($getRowID){
							if(mysqli_num_rows($getRowID) > 0){
								while($row = mysqli_fetch_array($getRowID, MYSQLI_ASSOC)){
									$dbrowID = $row['id'];
								}
							}else{
								$response['status'] = false;
								$response['errors'] = "Can't find the record";
								echo json_encode($response);
								exit();
							}
						}else{
							$response['status'] = false;
							$response['errors'] = "GETROW: " . mysqli_error($connection);
							echo json_encode($response);
							exit();
						}
					}

					if(isset($_POST['filesToRemove'])){
						$files = base64_decode($_POST['filesToRemove']);
						$filesToRemove = json_decode($files);

						$getAttachmentsQuery = "SELECT * FROM attachmentMapping WHERE summaryID='$dbrowID'";
						$getAttachments = mysqli_query($connection, $getAttachmentsQuery);
						if($getAttachments){
							if(mysqli_num_rows($getAttachments) > 0){
								while($row = mysqli_fetch_array($getAttachments, MYSQLI_ASSOC)){
									$map[$row['filename']] = $row['path'];
								}

								foreach ($filesToRemove as $file) {
									$fileToQuery = mysqli_real_escape_string($connection, $file);
									$check = mysqli_query($connection, "DELETE FROM attachmentMapping WHERE filename='$fileToQuery' AND summaryID='$dbrowID'");
									if($check){
										if(isset($map[$file])){
											if(!unlink("../" . $map[$file])){
												$response['status'] = false;
												$response['errors'] = "Error while trying to delete file " . $map[$file];
												echo json_encode($response);
												exit();
											}
										}else{
											$response['status'] = false;
											$response['errors'] = "Not set.";
											echo json_encode($response);
											exit();
										}
									}else{
										$response['status'] = false;
										$response['errors'] = "DELFI: " . mysqli_error($connection);
										echo json_encode($response);
										exit();
									}
								}
							}else{
								$response['status'] = false;
								$response['errors'] = "No matches found.";
								echo json_encode($response);
								exit();
							}
						}else{
							$response['status'] = false;
							$response['errors'] = "GETAT: " . mysqli_error($connection);
							echo json_encode($response);
							exit();
						}
					}

					if(isset($_POST['filesToAdopt'])){
						$files = base64_decode($_POST['filesToAdopt']);
						$filesToAdopt = json_decode($files);

						foreach ($filesToAdopt as $id) {
							$adopt = mysqli_query($connection, "UPDATE attachmentMapping SET summaryID='$dbrowID' WHERE id='$id'");
							if(!$adopt){
								$response['status'] = false;
								$response['errors'] = "ADPFI: " . mysqli_error($connection);
								echo json_encode($response);
								exit();
							}
						}
					}
					$response['status'] = true;
					$response['errors'] = "";
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
			$response['errors'] = "APICK: " . mysqli_error($connection);
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