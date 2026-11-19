<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/** Sous /api, toute erreur (route inconnue, méthode non autorisée, erreur serveur) est renvoyée en JSON. */
class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onKernelException'];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();
        $statut    = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        $message   = match (true) {
            $statut === 404 => 'Ressource introuvable.',
            $statut === 405 => 'Méthode HTTP non autorisée.',
            $statut >= 500  => 'Erreur interne du serveur.',
            default         => $exception->getMessage(),
        };

        $event->setResponse(new JsonResponse(
            ['erreur' => $message, 'statut' => $statut],
            $statut,
            $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [],
        ));
    }
}
