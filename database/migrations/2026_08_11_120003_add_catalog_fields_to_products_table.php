<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('status')->default('Active')->after('name');
            $table->text('description')->nullable()->after('status');
            $table->string('image_link')->nullable()->after('description');
            $table->text('gallery_links')->nullable()->after('image_link');
            $table->string('year')->nullable()->after('gallery_links');
            $table->string('available_until_year')->nullable()->after('year');
            $table->string('total_look')->nullable()->after('available_until_year');
            $table->decimal('weight', 8, 2)->nullable()->after('total_look');
            $table->string('color1_code')->nullable()->after('weight');
            $table->string('color1_label')->nullable()->after('color1_code');
            $table->string('color2_code')->nullable()->after('color1_label');
            $table->string('color2_label')->nullable()->after('color2_code');
            $table->foreignId('co_sponsorship_id')->nullable()->after('color2_label')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('co_sponsorship_id');
            $table->dropColumn([
                'status', 'description', 'image_link', 'gallery_links', 'year',
                'available_until_year', 'total_look', 'weight',
                'color1_code', 'color1_label', 'color2_code', 'color2_label',
            ]);
        });
    }
};
