<?php

use App\Models\LogoStock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logo_stocks', function (Blueprint $table) {
            $table->string('width')->nullable()->after('size');
            $table->string('height')->nullable()->after('width');
            $table->text('notes')->nullable()->after('vector_file_link');
        });

        // Add "sponsor" alongside the existing logo/numbers stock types.
        DB::statement("ALTER TABLE logo_stocks MODIFY COLUMN logo_stock_type ENUM('logo', 'numbers', 'sponsor') NOT NULL DEFAULT 'logo'");

        // Best-effort split of the old free-form "size" string into width /
        // height: anything shaped like "10x10", "10 × 10cm", "10*10" becomes
        // two fields; anything else lands wholesale in width.
        LogoStock::whereNotNull('size')->where('size', '!=', '')->get(['id', 'size'])
            ->each(function (LogoStock $row) {
                $parts = preg_split('/\s*[x×*]\s*/iu', (string) $row->size, 2);

                $row->forceFill([
                    'width'  => trim($parts[0] ?? '') ?: null,
                    'height' => trim($parts[1] ?? '') ?: null,
                ])->saveQuietly();
            });

        Schema::table('logo_stocks', function (Blueprint $table) {
            $table->dropColumn('size');
        });
    }

    public function down(): void
    {
        Schema::table('logo_stocks', function (Blueprint $table) {
            $table->string('size')->nullable()->after('logo_name');
        });

        LogoStock::where(function ($q) {
            $q->whereNotNull('width')->orWhereNotNull('height');
        })->get(['id', 'width', 'height'])->each(function (LogoStock $row) {
            $combined = trim(implode(' x ', array_filter([$row->width, $row->height])));

            $row->forceFill(['size' => $combined ?: null])->saveQuietly();
        });

        DB::statement("ALTER TABLE logo_stocks MODIFY COLUMN logo_stock_type ENUM('logo', 'numbers') NOT NULL DEFAULT 'logo'");

        Schema::table('logo_stocks', function (Blueprint $table) {
            $table->dropColumn(['width', 'height', 'notes']);
        });
    }
};
