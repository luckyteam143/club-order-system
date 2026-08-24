<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Not wired to actual sending yet (that's the planned Exchange
     * integration) — these just configure the "From" identity so mail
     * config is ready ahead of that.
     */
    public function up(): void
    {
        $rows = [
            'mail_from_email' => 'noreply@macronstore.ca',
            'mail_from_name'  => 'Macron Club Order System',
            'mail_reply_to'   => '',
        ];

        foreach ($rows as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['mail_from_email', 'mail_from_name', 'mail_reply_to'])->delete();
    }
};
