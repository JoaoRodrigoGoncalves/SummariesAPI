<?php
require_once(dirname(__FILE__) . "/settings.php");
$settings = new API_Settings();
date_default_timezone_set($settings->timeZone);

/**
 * Checks if the current connection, is secure (using HTTPS)
 * @return bool True if connection is secure, false otherwise
 */
function CheckIfSecure(){
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
}

/**
 * Creates the connection to the database
 * @return mixed The connection to the databse
 */
function databaseConnect(){
    $settings = new API_Settings();
    $connection = @mysqli_connect ($settings->databaseHost, $settings->databaseUser, $settings->databasePsswd, $settings->databaseName) or die ('Não foi possivel ligar á database: '. mysqli_connect_error());
    mysqli_set_charset($connection, 'utf8');
    return $connection;
}

class AuthTokens{
    /**
     * Generates a new Access Token for the specified user
     * @param int $userID The user for whom the token will be created
     * @return mixed The token if operation preformed successfully, false otherwise.
     */
    function GenerateAccessToken($userID){
        $settings = new API_Settings();
        $connection = databaseConnect();

        $token = bin2hex(random_bytes($settings->tokenLength));
        $expireTime = date("Y-m-d h:i:s", time() + ($settings->tokenLifeSpan * 0));
        $tokenEscaped = mysqli_real_escape_string($connection, $token);
        $query = "INSERT INTO AccessTokens (userid, token, expiredate) VALUES ('$userID', '$tokenEscaped', '$expireTime')";
        $saveToken = mysqli_query($connection, $query);
        if($saveToken){
            return $token;
        }else{
            throw new Exception("GENTKN: " . mysqli_error($connection));
            return false;
        }
    }

    /**
     * Checks if token is valid
     * @param string $token The token to search for
     * @return mixed userID associated with token if the operation was successful, false otherwise
     */
    function isTokenValid($token){
        $connection = databaseConnect();
        $authTokens = new AuthTokens();

        $queryToken = mysqli_query($connection, "SELECT userid FROM AccessTokens WHERE token='$token'");
        if($queryToken){
            if(mysqli_num_rows($queryToken) > 0){
                $authTokens->RefreshTime($token);
                while($row = mysqli_fetch_array($queryToken, MYSQLI_ASSOC)){
                    return $row['userid'];
                }
            }
        }else{
            throw new Exception("CHKTKN: " . mysqli_error($connection));
        }
        return false;
    }

    /**
     * Postpones the expiration date of the specified token 
     * @param string $token The token whose the lifespan will be prolonged
     * @return bool True if operation preformed successfully, false otherwise
     */
    function RefreshTime($token){
        $settings = new API_Settings();
        $connection = databaseConnect();

        $expireTime = date("Y-m-d h:i:s", time() + ($settings->tokenLifeSpan * 0));
        $addTime = mysqli_query($connection, "UPDATE AccessTokens SET expiredate='$expireTime' WHERE token='$token'");
        if($addTime){
            if(mysqli_affected_rows($addTime) > 0){
                return true;
            }
        }else{
            throw new Exception("RFSTKN: " . mysqli_error($connection));
        }
        return false;
    }

    /**
     * Deletes the specifeied token
     * @param string $token Then token to be deleted
     * @return bool True if operation preformed successfully, false otherwise.
     */
    function DeleteToken($token){
        $connection = databaseConnect();

        $delete = mysqli_query($connection, "DELETE FROM AccessTokens WHERE token='$token'");
        if($delete){
            if(mysqli_affected_rows($delete) > 0){
                return true;
            }
        }else{
            throw new Exception("DELTKN: " . mysqli_error($connection));
        }
        return false;
    }
}

class UserFunctions{


	/**
	 * Checks if the given user password matches the on on record
	 * @param int $userID The ID of the user whose password will be checked
	 * @param string $password The plain text password to check against records
	 * @return bool True if password matches the one on record, false otherwise.
	 */
	function CheckUserPassword($userID, $password){
		$connection = databaseConnect();

		$query = "SELECT password FROM users WHERE id='$userID'";
		$run = mysqli_query($connection, $query);
		if($run){
			if(mysqli_num_rows($run) == 1){
				while($row = mysqli_fetch_array($run, MYSQLI_ASSOC)){
					if(password_verify($password, $row['password'])){
						return true;
					}
				}
			}
		}else{
			throw new Exception("PSWCHK: " . mysqli_error($connection));
		}
		return false;
	}

    /**
     * Updates password of the specified user
     * @param int $userID The ID of the user whose password will be changed
     * @param string $newPassword The new (already encrypted) password to update the specified user with
     * @return bool True if operation performed successfully, false otherwise.
     */
    function UpdateUserPassword($userID, $newPassword){
        $connection = databaseConnect();

        $query = "UPDATE users SET password='$newPassword' WHERE id='$userID'";
        $updatePassword = mysqli_query($connection, $query);
        if($updatePassword){
            if(mysqli_affected_rows($connection) > 0){
                return true;
            }
        }else{
            throw new Exception("UPDPSW: " . mysqli_error($connection));
        }
        return false;
	}
	
	/**
	 * @param string $username The Username of the user
	 * @param string $displayName The name to be displayed for this user
	 * @param int $classID The ID of the class the user makes part of
	 * @param bool $isAdmin Whether the user is admin or not
	 * @param bool $isDeletionProtected Whether the user is protected against accidental deletion or not
	 * @param int|null $userID The ID of the user to be edited. If this field is filled, it means that it is an edit operation
	 * @return bool True if operation preformed successfully, false otherwise
	 */
	function EditUser($username, $displayName, $classID, $isAdmin, $isDeletionProtected, $userID = null){
		$connection = databaseConnect();
		$settings = new API_Settings();

		$isAdmin = ($isAdmin==true ? 1 : 0);
		$isDeletionProtected = ($isDeletionProtected==true ? 1 : 0);

		if($userID == null){
			$password = password_hash($settings->defaultPassword, PASSWORD_BCRYPT);
			$query = "INSERT INTO users (user, classID, password, displayName, adminControl, isDeletionProtected) VALUES ('$username', '$classID', '$password', '$displayName', '$isAdmin', '$isDeletionProtected')";
		}else{
			$query = "UPDATE users SET user='$username', classID='$classID', displayName='$displayName', adminControl='$isAdmin', isDeletionProtected='$isDeletionProtected' WHERE id='$userID'";
		}

		$run = mysqli_query($connection, $query);
		if($run){
			if(mysqli_affected_rows($connection) == 1){
				return true;
			}
		}else{
			throw new Exception("UPDUSR: " . mysqli_error($connection));
		}
		return false;
	}


    /**
     * Checks if give user is protected against accidental deletion
     * @param int $userID The ID of the user whose protection will be checked
     * @return bool True if user is protected against accidental deletion, false otherwise
     */
    function isUserDeletionProtected($userID){
        $connection = databaseConnect();

        $query = mysqli_query($connection, "SELECT isDeletionProtected FROM users WHERE id='$userID'");
        if($query){
            while($row = mysqli_fetch_array($query, MYSQLI_ASSOC)){
                if($row['isDeletionProtected'] == 1){
                    return true;
                }
            }
        }else{
            throw new Exception("ADMCHK: " . mysqli_error($connection));
        }
        return false;
    }

    /**
     * Checks if given user has admin powers
     * @param int $userID The ID of the user whose permissions will be checked
     * @return bool True if user has admin powers, false otherwise
     */
    function isUserAdmin($userID){
        $connection = databaseConnect();

        $query = mysqli_query($connection, "SELECT adminControl FROM users WHERE id='$userID'");
        if($query){
            while($row = mysqli_fetch_array($query, MYSQLI_ASSOC)){
                if($row['adminControl'] == 1){
                    return true;
                }
            }
        }else{
            throw new Exception("ADMCHK: " . mysqli_error($connection));
        }
        return false;
	}
	
	/**
	 * Deletes the specified user account
	 * @param int $userID The ID of the user whose account will be deleted
	 * @return bool True if operation preformed successfully, false otherwise
	 */
	function DeleteUser($userID){
		$connection = databaseConnect();

		$query = "DELETE FROM users WHERE id='$userID'";
		$run = mysqli_query($connection, $query);
		if($run){
			if(mysqli_affected_rows($connection) == 1){
				return true;
			}
		}else{
			throw new Exception("DELUSR: " . mysqli_error($connection));
		}
		return false;
	}

	/**
	 * Retrieves the user list from the database
	 * @return mixed List of users, false if an error occurs
	 */
	function GetUserList(){
		$connection = databaseConnect();

		$query = "SELECT id, user, displayName, classID, adminControl, isDeletionProtected FROM users ORDER BY id ASC";
		$run = mysqli_query($connection, $query);
		if($run){
			$i = 0;
			while($row = mysqli_fetch_array($run, MYSQLI_ASSOC)){
				$list[$i]['user'] = $row['user'];
				$list[$i]['userid'] = $row['id'];
				$list[$i]['displayName'] = $row['displayName'];
				$list[$i]['className'] = $row['classID'];
				$list[$i]['isAdmin'] = ($row['adminControl'] == 1 ? true : false);
				$list[$i]['isDeletionProtected'] = ($row['isDeletionProtected'] == 1 ? true : false);
				$i++;
			}
			return $list;
		}else{
			throw new Exception("GULERR: " . mysqli_error($connection));
		}
		return false;
	}

}

class ClassFunctions{

    /**
     * Searches for a class given one of the two parameters
     * @param string|null $className The name of the class to search for
     * @param int|null $classID The ID of the class to search for
     * @return bool True if operation preformed successfully, false otherwise
     */
    function ClassExists($className = null, $classID = null){
        $connection = databaseConnect();

        if($className != null || $classID != null){
            if($classID == null){
                $query = "SELECT * FROM classesList WHERE name='$className'";
            }else{
                $query = "SELECT * FROM classesList WHERE id='$classID'";
            }
            $run = mysqli_query($connection, $query);
            if($run){
                if(mysqli_num_rows($run) == 1){
                    return true;
                }
            }else{
                throw new Exception("SCHCLS: " . mysqli_error($connection));
            }
        }
        return false;
    }

    /**
     * Retrives a list of classes and the amount of user in each one of them
     * @return mixed List of classes, false if an error occurs
     */
    function GetClassList(){
        $connection = databaseConnect();

        $query = "SELECT classesList.*, (SELECT COUNT(*) FROM users WHERE users.classID=classesList.id) AS totalUsers FROM classesList";
        $run = mysqli_query($connection, $query);
        if($run){
            $i = 0;
            while($row = mysqli_fetch_array($run, MYSQLI_ASSOC)){
                $list[$i]['classID'] = $row['id'];
                $list[$i]['className'] = $row['name'];
                $list[$i]['totalUsers'] = $row['totalUsers'];
                $i++;
            }
            return $list;
        }else{
            throw new Exception("LSTCLS: " . mysqli_error($connection));
        }
        return false;
    }

    /**
     * Adds / Edits the specified class
     * @param string $className The name of the class
     * @param int|null $classID (Optional) The ID of the class. If this field is filled, it means that this is an edit operation
     * @return bool True if operation preformed successfully, false otherwise
     */
    function EditClass($className, $classID = null){
        $connection = databaseConnect();
        $classFuncions = new ClassFunctions();

        if($classID == null){
            if(!$classFuncions->ClassExists($className)){
                $query = "INSERT INTO classesList (name) VALUES ('$className')";
            }
        }else{
            $query = "UPDATE classesList SET name='$className' WHERE id='$classID'";
        }

        if(isset($query)){
            $run = mysqli_query($connection, $query);
            if($run){
                if(mysqli_affected_rows($connection) == 1){
                    return true;
                }
            }else{
                throw new Exception("EDTCLS: " . mysqli_error($connection));
            }
        }
        return false;
    }

    /**
     * Delete the specified class
     * @param int $classID The ID of the class to be deleted
     * @param bool|true $resetUsers Reset users to the default class (0)
     * @return bool True if operation preformed successfully, false otherwise
     */
    function DeleteClass($classID, $resetUsers = true){
        $connection = databaseConnect();

        if($classID != 0){
            $query = "DELETE FROM classesList WHERE id=$classID";
            $run = mysqli_query($connection, $query);
            if($run){
                if(mysqli_affected_rows($connection) > 0){
                    if($resetUsers){
                        $query = "UPDATE users SET classID='0' WHERE classID='$classID'";
                        $run = mysqli_query($connection, $query);
                    }
                    return true;
                }
            }else{
                throw new Exception("DELCLS: " . mysqli_error($connection));
            }
        }
        return false;
    }

}

class WorkspaceFunctions{

    /**
     * Retrieves a list of workspaces from the database
     * @return mixed List of classes, false if an error occurs
     */
    function GetWorkspaceList(){
        $connection = databaseConnect();

        $query = "SELECT workspaces.*, (SELECT COUNT(*) FROM summaries WHERE summaries.workspace=workspaces.id) AS totalSummaries FROM workspaces";
        $run = mysqli_query($connection, $query);
        if($run){
            if(mysqli_num_rows($run) > 0){
                $i = 0;
                while($row = mysqli_fetch_array($run, MYSQLI_ASSOC)){
                    $list[$i]['id'] = $row['id'];
                    $list[$i]['name'] = $row['name'];
                    $list[$i]['read'] = ($row['read'] == 1 ? true : false);
                    $list[$i]['write'] = ($row['write'] == 1 ? true : false);
                    $list[$i]['totalSummaries'] = $row['totalSummaries'];
                    $i++;
                }
                return $list;
            }
            return null;
        }else{
            throw new Exception("WRKLST: " . mysqli_error($connection));
        }
        return false;
    }

    /**
     * Adds/Edits a workspace
     * @param string $name The name of the workspace
     * @param bool $readMode Whether the users are allowed to read the contents of this workspace
     * @param bool $writeMode Whether the users are allowed to write on this workspace
     * @param int|null $workspaceID The ID of the workspace. If this field is filled, it means that this is an edit operation
     * @return bool True if operation preformed successfully, false otherwise
     */
    function EditWorkspace($name, $readMode, $writeMode, $workspaceID = null){
        $connection = databaseConnect();

        $readMode = ($readMode == true || $readMode == "true" || $readMode == "True" ? 1 : 0);
        $writeMode = ($writeMode == true || $writeMode == "true" || $writeMode == "True" ? 1 : 0);
        $writeMode = ($readMode == 0 ? 0 : $writeMode);

        if($workspaceID == null){
            $query = "INSERT INTO workspaces (name, `read`, `write`) VALUES ('$name', '$readMode', '$writeMode')";
        }else{
            $query = "UPDATE workspaces SET name='$name', `read`='$readMode', `write`='$writeMode' WHERE id='$workspaceID'";
        }
        $run = mysqli_query($connection, $query);
        if($run){
            if(mysqli_affected_rows($connection) == 1){
                return true;
            }
        }else{
            throw new Exception("EDTWRK: " . mysqli_error($connection));
        }
        return false;
    }

    /**
     * Deletes all summaries in a workspace
     * @param int $workspaceID The id of the workspace to flush
     * @return bool True if operation preformed successfully, false otherwise
     */
    function FlushWorkspace($workspaceID){
        $summaryFunctions = new SummaryFunctions();

        $list = $summaryFunctions->GetSummariesList(null, $workspaceID);
        if($list == null || $list != false){
            foreach($list as $row){
                if(!$summaryFunctions->DeleteSummaries($row['id'])){
                    throw new Exception("Could Not Delete The Summary.");
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Deletes the specified workspace
     * @param int $workspaceID The ID of the workspace to be deleted
     * @param bool|false $deleteSummaries Whether the summaries on this workspace should be deleted or not
     * @return bool True if operation preformed successfully, false otherwise
     */
    function DeleteWorkspace($workspaceID, $deleteSummaries = false){
        $connection = databaseConnect();
        $summaryFunctions = new SummaryFunctions();

        $query = "DELETE FROM workspaces WHERE id=$workspaceID";
        $run = mysqli_query($connection, $query);
        if($run){
            if(mysqli_affected_rows($connection) > 0){
                if($deleteSummaries){
                    $summaryFunctions->DeleteSummaries($workspaceID);
                }
                return true;
            }
        }else{
            throw new Exception("DELWRK: " . mysqli_error($connection));
        }
        return false;
    }
}

class SummaryFunctions{

    /**
     * Finds a summary given an userID, summaryID and workspaceID
     * @param int $userID The ID of the user
     * @param int $summaryID The ID of the summary to search for
     * @param int $workspaceID The ID of the workspace
     * @return mixed Database row ID if operation performes successfully, false otherwise
     */
    function FindSummary($userID, $summaryID, $workspaceID){
        $connection = databaseConnect();

        $getSummaryInfo = mysqli_query($connection, "SELECT id FROM summaries WHERE userid='$userID' AND summaryNumber='$summaryID' AND workspace='$workspaceID' LIMIT 1");
        if($getSummaryInfo){
            while($row = mysqli_fetch_array($getSummaryInfo, MYSQLI_ASSOC)){
                return $row['id'];
            }
        }else{
            throw new Exception("FNDSUM: " . mysqli_error($connection));
        }
        return false;
    }

    /**
     * Retrieves a list with all summaries
     * @param int|null $userID ID of the user to limit the search for
     * @param int|null $workspaceID Workspace ID to limit the search for
     * @return mixed List if operation performes successfully, null if empty and false otherwise
     */
    function GetSummariesList($userID = null, $workspaceID = null){
        $connection = databaseConnect();

        if($userID == null && $workspaceID == null){
            $query = "SELECT * FROM summaries";
        }else{
            if($userID != null && $workspaceID != null){
                $query = "SELECT * FROM summaries WHERE userid='$userID' AND workspace='$workspaceID'";
            }else{
                if($userID != null){
                    $query = "SELECT * FROM summaries WHERE userid='$userID'";
                }else{
                    $query = "SELECT * FROM summaries WHERE workspace='$workspaceID'";
                }
            }
        }

        $run = mysqli_query($connection, $query);
        if($run){
            if(mysqli_num_rows($run) > 0){
                $i = 0;
                while($row = mysqli_fetch_array($run, MYSQLI_ASSOC)){
                    $list[$i]['id'] = $row['id'];
                    $rowID = $row['id'];
                    $list[$i]['userid'] = $row['userid'];
                    $list[$i]['date'] = $row['date'];
                    $list[$i]['summaryNumber'] = $row['summaryNumber'];
                    $list[$i]['contents'] = $row['contents'];
                    $fetchFiles = "SELECT * FROM attachmentMapping WHERE summaryID='$rowID'";
                    $runFetch = mysqli_query($connection, $fetchFiles);
                    if($runFetch){
                        if(mysqli_num_rows($runFetch) > 0){
                            $j = 0;
                            while($files = mysqli_fetch_array($runFetch, MYSQLI_ASSOC)){
                                $list[$i]['attachments'][$j]['filename'] = $files['filename'];
                                $list[$i]['attachments'][$j]['path'] = $files['path'];
                                $j++;
                            }
                        }
                    }else{
                        throw new Exception("FISLST: " . mysqli_error($connection));
                    }
                    $i++;
                }
                return $list;
            }else{
                return null;
            }
        }else{
            throw new Exception("SUMLST: " . mysqli_error($connection));
        }
        return false;
    }

    /**
     * Adds/Edits a summary with the given parameters
     * @param bool $isEdit Specifies if this is an edit operation or a new summary
     * @param int $userID ID of the user who "owns" this summary
     * @param int $summaryID SummaryID to give/edit to this summary
     * @param int $workspaceID ID of the workspace this summary makes part of
     * @param string $date Summary date (must be yyyy-mm-dd)
     * @param string $bodyText The text of this summary
     * @param int|null $databaseRow Database row where the summary is stored (only used if $isEdit is true)
     * @return mixed Returns the database row ID if success, bool false otherwise
     */
    function EditSummary($isEdit, $userID, $summaryID, $workspaceID, $date, $bodyText, $databaseRow = null){
        $connection = databaseConnect();
        $summaryFunctions = new SummaryFunctions();

        if($isEdit){
            $query = "UPDATE summaries SET date='$date', summaryNumber='$summaryID', workspace='$workspaceID', contents='$bodyText' WHERE id='$databaseRow'";
        }else{
            $query = "INSERT INTO summaries (userid, date, summaryNumber, workspace, contents) VALUES ('$userID', '$date', '$summaryID', '$workspaceID', '$bodyText')";
        }

        $run = mysqli_query($connection, $query);
        if($run){
            if(mysqli_affected_rows($connection) == 1){
                if($isEdit){
                    return $databaseRow;
                }else{
                    return $summaryFunctions->FindSummary($userID, $summaryID, $workspaceID);
                }
            }
        }else{
            throw new Exception("EDTSUM: " . mysqli_error($connection));
        }
        return false;
    }

    /**
     * Adopts a file given an row ID (requires that the file has already been uploaded)
     * @param int $rowID The row ID on the attachmentsMapping table
     * @param int $summaryID The ID of the summary to which this file should be associated with
     * @return bool True if operation performed successfully, false otherwise
     */
    function AdoptFile($rowID, $summaryID){
        $connection = databaseConnect();

        $query = "SELECT id FROM attachmentMapping WHERE id='$rowID'";
        $run = mysqli_query($connection, $query);
        if($run){
            if(mysqli_num_rows($run) == 1){
                $query = "UPDATE attachmentMapping SET summaryID='$summaryID' WHERE id='$rowID'";
                $run = mysqli_query($connection, $query);
                if($run){
                    if(mysqli_affected_rows($connection) == 1){
                        return true;
                    }
                }else{
                    throw new Exception("UPDFIL: " . mysqli_error($connection));
                }
            }else{
                throw new Exception("File Not Found.");
            }
        }else{
            throw new Exception("CHKFIL: " . mysqli_error($connection));
        }
        return false;
    }

    /**
     * Deletes a file
     * @param int $summaryID The ID of the summary
     * @param string $file The name of the file to be deleted
     * @return bool True if operation performed successfully, false otherwise
     */
    function DeleteFile($summaryID, $file){
        $connection = databaseConnect();

        $getAttachmentsQuery = "SELECT * FROM attachmentMapping WHERE summaryID='$summaryID'";
        $getAttachments = mysqli_query($connection, $getAttachmentsQuery);
        if($getAttachments){
            if(mysqli_num_rows($getAttachments) > 0){
                while($row = mysqli_fetch_array($getAttachments, MYSQLI_ASSOC)){
                    $map[$row['filename']] = $row['path'];
                }

                $fileToQuery = mysqli_real_escape_string($connection, $file);
                $check = mysqli_query($connection, "DELETE FROM attachmentMapping WHERE filename='$fileToQuery' AND summaryID='$summaryID'");
                if($check){
                    if(isset($map[$file])){
                        if(unlink("../" . $map[$file])){
                            return true;
                        }
                    }
                }else{
                    throw new Exception("DELFIL: " . mysqli_error($connection));
                }
            }else{
                throw new Exception("No matches found");
            }
        }else{
            throw new Exception("CHKFIL: " . mysqli_error($connection));
        }
        return false;
    }

    /**
     * Deletes Summaries and files from the database and fileSystem
     * @param int $id SummaryID to delete
     * @return bool True if operation preformed successfully, false otherwise
     */
    function DeleteSummaries($id){
        $connection = databaseConnect();

        $query = "SELECT id FROM summaries WHERE id='$id'";
        $run = mysqli_query($connection, $query);
        if($run){
            if(mysqli_num_rows($run) > 0){
                while($row = mysqli_fetch_array($run, MYSQLI_ASSOC)){
                    $rowID = $row['id'];
                }

                $delete = "DELETE FROM summaries WHERE id='$id'";
                $runDelete = mysqli_query($connection, $delete);
                if($runDelete){
                    if(mysqli_affected_rows($connection) == 1){
                        $files = "SELECT id, path FROM attachmentMapping WHERE summaryID='$rowID'";
                        $fetchFiles = mysqli_query($connection, $files);
                        if($fetchFiles){
                            if(mysqli_num_rows($fetchFiles) > 0){
                                while($row = mysqli_fetch_array($fetchFiles, MYSQLI_ASSOC)){
                                    if(unlink("../../" . $row['path'])){
                                        $thisRow = $row['id'];
                                        $deleteRow = mysqli_query($connection, "DELETE FROM attachmentMapping WHERE id='$thisRow'");
                                        if($deleteRow){
                                            return true;
                                        }else{
                                            throw new Exception("ROWREM: " . mysqli_error($connection));
                                            return false;
                                        }
                                    }else{
                                        throw new Exception("FILDEL");
                                        return false;
                                    }
                                }
                            }
                            return true;
                        }else{
                            throw new Exception("FILFCH: " . mysqli_error($connection));
                        }
                    }
                }else{
                    throw new Exception("SUMDEL: " . mysqli_error($connection));
                }
                return false;
            }
            return true;
        }else{
            throw new Exception("SUMDEL: " . mysqli_error($connection));
        }
        return false;
    }
}

class FilesFunctions{
    function isFileTypeBlocked($type){
        $settings = new API_Settings();
        for ($i=0; $i<count($settings->blockedFiles) ; $i++) { 
            if($type==$settings->blockedFiles[$i]){
                return true;
            }
        }
        return false;
    }
}

?>