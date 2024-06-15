<?php declare(strict_types=1);

/*
 * This file is a part of the Civ13 project.
 *
 * Copyright (c) 2024-present Valithor Obsidion <valithor@valzargaming.com>
 */

namespace Byond;

trait ByondTrait
{
    /**
     * The Unix timestamp representing the BYOND epoch.
     * The BYOND epoch is the starting point for measuring time in the BYOND game engine.
     * It is defined as January 1, 2000, 00:00:00 UTC.
     * This constant represents the BYOND epoch as a Unix timestamp.
     */
    const float|int BYOND_EPOCH_AS_UNIX_TS = 946684800;

    /**
     * The base URL for the BYOND website.
     *
     * @var string
     */
    const string BASE_URL = 'http://www.byond.com/';

    /**
     * The URL for the members section of the BYOND website.
     *
     * @var string
     */
    const string MEMBERS = self::BASE_URL . 'members/';

    /**
     * The URL for a user's profile page on the BYOND website.
     *
     * @var string
     */
    const string PROFILE = 'https://secure.byond.com/members/-/account';

    /**
     * Used to search through bans that are stored within CentCom.
     *
     * @var string
     */
    const string CENTCOM_URL = 'https://centcom.melonmesa.com';

    /**
     * Converts a timestamp in ISO 8601 format to a BYOND timestamp in deciseconds.
     *
     * @param string $iso_timestamp The timestamp in ISO 8601 format to convert.
     * @return float The converted BYOND timestamp in deciseconds.
     */
    public static function convertToByondFromTimestamp(string $iso_timestamp): float
    {
        return self::convertToByondFromUnix(strtotime($iso_timestamp));
    }

    /**
     * Converts a Unix timestamp to a BYOND timestamp in deciseconds.
     *
     * @param float|int $unix_timestamp The Unix timestamp to convert.
     * @return float The converted BYOND timestamp in deciseconds.
     */
    public static function convertToByondFromUnix(float|int $unix_timestamp): float
    {
        return round(($unix_timestamp - self::BYOND_EPOCH_AS_UNIX_TS) * 10);
    }

    /**
     * Converts a Byond timestamp to a Unix timestamp.
     *
     * @param float|int $byond_timestamp The Byond timestamp to convert.
     * @return float The converted Unix timestamp.
     */
    public static function convertToUnixFromByond(float|int $byond_timestamp): float
    {
        return ($byond_timestamp * 0.1) + self::BYOND_EPOCH_AS_UNIX_TS;
    }

    /**
     * Converts a Byond timestamp to a Unix timestamp and returns it in ISO 8601 format.
     *
     * @param float|int $byond_timestamp The Byond timestamp to convert.
     * @return string The converted timestamp in ISO 8601 format.
     */
    public static function convertToTimestampFromByond(float|int $byond_timestamp): string
    {
        return date('c', intval(self::convertToUnixFromByond($byond_timestamp)));
    }

    /**
     * Searches for a ban on the CENTCOM server using the provided ckey.
     *
     * @param string $ckey The ckey to search for.
     * @param bool $prettyprint (Optional) Whether to pretty print the JSON response. Default is true.
     * @return string|false The JSON response as a string if successful, false otherwise.
     */
    public static function bansearch_centcom(string $ckey, bool $prettyprint = true): string|false
    {
        $json = false;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::CENTCOM_URL . '/ban/search/' . urlencode($ckey));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        $response = curl_exec($ch);
        curl_close($ch);
        if (! $response) return false;
        if (! $json = $prettyprint ? json_encode(json_decode($response), JSON_PRETTY_PRINT) : $response) return false;
        return $json;
    }

    /**
     * Retrieves the profile page of a user based on their ckey.
     *
     * @param string $ckey The ckey of the user.
     * @return string|false The profile page content as a string, or false if the page couldn't be retrieved.
     */
    public static function getProfilePage(string $ckey): string|false 
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::MEMBERS . urlencode($ckey) . '?format=text');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // return the page as a string
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        $page = curl_exec($ch);
        curl_close($ch);
        if ($page) return $page;
        return false;
    }

    /**
     * Retrieves the "key" field for a player based on their ckey.
     *
     * @param string $ckey The ckey of the player.
     * @return string|false The key for the player, or false if it cannot be retrieved.
     */
    public static function getKey(string $ckey): string|false
    {
        if (! $page = self::getProfilePage($ckey)) return false;
        return self::parseKey($page);
    }

    /**
     * Retrieves the "gender" field for a player based on their ckey.
     *
     * @param string $ckey The ckey of the player.
     * @return string|false The gender for the player, or false if it cannot be retrieved.
     */
    public static function getGender(string $ckey): string|false
    {
        if (! $page = self::getProfilePage($ckey)) return false;
        return self::parseGender($page);
    }

    /**
     * Retrieves the "joined" field for a player based on their ckey.
     *
     * @param string $ckey The ckey of the player.
     * @return string|false The joined date for the player, or false if it cannot be retrieved.
     */
    public static function getJoined(string $ckey): string|false
    {
        if (! $page = self::getProfilePage($ckey)) return false;
        return self::parseJoined($page);
    }

    /**
     * Retrieves the "description" field for a player based on their ckey.
     *
     * @param string $ckey The ckey of the player.
     * @return string|false The desc for the player, or false if it cannot be retrieved.
     */
    public static function getDesc(string $ckey): string|false
    {
        if (! $page = self::getProfilePage($ckey)) return false;
        return self::parseDesc($page);
    }

    /**
     * Retrieves the "home_page" field for a player based on their ckey.
     *
     * @param string $ckey The ckey of the player.
     * @return string|false The home_page for the player, or false if it cannot be retrieved.
     */
    public static function getHomePage(string $ckey): string|false
    {
        if (! $page = self::getProfilePage($ckey)) return false;
        return self::parseHomePage($page);
    }

    /**
     * Parses the "key" field from the Byond page.
     *
     * @param string $page The Byond page content.
     * @return string|false The key for the player, or false if it cannot be retrieved.
     */
    public static function parseKey(string $page): string|false
    {
        return self::parse($page, 'key = "');
    }

    /**
     * Parses the "gender" field from a Byond page.
     *
     * @param string $page The Byond page content.
     * @return string|false The gender of the player, or false if it cannot be retrieved.
     */
    public static function parseGender(string $page): string|false
    {
        return self::parse($page, 'gender = "');
    }

    /**
     * Parses the "joined" field from a Byond page.
     *
     * @param string $page The Byond page content.
     * @return string|false The joined for of the player, or false if it cannot be retrieved.
     */
    public static function parseJoined(string $page): string|false
    {
        return self::parse($page, 'joined = "');
    }

    /**
     * Parses the "desc" field from a Byond page.
     * This field is manually set by the player.
     *
     * @param string $page The Byond page content.
     * @return string|false The description for the player, or false if it cannot be retrieved.
     */
    public static function parseDesc(string $page): string|false
    {
        return self::parse($page, 'desc = "');
    }

    /**
     * Parses the "home_page" field from a Byond page.
     * This field is manually set by the player.
     *
     * @param string $page The Byond page content.
     * @return string|false The home page URL for the player, or false if not found.
     */
    public static function parseHomePage(string $page): string|false
    {
        return self::parse($page, 'home_page = "');
    }

    /**
     * Parses a field from a given page using a search string.
     *
     * @param string $page The page content to search in.
     * @param string $search_string The string to search for in the page.
     * @return string|false The parsed field if found, or false if not found.
     */
    public static function parse(string $page, string $search_string): string|false
    {
        $page = preg_replace('/\\\(.)/', '', $page); // Escape characters and the following escaped characters must be removed to prevent issues with parsing.
        if (($strpos = strpos($page , $search_string)) === false) return false;
        $strpos += strlen($search_string);
        $length = strpos($page, '"', $strpos) - $strpos;
        return substr($page, $strpos, $length);
    }
}