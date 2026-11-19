<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Reservation;
use App\Entity\Voyage;
use App\Enum\MethodePaiement;
use App\Exception\ReservationException;
use App\Form\ReservationFormType;
use App\Service\ReservationManager;
use App\Service\VoucherManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservation')]
#[IsGranted('ROLE_CLIENT')]
class ReservationController extends AbstractController
{
    #[Route('/nouvelle/{id}', name: 'reservation_nouvelle', requirements: ['id' => '\d+'])]
    public function nouvelle(Voyage $voyage, Request $request, ReservationManager $reservationManager, VoucherManager $voucherManager): Response
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$voyage->isReservable()) {
            $this->addFlash('warning', $voyage->getMotifNonReservable() ?? 'Ce voyage n\'est plus disponible à la réservation.');

            return $this->redirectToRoute('app_voyage_show', ['id' => $voyage->getId()]);
        }

        $reservation = new Reservation();
        $reservation->setClient($client);
        $reservation->setVoyage($voyage);

        $form = $this->createForm(ReservationFormType::class, $reservation, ['voyage' => $voyage]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Décrémente les places de l'avion (avec verrou anti-survente)
                $reservationManager->creer($reservation);

                return $this->redirectToRoute('reservation_recap', ['id' => $reservation->getId()]);
            } catch (ReservationException $e) {
                $form->get('nombrePersonnes')->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('reservation/nouvelle.html.twig', [
            'form'              => $form, // objet Form : Symfony renvoie 422 si le formulaire est invalide
            'voyage'            => $voyage,
            'voucherDisponible' => $voucherManager->getVoucherDisponible($client),
        ]);
    }

    #[Route('/recap/{id}', name: 'reservation_recap', requirements: ['id' => '\d+'])]
    public function recap(Reservation $reservation): Response
    {
        $this->denyAccessIfNotOwner($reservation);

        return $this->render('reservation/recap.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/paiement/{id}', name: 'reservation_paiement', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function paiement(Reservation $reservation, Request $request, ReservationManager $reservationManager): Response
    {
        $this->denyAccessIfNotOwner($reservation);

        $methode = MethodePaiement::tryFrom((string) $request->request->get('methode', 'carte'));
        if ($methode === null) {
            $this->addFlash('danger', 'Méthode de paiement invalide.');
            return $this->redirectToRoute('reservation_recap', ['id' => $reservation->getId()]);
        }

        try {
            $reservationManager->payer($reservation, $methode);
        } catch (ReservationException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('reservation_recap', ['id' => $reservation->getId()]);
        }

        $this->addFlash('success', 'Paiement effectué avec succès ! Votre réservation est confirmée.');
        return $this->redirectToRoute('reservation_confirmation', ['id' => $reservation->getId()]);
    }

    #[Route('/confirmation/{id}', name: 'reservation_confirmation', requirements: ['id' => '\d+'])]
    public function confirmation(Reservation $reservation, VoucherManager $voucherManager): Response
    {
        $this->denyAccessIfNotOwner($reservation);

        return $this->render('reservation/confirmation.html.twig', [
            'reservation'     => $reservation,
            'voucherEligible' => $voucherManager->isEligible($reservation->getClient()),
        ]);
    }

    private function denyAccessIfNotOwner(Reservation $reservation): void
    {
        if ($reservation->getClient() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
    }
}
