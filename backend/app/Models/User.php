<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function games()
    {
        return $this->hasMany(Game::class, 'player_red_id')
            ->orWhere('player_blue_id', $this->id);
    }

    public function gamesAsRed()
    {
        return $this->hasMany(Game::class, 'player_red_id');
    }

    public function gamesAsBlue()
    {
        return $this->hasMany(Game::class, 'player_blue_id');
    }
}
