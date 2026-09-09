<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ownership for the shared Media library. Admin uploads leave both
     * null (a global asset everyone with manage_media sees). A club user's
     * uploads are stamped with their club — and club users only ever see /
     * pick media stamped with their own club.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->foreignId('club_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('club_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_id');
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
