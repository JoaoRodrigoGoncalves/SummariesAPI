<?php
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
	if(!isset($_SERVER['HTTP_API']) || !isset($_SERVER['HTTP_FILE'])){
		throw new Exception("Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)");
	}

	$APIkey = mysqli_real_escape_string($connection, $_SERVER['HTTP_API']);

	$queryAPI = "SELECT apikey FROM APIkeys WHERE apikey='$APIkey'";
	$APIcheck = mysqli_query($connection, $queryAPI);
	if($APIcheck){
		if(mysqli_num_rows($APIcheck) == 1){
			
			$filePath = mysqli_real_escape_string($connection, $_SERVER['HTTP_FILE']);

			$query = "SELECT * FROM attachmentMapping WHERE path='$filePath'";

			$run = mysqli_query($connection, $query);
			if($run){
				if(mysqli_num_rows($run) > 0){
					while($row = mysqli_fetch_array($run, MYSQLI_ASSOC)){
						$actualName = $row['filename'];
					}

					if(file_exists("../" . $filePath)){

						// https://www.php.net/manual/en/function.readfile.php

						header('Content-Description: File Transfer');
					    header('Content-Type: application/octet-stream');
					    header('Content-Disposition: attachment; filename="'. $actualName .'"');
					    header('Expires: 0');
					    header('Cache-Control: must-revalidate');
					    header('Pragma: public');
					    header('Content-Length: ' . filesize("../" . $filePath));
					    readfile("../" . $filePath);
					    exit();

					}else{
						echo "File Not Found";
					}

				}else{
					echo "File not found.";
				}
			}else{
				echo "Error: " . mysqli_error($connection);
			}
		}else{
			echo "Invalid Key";
		}
	}else{
		echo mysqli_error($connection);
	}		
} catch (Exception $e) {
	echo "Error: " . $e->getMessage();
}
?>