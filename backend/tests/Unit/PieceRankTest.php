<?php

namespace Tests\Unit;

use App\Enums\PieceRank;
use PHPUnit\Framework\TestCase;

class PieceRankTest extends TestCase
{
    public function test_all_ranks_have_correct_values(): void
    {
        $this->assertEquals(0, PieceRank::FLAG->value);
        $this->assertEquals(1, PieceRank::SPY->value);
        $this->assertEquals(2, PieceRank::SCOUT->value);
        $this->assertEquals(3, PieceRank::MINER->value);
        $this->assertEquals(4, PieceRank::SERGEANT->value);
        $this->assertEquals(5, PieceRank::LIEUTENANT->value);
        $this->assertEquals(6, PieceRank::CAPTAIN->value);
        $this->assertEquals(7, PieceRank::MAJOR->value);
        $this->assertEquals(8, PieceRank::COLONEL->value);
        $this->assertEquals(9, PieceRank::GENERAL->value);
        $this->assertEquals(10, PieceRank::MARSHAL->value);
        $this->assertEquals(11, PieceRank::BOMB->value);
    }

    public function test_get_name_returns_correct_names(): void
    {
        $this->assertEquals('Flag', PieceRank::FLAG->getName());
        $this->assertEquals('Spy', PieceRank::SPY->getName());
        $this->assertEquals('Scout', PieceRank::SCOUT->getName());
        $this->assertEquals('Miner', PieceRank::MINER->getName());
        $this->assertEquals('Sergeant', PieceRank::SERGEANT->getName());
        $this->assertEquals('Lieutenant', PieceRank::LIEUTENANT->getName());
        $this->assertEquals('Captain', PieceRank::CAPTAIN->getName());
        $this->assertEquals('Major', PieceRank::MAJOR->getName());
        $this->assertEquals('Colonel', PieceRank::COLONEL->getName());
        $this->assertEquals('General', PieceRank::GENERAL->getName());
        $this->assertEquals('Marshal', PieceRank::MARSHAL->getName());
        $this->assertEquals('Bomb', PieceRank::BOMB->getName());
    }

    public function test_flag_cannot_move(): void
    {
        $this->assertFalse(PieceRank::FLAG->canMove());
    }

    public function test_bomb_cannot_move(): void
    {
        $this->assertFalse(PieceRank::BOMB->canMove());
    }

    public function test_other_pieces_can_move(): void
    {
        $this->assertTrue(PieceRank::SPY->canMove());
        $this->assertTrue(PieceRank::SCOUT->canMove());
        $this->assertTrue(PieceRank::MINER->canMove());
        $this->assertTrue(PieceRank::SERGEANT->canMove());
        $this->assertTrue(PieceRank::LIEUTENANT->canMove());
        $this->assertTrue(PieceRank::CAPTAIN->canMove());
        $this->assertTrue(PieceRank::MAJOR->canMove());
        $this->assertTrue(PieceRank::COLONEL->canMove());
        $this->assertTrue(PieceRank::GENERAL->canMove());
        $this->assertTrue(PieceRank::MARSHAL->canMove());
    }

    public function test_get_count_returns_correct_piece_counts(): void
    {
        $this->assertEquals(1, PieceRank::FLAG->getCount());
        $this->assertEquals(1, PieceRank::SPY->getCount());
        $this->assertEquals(8, PieceRank::SCOUT->getCount());
        $this->assertEquals(5, PieceRank::MINER->getCount());
        $this->assertEquals(4, PieceRank::SERGEANT->getCount());
        $this->assertEquals(4, PieceRank::LIEUTENANT->getCount());
        $this->assertEquals(4, PieceRank::CAPTAIN->getCount());
        $this->assertEquals(3, PieceRank::MAJOR->getCount());
        $this->assertEquals(2, PieceRank::COLONEL->getCount());
        $this->assertEquals(1, PieceRank::GENERAL->getCount());
        $this->assertEquals(1, PieceRank::MARSHAL->getCount());
        $this->assertEquals(6, PieceRank::BOMB->getCount());
    }

    public function test_total_piece_count_is_40(): void
    {
        $total = 0;
        foreach (PieceRank::cases() as $rank) {
            $total += $rank->getCount();
        }
        $this->assertEquals(40, $total);
    }

    public function test_higher_rank_beats_lower_rank(): void
    {
        $this->assertTrue(PieceRank::MARSHAL->winsAgainst(PieceRank::GENERAL));
        $this->assertTrue(PieceRank::GENERAL->winsAgainst(PieceRank::COLONEL));
        $this->assertTrue(PieceRank::COLONEL->winsAgainst(PieceRank::MAJOR));
        $this->assertTrue(PieceRank::MAJOR->winsAgainst(PieceRank::CAPTAIN));
        $this->assertTrue(PieceRank::CAPTAIN->winsAgainst(PieceRank::LIEUTENANT));
        $this->assertTrue(PieceRank::LIEUTENANT->winsAgainst(PieceRank::SERGEANT));
        $this->assertTrue(PieceRank::SERGEANT->winsAgainst(PieceRank::MINER));
        $this->assertTrue(PieceRank::MINER->winsAgainst(PieceRank::SCOUT));
        $this->assertTrue(PieceRank::SCOUT->winsAgainst(PieceRank::SPY));
    }

    public function test_lower_rank_loses_to_higher_rank(): void
    {
        $this->assertFalse(PieceRank::SPY->winsAgainst(PieceRank::SCOUT));
        $this->assertFalse(PieceRank::SCOUT->winsAgainst(PieceRank::MINER));
        $this->assertFalse(PieceRank::SERGEANT->winsAgainst(PieceRank::CAPTAIN));
    }

    public function test_equal_ranks_destroy_each_other(): void
    {
        $this->assertNull(PieceRank::SCOUT->winsAgainst(PieceRank::SCOUT));
        $this->assertNull(PieceRank::CAPTAIN->winsAgainst(PieceRank::CAPTAIN));
        $this->assertNull(PieceRank::MARSHAL->winsAgainst(PieceRank::MARSHAL));
    }

    public function test_spy_beats_marshal_when_attacking(): void
    {
        $this->assertTrue(PieceRank::SPY->winsAgainst(PieceRank::MARSHAL));
    }

    public function test_marshal_beats_spy_when_attacking(): void
    {
        $this->assertTrue(PieceRank::MARSHAL->winsAgainst(PieceRank::SPY));
    }

    public function test_miner_defuses_bomb(): void
    {
        $this->assertTrue(PieceRank::MINER->winsAgainst(PieceRank::BOMB));
    }

    public function test_bomb_destroys_non_miners(): void
    {
        $this->assertFalse(PieceRank::MARSHAL->winsAgainst(PieceRank::BOMB));
        $this->assertFalse(PieceRank::GENERAL->winsAgainst(PieceRank::BOMB));
        $this->assertFalse(PieceRank::SCOUT->winsAgainst(PieceRank::BOMB));
        $this->assertFalse(PieceRank::SPY->winsAgainst(PieceRank::BOMB));
    }

    public function test_any_piece_captures_flag(): void
    {
        $this->assertTrue(PieceRank::SPY->winsAgainst(PieceRank::FLAG));
        $this->assertTrue(PieceRank::SCOUT->winsAgainst(PieceRank::FLAG));
        $this->assertTrue(PieceRank::MARSHAL->winsAgainst(PieceRank::FLAG));
        $this->assertTrue(PieceRank::MINER->winsAgainst(PieceRank::FLAG));
    }
}
