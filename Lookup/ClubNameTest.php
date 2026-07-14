<?php

namespace PL\Tests\Lookup;

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/Fun_ClubName.php';

final class ClubNameTest extends \PlTestCase
{
    #[DataProvider('shortNames')]
    public function testClubShortName(string $raw, string $expected): void
    {
        $this->assertSame($expected, \pl_club_short_name($raw));
    }

    public static function shortNames(): array
    {
        return [
            'quoted name + city' => ['Miejsko-Ludowy Klub Sportowy "Czarna Strzała" (Bytom)', 'MLKS Czarna Strzała Bytom'],
            'unquoted name + city' => ['Uczniowski Klub Sportowy Talent (Wrocław)', 'UKS Talent Wrocław'],
            'niezrzeszony special case' => ['Niezrzeszony (Niezrzeszony)', 'Niezrzeszony'],
        ];
    }

    #[DataProvider('codeBases')]
    public function testClubCodeBase(string $raw, string $expected): void
    {
        $this->assertSame($expected, \pl_club_code_base($raw));
    }

    public static function codeBases(): array
    {
        return [
            'quoted name + city' => ['Miejsko-Ludowy Klub Sportowy "Czarna Strzała" (Bytom)', 'CZABYT'],
            'unquoted name + city' => ['Uczniowski Klub Sportowy Talent (Wrocław)', 'TALWRO'],
            'niezrzeszony special case' => ['Niezrzeszony (Niezrzeszony)', 'NIE'],
        ];
    }

    public function testStripPolishDiacritics(): void
    {
        $this->assertSame(
            'ACELNOSZZacelnoszz',
            \pl_strip_polish_diacritics('ĄĆĘŁŃÓŚŹŻąćęłńóśźż')
        );
    }

    public function testParseClubNameQuotedPath(): void
    {
        $p = \pl_parse_club_name('Miejsko-Ludowy Klub Sportowy "Czarna Strzała" (Bytom)');

        $this->assertSame('Bytom', $p['city']);
        $this->assertSame('MLKS', $p['abbr']);
        $this->assertSame('Czarna Strzała', $p['properName']);
    }

    public function testParseClubNameUnquotedPath(): void
    {
        $p = \pl_parse_club_name('Uczniowski Klub Sportowy Talent (Wrocław)');

        $this->assertSame('Wrocław', $p['city']);
        $this->assertSame('UKS', $p['abbr']);
        $this->assertSame('Talent', $p['properName']);
    }

    public function testResolveClubCodesAssignsSuffixOnCollisionSortedAlphabetically(): void
    {
        $result = \pl_resolve_club_codes([
            'Uczniowski Klub Sportowy Orzeł (Warszawa)',
            'Klub Sportowy Orzeł (Warszawa)',
        ]);

        $this->assertSame('ORZWAR', $result['Klub Sportowy Orzeł (Warszawa)']['code']);
        $this->assertSame('ORZWAR2', $result['Uczniowski Klub Sportowy Orzeł (Warszawa)']['code']);
    }

    public function testResolveClubCodesNoCollisionKeepsBaseCode(): void
    {
        $result = \pl_resolve_club_codes([
            'Uczniowski Klub Sportowy Talent (Wrocław)',
        ]);

        $this->assertSame('TALWRO', $result['Uczniowski Klub Sportowy Talent (Wrocław)']['code']);
    }
}
