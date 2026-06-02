<?php
use PHPUnit\Framework\TestCase;
use Byond\Byond;

class ByondTest extends TestCase
{
    public function testConvertToUnixFromByond()
    {
        $byondTimestamp = 123456789;
        $expectedUnixTimestamp = 946684800 + ($byondTimestamp * 0.1);
        $this->assertEquals($expectedUnixTimestamp, Byond::convertToUnixFromByond($byondTimestamp));
    }

    public function testConvertToTimestampFromByond()
    {
        $byondTimestamp = 123456789;
        $expectedTimestamp = date('c', 946684800 + (int) ($byondTimestamp * 0.1));
        $this->assertEquals($expectedTimestamp, Byond::convertToTimestampFromByond($byondTimestamp));
    }

    public function testConvertToByondFromUnix()
    {
        $unixTimestamp = time();
        $expectedByondTimestamp = round(($unixTimestamp - 946684800) * 10);
        $this->assertEquals($expectedByondTimestamp, Byond::convertToByondFromUnix($unixTimestamp));
    }

    public function testConvertToByondFromTimestamp()
    {
        $isoTimestamp = '2022-01-01T12:00:00+00:00';
        $unixTimestamp = strtotime($isoTimestamp);
        $expectedByondTimestamp = round(($unixTimestamp - 946684800) * 10);
        $this->assertEquals($expectedByondTimestamp, Byond::convertToByondFromTimestamp($isoTimestamp));
    }
}