<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->after('paypal_transaction_token', function (Blueprint $table) {
                $table->string('suitpay_payment_token')->nullable();
                $table->string('suitpay_payment_id')->nullable();
                $table->string('suitpay_payment_order_id')->nullable();
                $table->string('suitpay_payment_code')->nullable();
                $table->string('suitpay_payment_transaction_id')->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'suitpay_payment_token',
                'suitpay_payment_id',
                'suitpay_payment_order_id',
                'suitpay_payment_code',
                'suitpay_payment_transaction_id',
            ]);
        });
    }
};
