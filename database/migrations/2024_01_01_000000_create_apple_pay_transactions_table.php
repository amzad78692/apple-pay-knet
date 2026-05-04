<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApplePayTransactionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('apple_pay_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_id')->index();
            $table->string('amount');                 // KWD decimal amount as string (e.g. "5.250")
            $table->string('currency', 3)->default('414');
            $table->string('apple_transaction_id')->nullable();
            $table->string('knet_transaction_id')->nullable();
            $table->enum('status', ['pending', 'authorized', 'captured', 'failed'])
                  ->default('pending');
            $table->string('response_code')->nullable();
            $table->string('auth_code')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apple_pay_transactions');
    }
}
