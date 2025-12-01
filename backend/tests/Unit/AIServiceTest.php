<?php

namespace Tests\Unit;

use App\Enums\PieceRank;
use App\Enums\PlayerColor;
use App\Models\Game;
use App\Services\AIService;
use App\Services\GameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIServiceTest extends TestCase
{
    use RefreshDatabase;

    private AIService $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiService = new AIService(new GameService());
    }

    public function test_generate_setup_creates_40_pieces_for_blue(): void
    {
        $setup = $this->aiService->generateSetup(PlayerColor::BLUE);

        $this->assertCount(40, $setup);
    }

    public function test_generate_setup_creates_40_pieces_for_red(): void
    {
        $setup = $this->aiService->generateSetup(PlayerColor::RED);

        $this->assertCount(40, $setup);
    }

    public function test_generate_setup_places_blue_pieces_in_correct_rows(): void
    {
        $setup = $this->aiService->generateSetup(PlayerColor::BLUE);

        foreach ($setup as $piece) {
            $this->assertGreaterThanOrEqual(0, $piece['row']);
            $this->assertLessThanOrEqual(3, $piece['row']);
        }
    }

    public function test_generate_setup_places_red_pieces_in_correct_rows(): void
    {
        $setup = $this->aiService->generateSetup(PlayerColor::RED);

        foreach ($setup as $piece) {
            $this->assertGreaterThanOrEqual(6, $piece['row']);
            $this->assertLessThanOrEqual(9, $piece['row']);
        }
    }

    public function test_generate_setup_has_correct_piece_distribution(): void
    {
        $setup = $this->aiService->generateSetup(PlayerColor::BLUE);

        $counts = [];
        foreach (PieceRank::cases() as $rank) {
            $counts[$rank->value] = 0;
        }

        foreach ($setup as $piece) {
            $counts[$piece['rank']]++;
        }

        // Verify each piece type has the correct count
        foreach (PieceRank::cases() as $rank) {
            $this->assertEquals(
                $rank->getCount(),
                $counts[$rank->value],
                "Expected {$rank->getCount()} {$rank->getName()} pieces, got {$counts[$rank->value]}"
            );
        }
    }

    public function test_generate_setup_includes_flag(): void
    {
        $setup = $this->aiService->generateSetup(PlayerColor::BLUE);

        $flags = array_filter($setup, fn($p) => $p['rank'] === PieceRank::FLAG->value);

        $this->assertCount(1, $flags);
    }

    public function test_generate_setup_includes_correct_number_of_scouts(): void
    {
        $setup = $this->aiService->generateSetup(PlayerColor::BLUE);

        $scouts = array_filter($setup, fn($p) => $p['rank'] === PieceRank::SCOUT->value);

        $this->assertCount(8, $scouts);
    }

    public function test_generate_setup_includes_correct_number_of_bombs(): void
    {
        $setup = $this->aiService->generateSetup(PlayerColor::BLUE);

        $bombs = array_filter($setup, fn($p) => $p['rank'] === PieceRank::BOMB->value);

        $this->assertCount(6, $bombs);
    }

    public function test_generate_setup_all_pieces_have_valid_columns(): void
    {
        $setup = $this->aiService->generateSetup(PlayerColor::BLUE);

        foreach ($setup as $piece) {
            $this->assertGreaterThanOrEqual(0, $piece['col']);
            $this->assertLessThanOrEqual(9, $piece['col']);
        }
    }

    public function test_generate_setup_no_duplicate_positions(): void
    {
        $setup = $this->aiService->generateSetup(PlayerColor::BLUE);

        $positions = array_map(fn($p) => "{$p['row']},{$p['col']}", $setup);
        $uniquePositions = array_unique($positions);

        $this->assertCount(40, $uniquePositions, 'All pieces should be in unique positions');
    }
}
