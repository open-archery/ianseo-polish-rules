<?php

namespace PL\Tests\PointsRanking;

if (PHP_SAPI !== 'cli') {
    exit;
}

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/Presets.php';

/**
 * Bracket integrity over every shipped preset — see spec "Bracket table integrity".
 * No FakeDb needed: PL_POINTS_PRESETS is a plain constant.
 */
final class PresetsTest extends \PHPUnit\Framework\TestCase
{
    public static function presetProvider(): array
    {
        $cases = [];
        foreach (\PL_POINTS_PRESETS as $presetKey => $preset) {
            $cases[$presetKey] = [$presetKey, $preset];
        }
        return $cases;
    }

    #[DataProvider('presetProvider')]
    public function testEveryBracketHasFromLessOrEqualTo(string $presetKey, array $preset): void
    {
        foreach ($preset['classifications'] as $classKey => $classification) {
            foreach ($classification['brackets'] as $bracket) {
                [$from, $to] = $bracket;
                $this->assertLessThanOrEqual(
                    $to,
                    $from,
                    "Preset '$presetKey' classification '$classKey': bracket [$from,$to] has rank_from > rank_to"
                );
            }
        }
    }

    #[DataProvider('presetProvider')]
    public function testNoTwoBracketsOverlapWithinAClassification(string $presetKey, array $preset): void
    {
        foreach ($preset['classifications'] as $classKey => $classification) {
            $brackets = $classification['brackets'];
            foreach ($brackets as $i => $a) {
                foreach ($brackets as $j => $b) {
                    if ($i >= $j) {
                        continue;
                    }
                    $overlap = $a[0] <= $b[1] && $b[0] <= $a[1];
                    $this->assertFalse(
                        $overlap,
                        "Preset '$presetKey' classification '$classKey': brackets [{$a[0]},{$a[1]}] and [{$b[0]},{$b[1]}] overlap"
                    );
                }
            }
        }
    }

    #[DataProvider('presetProvider')]
    public function testEveryReportReferencesADeclaredClassification(string $presetKey, array $preset): void
    {
        $declared = array_keys($preset['classifications']);
        foreach ($preset['reports'] as $report) {
            $refs = [];
            if ($report['kind'] === 'SEPARATE') {
                $refs[] = $report['classification'];
            } elseif ($report['kind'] === 'COMBINED') {
                $refs = $report['classifications'];
            }
            foreach ($refs as $ref) {
                $this->assertContains(
                    $ref,
                    $declared,
                    "Preset '$presetKey': report references undeclared classification '$ref'"
                );
            }
        }
    }

    #[DataProvider('presetProvider')]
    public function testEveryCombinedCapIsNonNegative(string $presetKey, array $preset): void
    {
        $caps = array_column(
            array_filter($preset['reports'], fn ($r) => $r['kind'] === 'COMBINED'),
            'cap'
        );
        $this->assertSame(
            [],
            array_filter($caps, fn ($cap) => $cap < 0),
            "Preset '$presetKey': COMBINED cap is negative"
        );
    }

    public function testSixPresetsShipped(): void
    {
        $this->assertCount(6, \PL_POINTS_PRESETS);
    }
}
