<?php

namespace App\Support;

use RuntimeException;

/**
 * Verificações de conformidade legal da agência.
 *
 * Em produção, a ausência de AMI é um erro fatal na arranque da aplicação —
 * preferimos um site em baixo a um site em incumprimento da Lei n.º 15/2013.
 */
final class AgencyCompliance
{
    /**
     * @throws RuntimeException se o ambiente for produção e o AMI estiver vazio
     */
    public static function assertAmi(string $environment): bool
    {
        if ($environment === 'production' && blank(config('agency.ami'))) {
            throw new RuntimeException(
                'AGENCY_AMI está vazio: o número de licença AMI é obrigatório em produção (Lei n.º 15/2013).'
            );
        }

        return true;
    }
}
