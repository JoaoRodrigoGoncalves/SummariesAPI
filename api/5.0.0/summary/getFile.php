<?php
require("../functions.php");

if(CheckIfSecure()){
	try {

		$connection = databaseConnect();
		$authTokens = new AuthTokens();

		if(isset($_SERVER['HTTP_X_API_KEY']) || isset($_SERVER['HTTP_FILE'])){
			$AccessToken = mysqli_real_escape_string($connection, $_SERVER['HTTP_X_API_KEY']);

			$isValid = $authTokens->isTokenValid($AccessToken);
			if($isValid){
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
								$response['status'] = false;
								$response['errors'] = "File Not Found";
							}
		
						}else{
							$response['status'] = false;
							$response['errors'] = "File not found.";
						}
					}else{
						$response['status'] = false;
						$response['errors'] = "Error: " . mysqli_error($connection);
					}
			}else{
				$response['status'] = false;
				$response['errors'] = "Invalid Token";
			}
		}else{
			throw new Exception("Não foi possivel utilizar os dados para autenticação. (Talvez transição HTTP para HTTPS)");
		}
	} catch (Exception $e) {
		echo "Error: " . $e->getMessage();
	}
}else{
	$response['status'] = false;
	$response['errors'] = "A ligação não é segura! É necessário que a ligação seja feita sobre SSL (HTTPS) para continuar.";
}
echo json_encode($response);
?>