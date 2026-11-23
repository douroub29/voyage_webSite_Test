<?php

namespace App\Exception;

/** Le gel de prix est impossible. Le message est affiché à l'utilisateur. */
class GelPrixException extends \DomainException
{
    public static function voyageNonReservable(?string $motif): self
    {
        return new self($motif ?? 'Ce voyage n\'est plus réservable : son prix ne peut pas être gelé.');
    }

    public static function dejaGele(\DateTimeInterface $expireLe): self
    {
        return new self(sprintf('Vous avez déjà gelé le prix de ce voyage jusqu\'au %s.', $expireLe->format('d/m/Y à H:i')));
    }

    public static function departTropProche(): self
    {
        return new self('Le départ est trop proche pour geler le prix : réservez directement.');
    }
}
