<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Microsoft Graph app-only auth (client credentials) for sending mail —
     * this tenant has SmtpClientAuthentication disabled, so Graph's
     * /users/{mailbox}/sendMail sidesteps SMTP AUTH entirely. graph_client_id
     * and graph_tenant_id are app identifiers, not secrets, so they're safe
     * to seed here; graph_client_secret is set separately (encrypted) so it
     * never sits in plaintext in a migration file.
     */
    public function up(): void
    {
        $rows = [
            'mail_transport'      => 'graph',
            'graph_tenant_id'     => '822ca099-aa9f-43c7-a9ef-f4e794750d85',
            'graph_client_id'     => 'ebc781e9-ac04-481d-be3b-d288b14bf3bb',
            'graph_client_secret' => '',
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
        DB::table('settings')->whereIn('key', [
            'mail_transport', 'graph_tenant_id', 'graph_client_id', 'graph_client_secret',
        ])->delete();
    }
};
