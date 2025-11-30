<?php

namespace App\Enums;

/**
 * Piece ranks in Stratego
 * Higher rank beats lower rank in combat (except special cases)
 */
enum PieceRank: int
{
    case FLAG = 0;          // Cannot move, must be captured to win
    case SPY = 1;           // Can defeat Marshal if attacking
    case SCOUT = 2;         // Can move multiple squares
    case MINER = 3;         // Can defuse bombs
    case SERGEANT = 4;
    case LIEUTENANT = 5;
    case CAPTAIN = 6;
    case MAJOR = 7;
    case COLONEL = 8;
    case GENERAL = 9;
    case MARSHAL = 10;      // Highest rank
    case BOMB = 11;         // Cannot move, destroys attackers (except Miners)

    public function getName(): string
    {
        return match($this) {
            self::FLAG => 'Flag',
            self::SPY => 'Spy',
            self::SCOUT => 'Scout',
            self::MINER => 'Miner',
            self::SERGEANT => 'Sergeant',
            self::LIEUTENANT => 'Lieutenant',
            self::CAPTAIN => 'Captain',
            self::MAJOR => 'Major',
            self::COLONEL => 'Colonel',
            self::GENERAL => 'General',
            self::MARSHAL => 'Marshal',
            self::BOMB => 'Bomb',
        };
    }

    public function canMove(): bool
    {
        return match($this) {
            self::FLAG, self::BOMB => false,
            default => true,
        };
    }

    public function getCount(): int
    {
        return match($this) {
            self::FLAG => 1,
            self::SPY => 1,
            self::SCOUT => 8,
            self::MINER => 5,
            self::SERGEANT => 4,
            self::LIEUTENANT => 4,
            self::CAPTAIN => 4,
            self::MAJOR => 3,
            self::COLONEL => 2,
            self::GENERAL => 1,
            self::MARSHAL => 1,
            self::BOMB => 6,
        };
    }

    /**
     * Returns true if this piece wins against the defender
     * Returns null if both pieces are destroyed (equal rank)
     * Returns false if this piece loses
     */
    public function winsAgainst(self $defender): ?bool
    {
        // Bomb destroys everything except miner
        if ($defender === self::BOMB) {
            return $this === self::MINER;
        }

        // Spy beats Marshal only when attacking
        if ($this === self::SPY && $defender === self::MARSHAL) {
            return true;
        }

        // Flag is always captured
        if ($defender === self::FLAG) {
            return true;
        }

        // Standard rank comparison
        if ($this->value === $defender->value) {
            return null; // Both destroyed
        }

        return $this->value > $defender->value;
    }
}
