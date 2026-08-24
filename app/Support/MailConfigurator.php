<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Applies the admin-configurable (Settings > Mail Server) Outlook/Exchange
 * connection to Laravel's mail config at runtime, so it can change without
 * a deploy. Shared between AppServiceProvider::boot() (every request/job)
 * and the Settings page (so a save takes effect immediately, including for
 * the "send test email" action).
 */
class MailConfigurator
{
    public static function apply(): void
    {
        $fromEmail = Setting::get('mail_from_email', config('mail.from.address'));
        $fromName = Setting::get('mail_from_name', config('mail.from.name'));
        $replyTo = Setting::get('mail_reply_to', '');

        config([
            'mail.from.address' => $fromEmail,
            'mail.from.name'    => $fromName,
            'mail.reply_to'     => filled($replyTo) ? [['address' => $replyTo, 'name' => $fromName]] : [],
        ]);

        if (Setting::get('mail_send_enabled', 'false') !== 'true') {
            return;
        }

        if (Setting::get('mail_transport', 'graph') === 'graph') {
            self::applyGraph();
        } else {
            self::applySmtp();
        }
    }

    /**
     * App-only OAuth2 (client credentials) via Microsoft Graph's
     * /users/{mailbox}/sendMail — this tenant has SmtpClientAuthentication
     * disabled, so plain SMTP AUTH is rejected regardless of credentials.
     */
    private static function applyGraph(): void
    {
        $mailbox = Setting::get('mail_username');
        $tenantId = Setting::get('graph_tenant_id');
        $clientId = Setting::get('graph_client_id');
        $clientSecret = Setting::getDecrypted('graph_client_secret');

        if (blank($tenantId) || blank($clientId) || blank($clientSecret) || blank($mailbox)) {
            return;
        }

        config([
            'mail.default' => 'graph',
            'mail.mailers.graph.transport'    => 'graph',
            'mail.mailers.graph.tenant_id'    => $tenantId,
            'mail.mailers.graph.client_id'    => $clientId,
            'mail.mailers.graph.client_secret' => $clientSecret,
            'mail.mailers.graph.mailbox'      => $mailbox,
        ]);
    }

    private static function applySmtp(): void
    {
        $password = Setting::getDecrypted('mail_password');

        if (blank(Setting::get('mail_host')) || blank(Setting::get('mail_username')) || blank($password)) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host'      => Setting::get('mail_host'),
            'mail.mailers.smtp.port'      => (int) Setting::get('mail_port', 587),
            'mail.mailers.smtp.encryption' => Setting::get('mail_encryption', 'tls') ?: null,
            'mail.mailers.smtp.username'  => Setting::get('mail_username'),
            'mail.mailers.smtp.password'  => $password,
        ]);
    }
}
