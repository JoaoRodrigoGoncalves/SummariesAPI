<?php
class API_Settings{

    // General Settings

    public $timeZone = "Europe/Lisbon";

    // Database Settings

    public $databaseHost = "localhost";
    public $databaseUser = "summaries";
    public $databasePsswd = "HXIUI39kasbb6Bji";
    public $databaseName = "summariesDB";

    // Auth-Related Settings

    public $tokenLength = 64;
    public $tokenLifeSpan = 60; //Time in minutes
    public $defaultPassword = "defaultPW";

    // Class Settings

    public $resetUsersOnDelete = true; // Reset users to the default class (0) when their class gets deleted?

    // File Upload Settings

    public $filesPath = "resources/usercontent/"; // path staring on main folder (Summaries)
    public $maxFileSize = "52428800"; // in bytes (~52MB)
    public $blockedFiles = array("php", "js", "html"); // Blocked filetypes
}
?>