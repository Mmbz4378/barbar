<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

interface SmsGatewayInterface
{
    /** @return array{ok:bool,ref:?string,error:?string} نتیجهٔ ارسال */
    public function send(string $e164Phone, string $message): array;

    /**
     * Sends through a pre-approved template ("الگو"/pattern) registered in the
     * provider's panel.
     *
     * This is not an optimisation — Iranian carriers reject free-text
     * transactional messages (OTP codes above all) on shared service lines,
     * so an OTP sent via the plain text endpoint silently never arrives
     * unless the salon bought a dedicated line. Doc 8.7 flags this as the
     * failure you only discover when the first code doesn't show up.
     *
     * @param string[] $args template variables, in the order defined in the panel
     * @return array{ok:bool,ref:?string,error:?string}
     */
    public function sendPattern(string $e164Phone, string $patternId, array $args): array;

    public function name(): string;
}
