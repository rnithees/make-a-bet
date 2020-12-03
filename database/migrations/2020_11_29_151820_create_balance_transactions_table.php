<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBalanceTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /*
         id
         player_id
         amount
         amount_before
         */
        Schema::create('balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id');
            $table->double('amount', 8, 2);
            $table->double('amount_before', 8, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('balance_transactions');
    }
}
