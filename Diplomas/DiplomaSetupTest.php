<?php

namespace PL\Tests\Diplomas;

if (PHP_SAPI !== 'cli') {
    exit;
}

require_once __DIR__ . '/DiplomaSetup.php';

final class DiplomaSetupTest extends \PlTestCase
{
    private function sampleConfigData(array $overrides = []): array
    {
        return array_merge([
            'CompetitionName' => 'Mistrzostwa Polski',
            'Dates'           => '15-17.03.2026',
            'Location'        => 'Wrocław',
            'PlaceFrom'       => 1,
            'PlaceTo'         => 3,
            'BodyText'        => 'Tekst dyplomu',
            'HeadJudge'       => 'Jan Kowalski',
            'Organizer'       => 'PZŁucz',
            'TitlesEnabled'   => 1,
        ], $overrides);
    }

    // --- pl_diploma_ensure_tables (install/upgrade) --------------------------

    public function testEnsureTablesCreatesBothTablesWhenMissing(): void
    {
        \pl_diploma_ensure_tables();

        $this->assertCount(1, \FakeDb::executed('/CREATE TABLE PLDiplomaConfig/'));
        $this->assertCount(1, \FakeDb::executed('/CREATE TABLE PLDiplomaEventText/'));
        $this->assertCount(0, \FakeDb::executed('/ALTER TABLE/'));
    }

    public function testEnsureTablesSkipsCreateWhenTablesExist(): void
    {
        \FakeDb::on("/SHOW TABLES LIKE 'PLDiplomaConfig'/", [['t' => 'PLDiplomaConfig']]);
        \FakeDb::on("/SHOW TABLES LIKE 'PLDiplomaEventText'/", [['t' => 'PLDiplomaEventText']]);
        \FakeDb::on("/SHOW COLUMNS FROM PLDiplomaConfig LIKE 'PlDcTitlesEnabled'/", [['Field' => 'PlDcTitlesEnabled']]);
        \FakeDb::on("/SHOW COLUMNS FROM PLDiplomaEventText LIKE 'PlDeTitlePrefix'/", [['Field' => 'PlDeTitlePrefix']]);
        \FakeDb::on("/SHOW COLUMNS FROM PLDiplomaEventText LIKE 'PlDeTitleText'/", [['Field' => 'PlDeTitleText']]);
        \FakeDb::on("/SHOW COLUMNS FROM PLDiplomaEventText LIKE 'PlDeCategoryName'/", [['Field' => 'PlDeCategoryName']]);
        \FakeDb::on("/SHOW COLUMNS FROM PLDiplomaEventText LIKE 'PlDeBowPhrase'/", [['Field' => 'PlDeBowPhrase']]);

        \pl_diploma_ensure_tables();

        $this->assertCount(0, \FakeDb::executed('/CREATE TABLE/'));
        $this->assertCount(0, \FakeDb::executed('/ADD COLUMN/'));
        // The event-text column is always widened on the existing-table path.
        $this->assertCount(1, \FakeDb::executed('/MODIFY PlDeEventCode/'));
    }

    public function testEnsureTablesAddsTitlesEnabledColumnWhenUpgrading(): void
    {
        \FakeDb::on("/SHOW TABLES LIKE 'PLDiplomaConfig'/", [['t' => 'PLDiplomaConfig']]);
        \FakeDb::on("/SHOW TABLES LIKE 'PLDiplomaEventText'/", [['t' => 'PLDiplomaEventText']]);
        // No handler for the PlDcTitlesEnabled/title-column probes -> 0 rows -> upgrade path runs.

        \pl_diploma_ensure_tables();

        $this->assertCount(1, \FakeDb::executed('/ADD COLUMN PlDcTitlesEnabled/'));
        $this->assertCount(1, \FakeDb::executed('/ADD COLUMN PlDeTitlePrefix/'));
        $this->assertCount(1, \FakeDb::executed('/ADD COLUMN PlDeTitleText/'));
    }

    public function testEnsureTablesAddsCategoryColumnsWhenUpgrading(): void
    {
        \FakeDb::on("/SHOW TABLES LIKE 'PLDiplomaConfig'/", [['t' => 'PLDiplomaConfig']]);
        \FakeDb::on("/SHOW TABLES LIKE 'PLDiplomaEventText'/", [['t' => 'PLDiplomaEventText']]);
        \FakeDb::on("/SHOW COLUMNS FROM PLDiplomaConfig LIKE 'PlDcTitlesEnabled'/", [['Field' => 'PlDcTitlesEnabled']]);
        \FakeDb::on("/SHOW COLUMNS FROM PLDiplomaEventText LIKE 'PlDeTitlePrefix'/", [['Field' => 'PlDeTitlePrefix']]);
        \FakeDb::on("/SHOW COLUMNS FROM PLDiplomaEventText LIKE 'PlDeTitleText'/", [['Field' => 'PlDeTitleText']]);
        // No handler for the category-column probes -> 0 rows -> upgrade path runs.

        \pl_diploma_ensure_tables();

        $this->assertCount(1, \FakeDb::executed('/ADD COLUMN PlDeCategoryName/'));
        $this->assertCount(1, \FakeDb::executed('/ADD COLUMN PlDeBowPhrase/'));
    }

    // --- pl_diploma_get_config / save_config ---------------------------------

    public function testGetConfigReturnsDefaultsWhenNotConfigured(): void
    {
        $config = \pl_diploma_get_config(7);

        $this->assertSame('', $config['CompetitionName']);
        $this->assertSame(1, $config['PlaceFrom']);
        $this->assertSame(8, $config['PlaceTo']);
        $this->assertSame(0, $config['TitlesEnabled']);
        $this->assertFalse($config['ConfigExists']);
    }

    public function testGetConfigReturnsStoredValues(): void
    {
        \FakeDb::on('/FROM PLDiplomaConfig/', [[
            'PlDcCompetitionName' => 'Mistrzostwa Polski',
            'PlDcDates' => '15-17.03.2026',
            'PlDcLocation' => 'Wrocław',
            'PlDcPlaceFrom' => 1,
            'PlDcPlaceTo' => 3,
            'PlDcBodyText' => 'Tekst',
            'PlDcHeadJudge' => 'Jan Kowalski',
            'PlDcOrganizer' => 'PZŁucz',
            'PlDcTitlesEnabled' => 1,
        ]]);

        $config = \pl_diploma_get_config(7);

        $this->assertSame('Mistrzostwa Polski', $config['CompetitionName']);
        $this->assertSame(1, $config['TitlesEnabled']);
        $this->assertTrue($config['ConfigExists']);
    }

    public function testSaveConfigInsertsWhenNotExists(): void
    {
        \pl_diploma_save_config(7, $this->sampleConfigData());

        $writes = \FakeDb::executed('/INSERT INTO PLDiplomaConfig/');
        $this->assertCount(1, $writes);
        $this->assertCount(0, \FakeDb::executed('/UPDATE PLDiplomaConfig/'));
        $this->assertMatchesRegularExpression('/\(7, /', $writes[0]);
        $this->assertStringContainsString("'Mistrzostwa Polski'", $writes[0]);
    }

    public function testSaveConfigUpdatesWhenExists(): void
    {
        \FakeDb::on('/SELECT PlDcTournament FROM PLDiplomaConfig/', [['PlDcTournament' => 7]]);

        \pl_diploma_save_config(7, $this->sampleConfigData());

        $writes = \FakeDb::executed('/UPDATE PLDiplomaConfig/');
        $this->assertCount(1, $writes);
        $this->assertCount(0, \FakeDb::executed('/INSERT INTO PLDiplomaConfig/'));
        $this->assertMatchesRegularExpression('/WHERE PlDcTournament = 7/', $writes[0]);
        $this->assertStringContainsString("PlDcCompetitionName = 'Mistrzostwa Polski'", $writes[0]);
    }

    // --- pl_diploma_get_event_texts / save_event_text ------------------------

    public function testGetEventTextsKeyedByEventCode(): void
    {
        \FakeDb::on('/FROM PLDiplomaEventText/', [
            ['PlDeEventCode' => 'I:RM', 'PlDeCustomText' => 'Custom', 'PlDeTitlePrefix' => '', 'PlDeTitleText' => 'Polski Seniorów', 'PlDeCategoryName' => 'Juniorów', 'PlDeBowPhrase' => 'łuków barebow'],
        ]);

        $texts = \pl_diploma_get_event_texts(7);

        $this->assertArrayHasKey('I:RM', $texts);
        $this->assertSame('Custom', $texts['I:RM']['customText']);
        $this->assertSame('Juniorów', $texts['I:RM']['categoryName']);
        $this->assertSame('łuków barebow', $texts['I:RM']['bowPhrase']);
    }

    public function testSaveEventTextInsertsWhenNotExists(): void
    {
        \pl_diploma_save_event_text(7, 'I:RM', 'Custom', 'Prefix', 'Text', 'Juniorów', 'łuków barebow');

        $writes = \FakeDb::executed('/INSERT INTO PLDiplomaEventText/');
        $this->assertCount(1, $writes);
        $this->assertMatchesRegularExpression("/\(7, 'I:RM', 'Custom', 'Prefix', 'Text', 'Juniorów', 'łuków barebow'\)/", $writes[0]);
    }

    public function testSaveEventTextUpdatesWhenExists(): void
    {
        \FakeDb::on('/SELECT PlDeEventCode FROM PLDiplomaEventText/', [['PlDeEventCode' => 'I:RM']]);

        \pl_diploma_save_event_text(7, 'I:RM', 'Custom', 'Prefix', 'Text', 'Juniorów', 'łuków barebow');

        $writes = \FakeDb::executed('/UPDATE PLDiplomaEventText/');
        $this->assertCount(1, $writes);
        $this->assertMatchesRegularExpression("/WHERE PlDeTournament = 7 AND PlDeEventCode = 'I:RM'/", $writes[0]);
        $this->assertStringContainsString("PlDeCustomText = 'Custom'", $writes[0]);
        $this->assertStringContainsString("PlDeCategoryName = 'Juniorów'", $writes[0]);
        $this->assertStringContainsString("PlDeBowPhrase = 'łuków barebow'", $writes[0]);
    }

    public function testSaveEventTextDeletesRowWhenAllFieldsBlankAndRowExists(): void
    {
        \FakeDb::on('/SELECT PlDeEventCode FROM PLDiplomaEventText/', [['PlDeEventCode' => 'I:RM']]);

        \pl_diploma_save_event_text(7, 'I:RM', '', '', '');

        $writes = \FakeDb::executed('/DELETE FROM PLDiplomaEventText/');
        $this->assertCount(1, $writes);
        $this->assertMatchesRegularExpression("/WHERE PlDeTournament = 7 AND PlDeEventCode = 'I:RM'/", $writes[0]);
    }

    public function testSaveEventTextDoesNothingWhenAllFieldsBlankAndRowMissing(): void
    {
        \pl_diploma_save_event_text(7, 'I:RM', '', '', '');

        $this->assertCount(0, \FakeDb::executed('/DELETE FROM PLDiplomaEventText/'));
        $this->assertCount(0, \FakeDb::executed('/INSERT INTO PLDiplomaEventText/'));
        $this->assertCount(0, \FakeDb::executed('/UPDATE PLDiplomaEventText/'));
    }

    public function testSaveEventTextKeepsRowWhenOnlyCategoryFieldsSet(): void
    {
        \pl_diploma_save_event_text(7, 'I:RM', '', '', '', 'Juniorów', '');

        $this->assertCount(1, \FakeDb::executed('/INSERT INTO PLDiplomaEventText/'));
    }
}
