<?php

declare(strict_types=1);

namespace Game\Identity\Domain;

interface SocialIdentityVerifier
{
    /**
     * @return array{provider_id: string, email: string|null}
     */
    public function verify(string $provider, string $identityToken): array;
}
