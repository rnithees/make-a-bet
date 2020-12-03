<?php

namespace App\Http\Controllers;

use App\Models\BalanceTransaction;
use App\Models\Bet;
use App\Models\BetSelection;
use App\Models\Player;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use stdClass;

use function PHPUnit\Framework\isEmpty;

class BetController extends Controller
{
    const STAKE_MIN_AMOUNT = 0.3;
    const STAKE_MAX_AMOUNT = 10000;
    const MIN_SELECTIONS = 1;
    const MAX_SELECTIONS = 20;
    const MIN_ODDS = 1;
    const MAX_ODDS = 10000;
    const MAX_WIN_AMOUNT = 20000;
    //create bet
    public function createBet(Request $request)
    {
        $validator = $this->validateRequest($request);

        if ($validator['hasError'])
            return response()->json($validator['errors'], 400);

        try {

            $player = $this->getPlayer($request->player_id);
            if ($player->balance < $validator['maxWinAmount']) {
                return response()->json($this->getError(11, "Insufficient balance"), 400);
            }

            $bet = $this->processBet($player->id, $request);

            if ($bet) {
                return response()->json(new stdClass, 201);
            } else {
                return response()->json($this->getError(0, "Unknown error"), 400);
            }
        } catch (Exception $e) {
            var_dump($e);
            return response()->json($this->getError(0, "Unknown error"), 400);
        }
    }


    private function processBet($playerId, $request)
    {
        //
        DB::beginTransaction();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $winAmount = $request->stake_amount;

            //update bet
            $bet = new Bet();
            $bet->player_id = $playerId;
            $bet->stake_amount = $request->stake_amount;
            $bet->created_at = date('Y-m-d H:i:s');
            $betId = $bet->save();


            //update bet_selections
            $selections = [];
            foreach ($request->selections as $selection) {
                array_push($selections, array(
                    "selection_id" => $selection['id'],
                    "bet_id" => $betId,
                    "odds" => $selection['odds'],
                ));

                $winAmount = $winAmount * $selection['odds'];
            }
            BetSelection::insert($selections);

            //update player balance
            $updatedDetails = Player::updateBalance($playerId, $winAmount);

            //update balance transactions
            $btrans = new BalanceTransaction();
            $btrans->player_id = $playerId;
            $btrans->amount = $updatedDetails['balance'];
            $btrans->amount_before = $updatedDetails['oldBalanace'];
            $btrans->save();

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::commit();

            return $betId;
        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::rollback();
        }
    }

    private function validateRequest($request)
    {
        $hasError = false;
        $globalErrors = [];
        $selectionErrors = [];

        if (empty($request->player_id) || !is_int($request->player_id) || empty($request->stake_amount) || !is_string($request->stake_amount)) {
            $hasError = true;
            array_push($globalErrors, $this->getError(1, 'Betslip structure mismatch'));
        }
        if ($request->stake_amount < $this::STAKE_MIN_AMOUNT) {
            $hasError = true;
            array_push($globalErrors, $this->getError(2, 'Minimum stake amount is ' . $this::STAKE_MIN_AMOUNT));
        }
        if ($request->stake_amount > $this::STAKE_MAX_AMOUNT) {
            $hasError = true;
            array_push($globalErrors, $this->getError(3, 'Maximum stake amount is ' . $this::STAKE_MAX_AMOUNT));
        }
        if (count($request->selections) < $this::MIN_SELECTIONS) {
            $hasError = true;
            array_push($globalErrors, $this->getError(4, 'Minimum number of selections is ' . $this::MIN_SELECTIONS));
        }
        if (count($request->selections) > $this::MAX_SELECTIONS) {
            $hasError = true;
            array_push($globalErrors, $this->getError(5, 'Maximum number of selections is ' . $this::MAX_SELECTIONS));
        }

        $maxWinAmount = $request->stake_amount;
        $selectedIds = [];
        foreach ($request->selections as $key => $selection) {

            if (in_array($selection['id'], $selectedIds)) {
                $hasError = true;
                if (!key_exists($key, $selectionErrors)) {
                    $selectionErrors[$key] = array(
                        "id" => $selection['id'],
                        "errors" => [
                            $this->getError(8, 'Duplicate selection found')
                        ]
                    );
                } else {
                    array_push($selectionErrors[$key]['errors'], $this->getError(8, 'Duplicate selection found'));
                }

                $duplicatedOrigin = array_search($selection['id'], $selectedIds);
                if (!key_exists($duplicatedOrigin, $selectionErrors)) {
                    $selectionErrors[$duplicatedOrigin] = array(
                        "id" => $selection['id'],
                        "errors" => [
                            $this->getError(8, 'Duplicate selection found')
                        ]
                    );
                } else {
                    array_push($selectionErrors[$duplicatedOrigin]['errors'], $this->getError(8, 'Duplicate selection found'));
                }
            } else {
                array_push($selectedIds, $selection['id']);
            }

            if ($selection['odds'] < $this::MIN_ODDS) {
                $hasError = true;
                if (!key_exists($key, $selectionErrors)) {
                    $selectionErrors[$key] = array(
                        "id" => $selection['id'],
                        "errors" => [
                            $this->getError(6, 'Minimum odds are ' . $this::MIN_ODDS)
                        ]
                    );
                } else {
                    array_push($selectionErrors[$key]['errors'], $this->getError(6, 'Minimum odds are ' . $this::MIN_ODDS));
                }
            }


            if ($selection['odds'] > $this::MAX_ODDS) {
                $hasError = true;
                if (!key_exists($key, $selectionErrors)) {

                    $selectionErrors[$key] = array(
                        "id" => $selection['id'],
                        "errors" => [
                            $this->getError(7, 'Maximum odds are ' . $this::MAX_ODDS)
                        ]
                    );
                } else {
                    array_push($selectionErrors[$key]['errors'], $this->getError(7, 'Maximum odds are ' . $this::MAX_ODDS));
                }
            }


            $maxWinAmount = $maxWinAmount * $selection['odds'];
        }

        if ($maxWinAmount > $this::MAX_WIN_AMOUNT) {
            $hasError = true;
            array_push($globalErrors, $this->getError(9, 'Maximum win amount is ' . $this::MAX_WIN_AMOUNT));
        }

        return array(
            "hasError" => $hasError,
            "errors" => array(
                "errors" => $globalErrors,
                "selections" => array_values($selectionErrors)
            ),
            "maxWinAmount" => $maxWinAmount
        );
    }


    private function getError($code, $message)
    {
        $errObj = new stdClass();
        $errObj->code = $code;
        $errObj->message = $message;
        return $errObj;
    }


    private function getPlayer($playerId)
    {
        $player = Player::find($playerId);

        if (!$player) {
            $player = new Player();
            $player->id = $playerId;
            $player->balance = Player::DEFAULT_BALANCE;
            $player->save();
        }

        return $player;
    }
}
