<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Player extends Model
{

    use HasFactory;
    public $timestamps = false;
    protected $table = 'player';

    const DEFAULT_BALANCE = 1000;

    static function updateBalance($playerId, $amountUsed)
    {
        $updatedDetails = [];

        $player = Player::find($playerId);
        $updatedDetails['oldBalanace'] = $player->balance;

        $sql = "update player p set p.balance= p.balance - " . $amountUsed . " where id=" . $playerId . "";
        
        $updated = DB::statement($sql);

        $player = Player::find($playerId);
        $updatedDetails['balance'] = $player->balance;

        // var_dump($updatedDetails);
        return $updatedDetails;
    }
}
