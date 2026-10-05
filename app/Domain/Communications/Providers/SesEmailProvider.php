<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\EmailChannelProvider;
use App\Domain\Communications\Data\OutboundCommunication;
use App\Domain\Communications\Data\ProviderSendResult;
use App\Domain\Communications\Exceptions\CommunicationProviderException;
use Aws\Exception\AwsException;
use Aws\Ses\SesClient;
use Throwable;

class SesEmailProvider implements EmailChannelProvider
{
    public function __construct(private readonly SesClient $client) {}

    public function name(): string
    {
        return config('communications.email_transport_mode') === 'fake' ? 'fake' : 'ses';
    }

    public static function configured(): bool
    {
        return filled(config('services.ses.key')) && filled(config('services.ses.secret'))
            && filled(config('services.ses.region')) && filter_var(config('communications.ses.from_address'), FILTER_VALIDATE_EMAIL)
            && ! str_ends_with((string) config('communications.ses.from_address'), '.local');
    }

    public function send(OutboundCommunication $message): ProviderSendResult
    {
        if ($this->name() === 'fake') {
            return new ProviderSendResult('fake-email-'.substr($message->idempotencyKey, 0, 24));
        }
        if (! self::configured()) {
            throw new CommunicationProviderException('provider_not_configured', false);
        }
        $source = config('communications.ses.from_address');
        $name = trim(str_replace(["\r", "\n", '<', '>', '"'], '', (string) config('communications.ses.from_name')));
        $payload = [
            'Source' => $name !== '' ? '"'.$name.'" <'.$source.'>' : $source,
            'Destination' => ['ToAddresses' => [$message->destination]],
            'Message' => ['Subject' => ['Charset' => 'UTF-8', 'Data' => $message->subject], 'Body' => [
                'Text' => ['Charset' => 'UTF-8', 'Data' => $message->body],
                'Html' => ['Charset' => 'UTF-8', 'Data' => nl2br(e($message->body))],
            ]],
            'Tags' => [['Name' => 'client_message', 'Value' => $message->idempotencyKey]],
        ];
        if (filled(config('communications.ses.configuration_set'))) {
            $payload['ConfigurationSetName'] = config('communications.ses.configuration_set');
        }
        try {
            $result = $this->client->sendEmail($payload);
        } catch (AwsException $error) {
            $code = $error->getAwsErrorCode();
            // SES SendEmail has no idempotency token. Only an explicit rejection
            // is safe to retry; a network timeout or 5xx may already be accepted.
            if (in_array($code, ['Throttling', 'ThrottlingException', 'TooManyRequestsException'], true)) {
                throw new CommunicationProviderException('ses_throttled', true);
            }
            $safe = match ($code) {
                'AccessDenied', 'AccessDeniedException', 'InvalidClientTokenId', 'SignatureDoesNotMatch', 'UnrecognizedClientException', 'ExpiredToken' => 'ses_authentication_failed',
                'MessageRejected', 'MailFromDomainNotVerifiedException' => 'ses_sender_rejected',
                'ConfigurationSetDoesNotExistException' => 'ses_configuration_set_missing',
                'AccountSendingPausedException', 'ConfigurationSetSendingPausedException' => 'ses_sending_paused',
                default => 'ses_outcome_unknown',
            };
            throw new CommunicationProviderException($safe, false);
        } catch (Throwable) {
            throw new CommunicationProviderException('ses_outcome_unknown', false);
        }
        if (blank($result['MessageId'] ?? null)) {
            throw new CommunicationProviderException('ses_outcome_unknown', false);
        }

        return new ProviderSendResult((string) $result['MessageId'], $result['@metadata']['headers']['x-amzn-requestid'] ?? null);
    }
}
