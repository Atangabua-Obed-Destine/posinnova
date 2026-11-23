<?php

namespace App\Utils;

/** An answer from E-Ride that was not a success (or no answer at all: code "network", status 0). */
class ChannelException extends \RuntimeException
{
    public $errorCode;

    public $status;

    public $retryAfter = 0;

    public $data = [];

    public function __construct($code, $message, $status)
    {
        parent::__construct($message !== '' ? $message : $code);
        $this->errorCode = (string) $code;
        $this->status = (int) $status;
    }

    /** E-Ride removed the link (401 link_revoked) or the key is wrong. */
    public function isRevoked()
    {
        return $this->status === 401;
    }

    /** Worth trying again later (no answer, rate limit, server trouble). */
    public function isTemporary()
    {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500;
    }
}
