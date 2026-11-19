<?php

namespace App\Controller;

use App\Entity\Client;
use App\Service\IA\AssistantVoyage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Chatbot client (widget présent sur toutes les pages du site). */
#[Route('/assistant')]
class AssistantController extends AbstractController
{
    private const LONGUEUR_MAX   = 500;   // caractères par question
    private const HISTORIQUE_MAX = 10;    // messages gardés en session (5 échanges)
    private const QUOTA          = 20;    // questions par session…
    private const FENETRE        = 600;   // …par tranche de 10 minutes

    #[Route('/message', name: 'app_assistant_message', methods: ['POST'])]
    public function message(Request $request, AssistantVoyage $assistant): JsonResponse
    {
        if (!$this->isCsrfTokenValid('assistant', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['erreur' => 'Session expirée, rechargez la page.'], 403);
        }
        $question = trim((string) (json_decode($request->getContent(), true)['message'] ?? ''));
        if ($question === '') {
            return $this->json(['erreur' => 'Écrivez votre question.'], 422);
        }
        if (mb_strlen($question) > self::LONGUEUR_MAX) {
            return $this->json(['erreur' => sprintf('Question trop longue (%d caractères maximum).', self::LONGUEUR_MAX)], 422);
        }
        if (!$assistant->isDisponible()) {
            return $this->json(['reponse' => 'L\'assistant n\'est pas encore activé sur ce site. Consultez la page Voyages ou contactez-nous.']);
        }

        $session = $request->getSession();
        $quota   = array_filter($session->get('assistant_quota', []), fn(int $t) => $t > time() - self::FENETRE);
        if (count($quota) >= self::QUOTA) {
            return $this->json(['erreur' => 'Vous avez posé beaucoup de questions. Réessayez dans quelques minutes.'], 429);
        }
        $quota[] = time();
        $session->set('assistant_quota', array_values($quota));

        $client     = $this->getUser() instanceof Client ? $this->getUser() : null;
        $historique = $session->get('assistant_historique', []);

        $reponse = $assistant->repondre($historique, $question, $client);

        $historique[] = ['role' => 'user', 'content' => $question];
        $historique[] = ['role' => 'assistant', 'content' => $reponse];
        $session->set('assistant_historique', array_slice($historique, -self::HISTORIQUE_MAX));

        return $this->json(['reponse' => $reponse]);
    }

    #[Route('/reinitialiser', name: 'app_assistant_reset', methods: ['POST'])]
    public function reinitialiser(Request $request): JsonResponse
    {
        if ($this->isCsrfTokenValid('assistant', $request->headers->get('X-CSRF-Token'))) {
            $request->getSession()->remove('assistant_historique');
        }
        return $this->json(['ok' => true]);
    }
}
