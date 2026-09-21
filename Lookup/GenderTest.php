<?php

namespace PL\Tests\Lookup;

use PHPUnit\Framework\Attributes\DataProvider;

if (PHP_SAPI !== 'cli') {
    exit;
}

require_once __DIR__ . '/Fun_Gender.php';

final class GenderTest extends \PlTestCase
{
    #[DataProvider('femaleExceptions')]
    public function testFemaleExceptionOverridesEndsInADefault(string $name): void
    {
        $this->assertSame('W', \pl_derive_gender($name));
    }

    public static function femaleExceptions(): array
    {
        return [
            'Abigail'   => ['Abigail'],
            'Angeliki'  => ['Angeliki'],
            'Ariel'     => ['Ariel'],
            'Dinah'     => ['Dinah'],
            'Elizabeth' => ['Elizabeth'],
            'Kendall'   => ['Kendall'],
            'Madeleine' => ['Madeleine'],
            'Miriam'    => ['Miriam'],
            'Nelly'     => ['Nelly'],
            'Nicole'    => ['Nicole'],
            'Nikol'     => ['Nikol'],
            'Noemi'     => ['Noemi'],
            'Sophie'    => ['Sophie'],
            'Vivienne'  => ['Vivienne'],
            'Zerin'     => ['Zerin'],
        ];
    }

    #[DataProvider('maleExceptions')]
    public function testMaleExceptionOverridesEndsInADefault(string $name): void
    {
        $this->assertSame('M', \pl_derive_gender($name));
    }

    public static function maleExceptions(): array
    {
        return [
            'Barnaba'     => ['Barnaba'],
            'Bonawentura' => ['Bonawentura'],
            'Ilia'        => ['Ilia'],
            'Illia'       => ['Illia'],
            'Jarema'      => ['Jarema'],
            'Kosma'       => ['Kosma'],
            'Kuba'        => ['Kuba'],
            'Mykyta'      => ['Mykyta'],
            'Nikita'      => ['Nikita'],
        ];
    }

    public function testUnlistedNameEndingInAIsStillFemale(): void
    {
        $this->assertSame('W', \pl_derive_gender('Ewelina'));
    }

    public function testUnlistedNameNotEndingInAIsStillMale(): void
    {
        $this->assertSame('M', \pl_derive_gender('Krzysztof'));
    }

    public function testExceptionMatchingIsCaseInsensitive(): void
    {
        $this->assertSame('W', \pl_derive_gender('ANGELIKI'));
        $this->assertSame('M', \pl_derive_gender('kosma'));
    }

    #[DataProvider('commaJoinedNames')]
    public function testFirstGivenNameStripsCommaJoinedSecondName(string $raw, string $expected): void
    {
        $this->assertSame($expected, \pl_first_given_name($raw));
    }

    public static function commaJoinedNames(): array
    {
        return [
            'comma with two spaces' => ['Artur,  Damian', 'Artur'],
            'comma with no space'   => ['Marcin,Artur', 'Marcin'],
            'trailing comma only'   => ['Józef,', 'Józef'],
            'no comma unchanged'    => ['Jan Maciej', 'Jan Maciej'],
        ];
    }
}
