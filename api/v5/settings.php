<?php

define("ROOT_FOLDER",  realpath(__DIR__ . "/../..")); // eg. /var/www/summaries

class API_Settings{

    // General Settings

    public $timeZone = "Europe/Lisbon";

    // Database Settings

    public $databaseHost = "localhost";
    public $databaseUser = "summaries";
    public $databasePsswd = "HXIUI39kasbb6Bji";
    public $databaseName = "summariesDB_dev";

    // Auth-Related Settings

    public $tokenLength = 30;
    public $tokenLifeSpan = 15; //Time in minutes
    public $defaultPassword = "defaultPW";

    // Class Settings

    public $resetUsersOnDelete = true; // Reset users to the default class (0) when their class gets deleted?

    // File Upload Settings

    public $filesPath = "resources/usercontent/"; // path staring on main folder (Summaries). This should not be change, since it would require you to rewrite some of the code
    public $maxFileSize = "52428800"; // in bytes (~52MB)
    public $blockedFiles = array("php", "js", "html"); // Blocked filetypes
}
?>