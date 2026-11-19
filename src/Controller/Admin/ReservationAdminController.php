<?php

namespace App\Controller\Admin;

use App\Entity\Reservation;
use App\Enum\StatutReservation;
use App\Exception\ReservationException;
use App\Repository\ChauffeurRepository;
use App\Repository\ReservationRepository;
use App\Service\ReservationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reservations')]
#[IsGranted('ROLE_ADMIN')]
class ReservationAdminController extends AbstractController
{
    #[Route('', name: 'admin_reservation_index')]
    public function index(ReservationRepository $repo, Request $request): Response
    {
        $statut = $request->query->get('statut');
        $criteres = $statut ? ['statut' => StatutReservation::from($statut)] : [];

        return $this->render('admin/reservation/index.html.twig', [
            'reservations' => $repo->findBy($criteres, ['createdAt' => 'DESC']),
            'filtreStatut' => $statut,
        ]);
    }

    #[Route('/{id}', name: 'admin_reservation_show', requirements: ['id' => '\d+'])]
    public function show(Reservation $reservation, ChauffeurRepository $chauffeurRepo): Response
    {
        return $this->render('admin/reservation/show.html.twig', [
            'reservation' => $reservation,
            'chauffeurs'  => $chauffeurRepo->findBy(['actif' => true]),
        ]);
    }

    #[Route('/{id}/confirmer', name: 'admin_reservation_confirmer', methods: ['POST'])]
    public function confirmer(Reservation $reservation, Request $request, ReservationManager $manager): Response
    {
        if ($this->isCsrfTokenValid('confirmer' . $reservation->getId(), $request->request->get('_token'))) {
            try {
                $manager->confirmer($reservation);
                $this->addFlash('success', 'Réservation #' . $reservation->getReference() . ' confirmée.');
            } catch (ReservationException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }
        return $this->redirectToRoute('admin_reservation_show', ['id' => $reservation->getId()]);
    }

    #[Route('/{id}/annuler', name: 'admin_reservation_annuler', methods: ['POST'])]
    public function annuler(Reservation $reservation, Request $request, ReservationManager $manager): Response
    {
        if ($this->isCsrfTokenValid('annuler' . $reservation->getId(), $request->request->get('_token'))) {
            try {
                // Remet les places en vente sur le voyage
                $manager->annuler($reservation);
                $this->addFlash('warning', sprintf(
                    'Réservation #%s annulée : %d place(s) remise(s) en vente.',
                    $reservation->getReference(),
                    $reservation->getNombrePersonnes()
                ));
            } catch (ReservationException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }
        return $this->redirectToRoute('admin_reservation_show', ['id' => $reservation->getId()]);
    }

    #[Route('/{id}/affecter-chauffeur', name: 'admin_reservation_chauffeur', methods: ['POST'])]
    public function affecterChauffeur(Reservation $reservation, Request $request, ChauffeurRepository $chauffeurRepo, ReservationManager $manager): Response
    {
        $chauffeur = $chauffeurRepo->find($request->request->getInt('chauffeur_id'));

        if ($chauffeur) {
            $manager->affecterChauffeur($reservation, $chauffeur);
            $this->addFlash('success', 'Chauffeur affecté : ' . $chauffeur->getNomComplet());
        }

        return $this->redirectToRoute('admin_reservation_show', ['id' => $reservation->getId()]);
    }
}
