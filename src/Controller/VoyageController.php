<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Voyage;
use App\Enum\MethodePaiement;
use App\Exception\GelPrixException;
use App\Repository\HotelRepository;
use App\Repository\SocieteRepository;
use App\Repository\VoyageRepository;
use App\Service\Contexte\ContexteSejourService;
use App\Service\Prix\GelPrixManager;
use App\Service\Prix\PrevisionPrix;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/voyages')]
class VoyageController extends AbstractController
{
    #[Route('', name: 'app_voyages')]
    public function index(Request $request, VoyageRepository $repo, SocieteRepository $societeRepo): Response
    {
        $destination = trim((string) $request->query->get('destination')) ?: null;
        $societe     = trim((string) $request->query->get('societe')) ?: null;
        $dateDepart  = $request->query->get('date_depart') ? new \DateTime($request->query->get('date_depart')) : null;
        $budget      = $request->query->get('budget') ? (float) $request->query->get('budget') : null;

        $voyages = $repo->search($destination, $dateDepart, $budget, $societe);

        return $this->render('voyage/index.html.twig', [
            'voyages'     => $voyages,
            'destination' => $destination,
            'societe'     => $societe,
            'societes'    => $societeRepo->findAllOrdered(),
            'dateDepart'  => $dateDepart,
            'budget'      => $budget,
        ]);
    }

    #[Route('/{id}', name: 'app_voyage_show', requirements: ['id' => '\d+'])]
    public function show(Voyage $voyage, HotelRepository $hotelRepo, PrevisionPrix $previsionPrix, GelPrixManager $gelPrixManager): Response
    {
        $hotels = $hotelRepo->findByDestination($voyage->getDestination()->getId());
        $client = $this->getUser() instanceof Client ? $this->getUser() : null;

        return $this->render('voyage/show.html.twig', [
            'voyage'     => $voyage,
            'hotels'     => $hotels,
            'prevision'  => $voyage->isReservable() ? $previsionPrix->prevoir($voyage) : null,
            'gelActif'   => $client ? $gelPrixManager->getGelActif($client, $voyage) : null,
            'fraisGel'   => GelPrixManager::FRAIS,
            'dureeGel'   => GelPrixManager::DUREE_JOURS,
            'methodes'   => MethodePaiement::cases(),
        ]);
    }

    /** Fragment HTML chargé en différé : météo historique, jours fériés, affluence, événements. */
    #[Route('/{id}/contexte', name: 'app_voyage_contexte', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function contexte(Voyage $voyage, ContexteSejourService $contexte): Response
    {
        return $this->render('voyage/_contexte.html.twig', [
            'voyage'   => $voyage,
            'contexte' => $contexte->pour($voyage->getDestination(), $voyage->getDateDepart(), $voyage->getDateRetour()),
        ]);
    }

    /** Paiement des frais de gel : le prix par personne est bloqué pendant GelPrixManager::DUREE_JOURS jours. */
    #[Route('/{id}/geler-prix', name: 'app_voyage_geler_prix', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_CLIENT')]
    public function gelerPrix(Voyage $voyage, Request $request, GelPrixManager $gelPrixManager): Response
    {
        $retour = $this->redirectToRoute('app_voyage_show', ['id' => $voyage->getId()]);

        if (!$this->isCsrfTokenValid('geler-prix-' . $voyage->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée, veuillez réessayer.');
            return $retour;
        }
        $methode = MethodePaiement::tryFrom((string) $request->request->get('methode', 'carte'));
        if ($methode === null || $methode === MethodePaiement::Especes) {
            $this->addFlash('danger', 'Méthode de paiement invalide pour un gel de prix.');
            return $retour;
        }

        /** @var Client $client */
        $client = $this->getUser();
        try {
            $gel = $gelPrixManager->geler($client, $voyage, $methode);
        } catch (GelPrixException $e) {
            $this->addFlash('warning', $e->getMessage());
            return $retour;
        }

        $this->addFlash('success', sprintf(
            "Prix gelé à %s € par personne jusqu'au %s. Réservez quand vous voulez d'ici là : vous paierez le prix le plus bas.",
            number_format((float) $gel->getPrixGele(), 0, ',', ' '),
            $gel->getExpireLe()->format('d/m/Y')
        ));
        return $retour;
    }
}
