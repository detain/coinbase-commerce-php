<?php
namespace CoinbaseCommerce\Exceptions;

class SignatureVerificationException extends CoinbaseException
{
    public function __construct($signatureOrMessage, $payload = null)
    {
        if ($payload !== null) {
            $message = sprintf('No signatures found matching the expected signature %s for payload %s', $signatureOrMessage, $payload);
        } else {
            $message = $signatureOrMessage;
        }

        parent::__construct($message);
    }
}
