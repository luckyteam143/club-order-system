<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Outlook/Exchange (Microsoft 365) SMTP connection used to actually send
     * order emails, gated by mail_send_enabled so it can be toggled from
     * Settings without a deploy. mail_password is set separately (encrypted)
     * rather than seeded here, so the credential never sits in plaintext in
     * a migration file.
     */
    public function up(): void
    {
        $rows = [
            'mail_send_enabled' => 'false',
            'mail_host'         => 'smtp.office365.com',
            'mail_port'         => '587',
            'mail_encryption'   => 'tls',
            'mail_username'     => 'orders@macronstore.ca',
            'mail_password'     => '',
        ];

        foreach ($rows as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        // Exchange Online rejects/flags SMTP AUTH mail whose From doesn't
        // match the authenticated mailbox, so align the still-default From
        // identity with the mailbox this now sends through.
        if (DB::table('settings')->where('key', 'mail_from_email')->value('value') === 'noreply@macronstore.ca') {
            DB::table('settings')->where('key', 'mail_from_email')->update(['value' => 'orders@macronstore.ca', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'mail_send_enabled', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password',
        ])->delete();

        if (DB::table('settings')->where('key', 'mail_from_email')->value('value') === 'orders@macronstore.ca') {
            DB::table('settings')->where('key', 'mail_from_email')->update(['value' => 'noreply@macronstore.ca', 'updated_at' => now()]);
        }
    }
};
