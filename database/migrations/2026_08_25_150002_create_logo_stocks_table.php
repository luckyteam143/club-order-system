<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logo_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            // The same barcode value is printed/assigned once per club and
            // repeated on every logo-stock row that club owns — scanning it
            // resolves the club, then every row for that club is shown, so
            // duplicates across rows are expected, not a data error.
            $table->string('barcode')->index();
            $table->foreignId('logo_type_id')->constrained()->cascadeOnDelete();
            // Box number the physical logos are stored in.
            $table->string('location')->nullable();
            $table->unsignedInteger('qty')->default(0);
            $table->string('logo_name');
            $table->string('image')->nullable();
            $table->string('vector_file_link')->nullable();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            // Manual sort order (1, 2, 3…) controlling which row shows first.
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['club_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logo_stocks');
    }
};
