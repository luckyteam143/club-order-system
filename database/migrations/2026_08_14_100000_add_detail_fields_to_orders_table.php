<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('team_po')->nullable()->after('package_id');
            $table->string('coach_manager')->nullable()->after('team_po');
            $table->text('shipping_address')->nullable()->after('coach_manager');
            $table->string('phone')->nullable()->after('shipping_address');
            $table->string('email')->nullable()->after('phone');

            // Office-use fields — admin/sub-admin only.
            $table->date('order_date')->nullable()->after('notes');
            $table->string('b2b_number')->nullable()->after('order_date');
            $table->string('qb_invoice')->nullable()->after('b2b_number');
            $table->string('brochure_link')->nullable()->after('qb_invoice');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'team_po', 'coach_manager', 'shipping_address', 'phone', 'email',
                'order_date', 'b2b_number', 'qb_invoice', 'brochure_link',
            ]);
        });
    }
};
