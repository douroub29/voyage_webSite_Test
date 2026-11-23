<?php

namespace App\Controller;

use App\Entity\Client;
use App\Exception\VoucherException;
use App\Service\AvisService;
use App\Service\IA\RecommandationService;
use App\Service\Prix\GelPrixManager;
use App\Service\VoucherManager;
use App\Service\VoucherService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mon-compte')]
#[IsGranted('ROLE_CLIENT')]
class ProfilController extends AbstractController
{
    #[Route('', name: 'profil_index')]
    public function index(VoucherManager $voucherManager): Response
    {
        $client = $this->getClient();

        return $this->render('profil/index.html.twig', [
            'client'   => $client,
            'fidelite' => $this->fidelite($client, $voucherManager),
        ]);
    }

    #[Route('/reservations', name: 'profil_reservations')]
    public function reservations(VoucherManager $voucherManager, GelPrixManager $gelPrixManager): Response
    {
        $client = $this->getClient();

        return $this->render('profil/reservations.html.twig', [
            'reservations' => $client->getReservations(),
            'fidelite'     => $this->fidelite($client, $voucherManager),
            'gels'         => $gelPrixManager->getGelsClient($client),
        ]);
    }

    /**
     * "Télécharger voucher" : réservé aux clients ayant 5 réservations confirmées.
     * Crée (ou réaffiche) le ticket de remise stocké sur le compte du client.
     */
    #[Route('/voucher', name: 'profil_voucher')]
    public function voucher(Request $request, VoucherManager $voucherManager, VoucherService $voucherService): Response
    {
        try {
            $voucher = $voucherManager->obtenirVoucher($this->getClient());
        } catch (VoucherException $e) {
            $this->addFlash('warning', $e->getMessage());
            return $this->redirectToRoute('profil_reservations');
        }

        if ($request->query->getBoolean('pdf') && $voucherService->isPdfDisponible()) {
            return new Response($voucherService->generatePdf($voucher), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="voucher-' . $voucher->getCode() . '.pdf"',
            ]);
        }

        return new Response($voucherService->renderHtml($voucher));
    }

    /** Fragment HTML chargé en différé (fetch) sur l'accueil et "Mes réservations". */
    #[Route('/recommandations', name: 'profil_recommandations', methods: ['GET'])]
    public function recommandations(RecommandationService $recommandations, VoucherManager $voucherManager): Response
    {
        $client = $this->getClient();

        return $this->render('profil/_recommandations.html.twig', [
            'recommandations'   => $recommandations->pourClient($client),
            'voucherDisponible' => $voucherManager->getVoucherDisponible($client),
        ]);
    }

    #[Route('/avis/nouveau/{reservationId}', name: 'profil_avis_nouveau', requirements: ['reservationId' => '\d+'])]
    public function nouvelAvis(int $reservationId, Request $request, AvisService $avisService): Response
    {
        if ($request->isMethod('POST')) {
            $avisService->soumettre(
                $this->getClient(),
                $request->request->getInt('note', 5),
                (string) $request->request->get('commentaire', ''),
                $request->request->get('cible'),
                $request->request->getInt('cible_id'),
            );
            $this->addFlash('success', 'Votre avis a été soumis. Il sera publié après validation.');
        }

        return $this->redirectToRoute('profil_reservations');
    }

    /** Données de fidélité affichées dans la vue. */
    private function fidelite(Client $client, VoucherManager $voucherManager): array
    {
        return [
            'eligible'    => $voucherManager->isEligible($client),
            'confirmees'  => $client->getNombreReservationsConfirmees(),
            'seuil'       => VoucherManager::SEUIL_RESERVATIONS,
            'manquantes'  => $voucherManager->getReservationsManquantes($client),
            'pourcentage' => $voucherManager->getProchainPourcentage($client),
            'paliers'     => VoucherManager::PALIERS_REMISE,
            'disponible'  => $voucherManager->getVoucherDisponible($client),
            'aTelecharger'=> $voucherManager->getNombreVouchersGagnes($client) > $client->getVouchers()->count(),
            'vouchers'    => $client->getVouchers(),
        ];
    }

    private function getClient(): Client
    {
        /** @var Client $client */
        $client = $this->getUser();
        return $client;
    }
}
