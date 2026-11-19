<?php

namespace App\Enum;

enum StatutPaiement: string
{
    case EnAttente = 'en_attente';
    case Complete  = 'complete';
    case Rembourse = 'rembourse';
    case Echoue    = 'echoue';

    public function label(): string
    {
        return match($this) {
            self::EnAttente => 'En attente',
            self::Complete  => 'Complété',
            self::Rembourse => 'Remboursé',
            self::Echoue    => 'Échoué',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::EnAttente => 'badge-warning',
            self::Complete  => 'badge-success',
            self::Rembourse => 'badge-info',
            self::Echoue    => 'badge-danger',
        };
    }
}
