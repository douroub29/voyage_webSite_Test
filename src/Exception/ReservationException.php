<?php

namespace App\Exception;

/**
 * Violation d'une règle de gestion liée aux réservations.
 * Le message est destiné à être affiché à l'utilisateur.
 */
class ReservationException extends \DomainException
{
    public static function voyageInactif(): self
    {
        return new self('Ce voyage n’est plus proposé à la réservation.');
    }

    public static function voyageParti(): self
    {
        return new self('Les réservations sont fermées : la date de départ de ce voyage est atteinte.');
    }

    public static function placesInsuffisantes(int $restantes): self
    {
        return $restantes === 0
            ? new self('Ce voyage est complet.')
            : new self(sprintf('Il ne reste que %d place(s) pour ce voyage.', $restantes));
    }

    public static function nombrePersonnesInvalide(): self
    {
        return new self('Le nombre de personnes doit être au moins 1.');
    }

    public static function dejaAnnulee(): self
    {
        return new self('Cette réservation est déjà annulée.');
    }

    public static function dejaConfirmee(): self
    {
        return new self('Cette réservation est déjà confirmée.');
    }

    public static function annuleeNonModifiable(): self
    {
        return new self('Une réservation annulée ne peut plus être confirmée ni payée.');
    }
}
