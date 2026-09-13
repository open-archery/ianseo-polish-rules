<?php

namespace PL\Tests\Live;

use PHPUnit\Framework\Attributes\DataProvider;

if (PHP_SAPI !== 'cli') {
    exit;
}

require_once __DIR__ . '/Fun_LiveQualification.php';

final class Fun_LiveQualificationTest extends \PlTestCase
{
    #[DataProvider('arrowStrings')]
    public function testArrowsShot(string $arrowString, int $expected): void
    {
        $this->assertSame($expected, \pl_live_qual_arrows_shot($arrowString));
    }

    public static function arrowStrings(): array
    {
        return [
            'empty string' => ['', 0],
            'fully padded' => [str_repeat(' ', 24), 0],
            'fully shot' => [str_repeat('9', 24), 24],
            'mixed shot and padding' => ['9X8978' . str_repeat(' ', 18), 6],
        ];
    }

    #[DataProvider('behindCases')]
    public function testIsBehind(int $arrowsShot, int $maxArrows, int $arrowsPerEnd, bool $expected): void
    {
        $this->assertSame($expected, \pl_live_qual_is_behind($arrowsShot, $maxArrows, $arrowsPerEnd));
    }

    public static function behindCases(): array
    {
        return [
            'gap exactly one end is not behind' => [24, 30, 6, false],
            'gap one arrow over one end is behind' => [23, 30, 6, true],
            'zero gap is not behind' => [30, 30, 6, false],
            'zero arrowsPerEnd is guarded' => [0, 30, 0, false],
            'negative arrowsPerEnd is guarded' => [0, 30, -1, false],
        ];
    }
}
