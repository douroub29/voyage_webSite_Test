<?php

namespace App\EventSubscriber;

use App\Service\VoyageClotureService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Passe automatiquement en "inactif" les voyages dont la date de départ est
 * atteinte, sans attendre une action de l'admin ni une tâche planifiée.
 * Le contrôle est fait au plus une fois par minute et par processus PHP.
 */
class VoyageClotureSubscriber implements EventSubscriberInterface
{
    private const INTERVALLE_SECONDES = 60;

    private static ?int $dernierControle = null;

    public function __construct(
        private readonly VoyageClotureService $clotureService,
        private readonly LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        // Après le routeur (32) et avant le pare-feu (8) : les pages affichent déjà le bon statut.
        return [KernelEvents::REQUEST => ['onKernelRequest', 16]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $route = (string) $event->getRequest()->attributes->get('_route');
        if ($route === '' || str_starts_with($route, '_')) {
            return; // profiler, wdt, assets...
        }
        if (self::$dernierControle !== null && time() - self::$dernierControle < self::INTERVALLE_SECONDES) {
            return;
        }
        self::$dernierControle = time();

        try {
            foreach ($this->clotureService->cloturer() as $ligne) {
                $this->logger->info('Voyage #{id} désactivé automatiquement (départ atteint).', ['id' => $ligne['voyage']->getId()]);
            }
        } catch (\Throwable $e) {
            // Ne jamais bloquer la navigation à cause de cette tâche de fond.
            $this->logger->error('Clôture automatique des voyages impossible : ' . $e->getMessage());
        }
    }
}
