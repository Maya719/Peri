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
        Schema::table('bank_details', function (Blueprint $table) {
            $table->foreignId('statutory_component_category_id')->nullable()->constrained('salary_component_categories')->after('probation_salary')->nullOnDelete();
            $table->decimal('statutory_component_percentage', 5, 2)->nullable()->after('statutory_component_category_id');
            $table->decimal('statutory_component_amount', 15, 2)->nullable()->after('statutory_component_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_details', function (Blueprint $table) {
            $table->dropForeign(['statutory_component_category_id']);
            $table->dropColumn('statutory_component_category_id');
            $table->dropColumn('statutory_component_percentage');
            $table->dropColumn('statutory_component_amount');
        });
    }
};
