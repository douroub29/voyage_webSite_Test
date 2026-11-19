<?php

namespace App\Enum;

enum StatutAvis: string
{
    case EnAttente = 'en_attente';
    case Publie    = 'publie';
    case Rejete    = 'rejete';

    public function label(): string
    {
        return match($this) {
            self::EnAttente => 'En attente',
            self::Publie    => 'Publié',
            self::Rejete    => 'Rejeté',
        };
    }
}
