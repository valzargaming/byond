<?php declare(strict_types=1);

/*
 * This file is a part of the Civ13 project.
 *
 * Copyright (c) 2024-present Valithor Obsidion <valithor@valzargaming.com>
 */

namespace Byond;

interface ByondInterface
{
    public static function convertToByondFromTimestamp(string $iso_timestamp): float;
    public static function convertToByondFromUnix(float|int $unix_timestamp): float;
    public static function convertToUnixFromByond(float|int $byond_timestamp): float;
    public static function convertToTimestampFromByond(float|int $byond_timestamp): string;
    public static function bansearch_centcom(string $ckey, bool $prettyprint = true): string|false;
    public static function getProfilePage(string $ckey): string|false;
    public static function getKey(string $ckey): string|false;
    public static function getGender(string $ckey): string|false;
    public static function getJoined(string $ckey): string|false;
    public static function getDesc(string $ckey): string|false;
    public static function getHomePage(string $ckey): string|false;
    public static function parseKey(string $page): string|false;
    public static function parseGender(string $page): string|false;
    public static function parseJoined(string $page): string|false;
    public static function parseDesc(string $page): string|false;
    public static function parseHomePage(string $page): string|false;
    public static function parse(string $page, string $search_string): string|false;
}