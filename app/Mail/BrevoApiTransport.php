<?php

namespace App\Mail;

use Illuminate\Http\Client\Factory as HttpFactory;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through Brevo's transactional HTTP API (POST /v3/smtp/email over
 * HTTPS) instead of SMTP. Render's free tier blocks/throttles outbound SMTP
 * (port 587), which made the synchronous reset-password send hang until the
 * gateway 504'd; HTTPS on 443 is not blocked, so this returns promptly.
 *
 * Built on Laravel's HTTP client (Guzzle, already a dependency) so no extra
 * package is required.
 */
class BrevoApiTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $apiKey,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        $payload = [
            'sender' => $this->addressToArray($envelope->getSender()),
            'to' => array_map(
                fn (Address $a) => $this->addressToArray($a),
                $email->getTo() ?: $envelope->getRecipients()
            ),
            'subject' => $email->getSubject(),
        ];

        if ($html = $email->getHtmlBody()) {
            $payload['htmlContent'] = $html;
        }
        if ($text = $email->getTextBody()) {
            $payload['textContent'] = $text;
        }
        if (! isset($payload['htmlContent']) && ! isset($payload['textContent'])) {
            // Brevo requires at least one body part.
            $payload['textContent'] = ' ';
        }

        if ($cc = $email->getCc()) {
            $payload['cc'] = array_map(fn (Address $a) => $this->addressToArray($a), $cc);
        }
        if ($bcc = $email->getBcc()) {
            $payload['bcc'] = array_map(fn (Address $a) => $this->addressToArray($a), $bcc);
        }
        if ($replyTo = $email->getReplyTo()) {
            $payload['replyTo'] = $this->addressToArray($replyTo[0]);
        }

        $this->http
            ->withHeaders([
                'api-key' => $this->apiKey,
                'accept' => 'application/json',
            ])
            ->timeout(15)
            ->post(self::ENDPOINT, $payload)
            ->throw();
    }

    private function addressToArray(Address $address): array
    {
        $out = ['email' => $address->getAddress()];
        if ($name = $address->getName()) {
            $out['name'] = $name;
        }

        return $out;
    }

    public function __toString(): string
    {
        return 'brevo-api';
    }
}
