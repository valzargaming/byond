# Byond PHP Class

## Overview

The `Byond` PHP class is designed to interact with the website for BYOND (Build Your Own Net Dream), facilitating the retrieval of player information and management of account-related data. This class is particularly useful for developers working on BYOND projects, providing an easy-to-use interface for accessing and manipulating data related to players and their accounts.

## Features

- Convert BYOND timestamps to Unix timestamps and ISO 8601 format.
- Retrieve player details such as gender, join date, description, and homepage from BYOND pages.
- Designed to be used in combination with a caching system to enhance performance.

## Installation

Install the package via Composer:

```bash
composer require valzargaming/byond
```

## Usage

Here is a basic example of how to use the `Byond` class:

```php
<?php
require 'vendor/autoload.php';

use Byond\Byond;

// Convert BYOND timestamp to Unix timestamp
$byondTimestamp = 1234567890;
$unixTimestamp = Byond::convertToUnixFromByond($byondTimestamp);
echo "Unix Timestamp: " . $unixTimestamp;

// Convert BYOND timestamp to ISO 8601 format
$isoTimestamp = Byond::convertToTimestampFromByond($byondTimestamp);
echo "ISO 8601 Timestamp: " . $isoTimestamp;

// Parse player information from a BYOND page
$pageContent = Byond::getProfilePage('valithor');
$key = Byond::__parseKey($pageContent);
$gender = Byond::__parseGender($pageContent);
$joined = Byond::__parseJoined($pageContent);
$description = Byond::__parseDesc($pageContent);
$homePage = Byond::__parseHomePage($pageContent);

echo "Key: " . $gender;
echo "Gender: " . $gender;
echo "Joined: " . $joined;
echo "Description: " . $description;
echo "Home Page: " . $homePage;
?>
```

## Contributing

Contributions are welcome! Please submit a pull request or open an issue to discuss any changes you would like to make.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for more details.

## Author

Valithor Obsidion - [valithor@valzargaming.com](mailto:valithor@valzargaming.com)