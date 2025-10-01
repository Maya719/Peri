<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('default_gateway')->constrained('payment_gateways')->cascadeOnDelete->after('plan_id')->nullable();
            $table->string('gateway_subscription_id')->nullable()->after('default_gateway');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('default_gateway');
            $table->dropColumn('gateway_subscription_id');
        });
    }
};
