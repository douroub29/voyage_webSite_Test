<?php

namespace App\EventSubscriber;

use App\Entity\Admin;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Un administrateur qui se connecte (depuis /connexion ou /admin/connexion)
 * arrive directement sur son tableau de bord, jamais sur l'accueil du site.
 */
class AdminLoginRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator) {}

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof Admin || in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('admin_dashboard')));
        }
    }
}
