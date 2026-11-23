<?php

namespace App\Controller;

use App\Service\IA\PlanificateurAmbiance;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Planificateur de voyage par « ambiance » (description libre → voyage + hôtel + programme modifiable). */
#[Route('/planificateur')]
class PlanificateurController extends AbstractController
{
    private const QUOTA   = 6;    // plans générés par session…
    private const FENETRE = 600;  // …par tranche de 10 minutes

    private const EXEMPLES = [
        'Je veux un endroit calme au bord de la mer, avec de la bonne gastronomie, à moins de 3h de vol et un budget de 2000 €',
        'Une escapade romantique et culturelle en mai, musées et petits restaurants',
        'Dépaysement total, nature et temples, je suis prêt à faire un long vol',
        'Luxe et shopping au soleil en avril',
    ];

    #[Route('', name: 'app_planificateur', methods: ['GET', 'POST'])]
    public function index(Request $request, PlanificateurAmbiance $planificateur): Response
    {
        $envie  = trim((string) $request->request->get('envie', $request->query->get('envie', '')));
        $plan   = null;
        $erreur = null;

        if ($request->isMethod('POST')) {
            $session = $request->getSession();
            $quota   = array_filter($session->get('planificateur_quota', []), fn(int $t) => $t > time() - self::FENETRE);

            if (!$this->isCsrfTokenValid('planificateur', (string) $request->request->get('_token'))) {
                $erreur = 'Session expirée, veuillez réessayer.';
            } elseif (mb_strlen($envie) < 10) {
                $erreur = 'Décrivez votre envie en quelques mots (10 caractères minimum).';
            } elseif (mb_strlen($envie) > PlanificateurAmbiance::LONGUEUR_MAX) {
                $erreur = sprintf('Description trop longue (%d caractères maximum).', PlanificateurAmbiance::LONGUEUR_MAX);
            } elseif (count($quota) >= self::QUOTA) {
                $erreur = 'Vous avez généré beaucoup de voyages. Réessayez dans quelques minutes.';
            } else {
                $quota[] = time();
                $session->set('planificateur_quota', array_values($quota));

                $plan = $planificateur->planifier($envie);
                if ($plan === null) {
                    $erreur = 'Aucun voyage n\'est réservable pour le moment.';
                }
            }
        }

        return $this->render('planificateur/index.html.twig', [
            'envie'    => $envie,
            'plan'     => $plan,
            'erreur'   => $erreur,
            'exemples' => self::EXEMPLES,
        ], new Response(status: $erreur ? 422 : 200));
    }
}
