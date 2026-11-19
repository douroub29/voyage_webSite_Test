<?php

namespace App\Exception;

use App\Service\VoucherManager;

/** Le client n'a pas droit à un voucher. Le message est affiché à l'utilisateur. */
class VoucherException extends \DomainException
{
    public static function nonEligible(int $manquantes): self
    {
        return new self(sprintf(
            'Le voucher est réservé aux clients ayant %d réservations confirmées. Encore %d réservation(s) pour y avoir droit.',
            VoucherManager::SEUIL_RESERVATIONS,
            $manquantes
        ));
    }

    public static function dejaUtilise(int $manquantes): self
    {
        return new self(sprintf(
            'Votre voucher a déjà été utilisé. Encore %d réservation(s) confirmée(s) pour en gagner un nouveau.',
            $manquantes
        ));
    }
}
