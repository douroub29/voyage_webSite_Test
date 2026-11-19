<?php

namespace App\Enum;

enum MethodePaiement: string
{
    case Carte    = 'carte';
    case Virement = 'virement';
    case Paypal   = 'paypal';
    case Especes  = 'especes';

    public function label(): string
    {
        return match($this) {
            self::Carte    => 'Carte bancaire',
            self::Virement => 'Virement bancaire',
            self::Paypal   => 'PayPal',
            self::Especes  => 'Espèces',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Carte    => 'fa-credit-card',
            self::Virement => 'fa-university',
            self::Paypal   => 'fa-paypal',
            self::Especes  => 'fa-money-bill',
        };
    }
}
