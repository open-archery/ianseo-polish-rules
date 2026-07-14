<?php

namespace PL\Tests\Diplomas;

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/Fun_Diploma.php';

final class DiplomaTest extends \PlTestCase
{
    #[DataProvider('compositeKeys')]
    public function testRawEventCode(string $compositeKey, string $expected): void
    {
        $this->assertSame($expected, \pl_diploma_raw_event_code($compositeKey));
    }

    public static function compositeKeys(): array
    {
        return [
            'individual prefix' => ['I:RM', 'RM'],
            'team prefix' => ['T:CU21M', 'CU21M'],
            'mixed prefix' => ['M:RX', 'RX'],
            'no prefix, no colon' => ['RC', 'RC'],
            'colon at wrong position is not a prefix' => ['AB:CD', 'AB:CD'],
            'exactly two chars is too short to strip' => ['R:', 'R:'],
            'empty string' => ['', ''],
        ];
    }
}
