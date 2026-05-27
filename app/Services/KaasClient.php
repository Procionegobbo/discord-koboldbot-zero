<?php

namespace App\Services;

use React\Http\Browser;
use React\Promise\PromiseInterface;
use RuntimeException;

class KaasClient
{
    public function __construct(private readonly Browser $browser) {}

    /** @return PromiseInterface<string> resolves with raw JSON body */
    public function generate(): PromiseInterface
    {
        return $this->browser->post(
            config('discord.kaas_url'),
            ['Content-Type' => 'application/json'],
            json_encode(['language' => 'it']),
        )->then(function ($response) {
            $status = $response->getStatusCode();

            if ($status === 429) {
                throw new RuntimeException('rate_limit');
            }

            if ($status < 200 || $status >= 300) {
                throw new RuntimeException("http_error:{$status}");
            }

            return (string) $response->getBody();
        });
    }
}
