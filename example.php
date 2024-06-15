<?php
require 'vendor/autoload.php';

use Byond\Byond;

// Convert BYOND timestamp to Unix timestamp
$byondTimestamp = 1234567890;
$unixTimestamp = Byond::convertToUnixFromByond($byondTimestamp);
echo "Unix Timestamp: " . $unixTimestamp . PHP_EOL;

// Convert BYOND timestamp to ISO 8601 format
$isoTimestamp = Byond::convertToTimestampFromByond($byondTimestamp);
echo "ISO 8601 Timestamp: " . $isoTimestamp . PHP_EOL;

// Parse player information from a BYOND page
$pageContent = Byond::getProfilePage('valithor');
$key = Byond::parseKey($pageContent);
$gender = Byond::parseGender($pageContent);
$joined = Byond::parseJoined($pageContent);
$description = Byond::parseDesc($pageContent);
$homePage = Byond::parseHomePage($pageContent);

echo "Key: " . $key . PHP_EOL;
echo "Gender: " . $gender . PHP_EOL;
echo "Joined: " . $joined . PHP_EOL;
echo "Description: " . $description . PHP_EOL;
echo "Home Page: " . $homePage . PHP_EOL;
?>