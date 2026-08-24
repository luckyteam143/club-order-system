<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends via Microsoft Graph (POST /users/{mailbox}/sendMail) using an
 * app-only OAuth2 client-credentials token, instead of SMTP AUTH — this
 * tenant has SmtpClientAuthentication disabled, which Graph sidesteps
 * entirely since it isn't SMTP.
 */
class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $mailbox,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $toRecipients = $this->formatAddresses($email->getTo());

        if (empty($toRecipients)) {
            throw new TransportException('Microsoft Graph mail transport requires at least one To recipient.');
        }

        $payload = [
            'message' => [
                'subject' => $email->getSubject(),
                'body' => [
                    'contentType' => $email->getHtmlBody() ? 'HTML' : 'Text',
                    'content' => $email->getHtmlBody() ?? $email->getTextBody() ?? '',
                ],
                'toRecipients' => $toRecipients,
                'ccRecipients' => $this->formatAddresses($email->getCc()),
                'bccRecipients' => $this->formatAddresses($email->getBcc()),
                'replyTo' => $this->formatAddresses($email->getReplyTo()),
            ],
            'saveToSentItems' => true,
        ];

        $response = Http::withToken($this->getAccessToken())
            ->post("https://graph.microsoft.com/v1.0/users/{$this->mailbox}/sendMail", $payload);

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->body();

            throw new TransportException("Microsoft Graph sendMail failed ({$response->status()}): {$error}");
        }
    }

    private function formatAddresses(array $addresses): array
    {
        return collect($addresses)
            ->map(fn (Address $address) => [
                'emailAddress' => array_filter([
                    'address' => $address->getAddress(),
                    'name'    => $address->getName() ?: null,
                ]),
            ])
            ->values()
            ->all();
    }

    private function getAccessToken(): string
    {
        $cacheKey = 'graph_mail_token:'.md5($this->tenantId.$this->clientId);

        return Cache::remember($cacheKey, 3000, function () {
            $response = Http::asForm()->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope'         => 'https://graph.microsoft.com/.default',
                'grant_type'    => 'client_credentials',
            ]);

            if ($response->failed()) {
                $error = $response->json('error_description') ?? $response->body();

                throw new TransportException("Microsoft Graph token request failed ({$response->status()}): {$error}");
            }

            return $response->json('access_token');
        });
    }

    public function __toString(): string
    {
        return 'graph';
    }
}
