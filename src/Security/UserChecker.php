<?php

namespace App\Security;

use App\Entity\Client;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Client) {
            return;
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte n\'est pas encore activé. Vérifiez votre boîte email et cliquez sur le lien d\'activation.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void {}
}
