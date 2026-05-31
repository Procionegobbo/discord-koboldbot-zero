<?php

namespace App\Handlers;

use JsonException;
use Throwable;

trait HasKoboldErrors
{
    private function errorMessage(Throwable $e): string
    {
        return match (true) {
            str_starts_with($e->getMessage(), 'rate_limit') => 'Troppi kobold evocati! Riprova tra un secondo.',
            str_starts_with($e->getMessage(), 'http_error') => 'Il portale dei kobold è temporaneamente chiuso. Riprova più tardi.',
            $e instanceof JsonException => 'Il messaggio del kobold era illeggibile. Riprova.',
            default => 'Evocazione fallita per cause misteriose. Riprova.',
        };
    }
}
