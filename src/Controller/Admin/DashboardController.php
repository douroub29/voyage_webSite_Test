<?php

namespace App\Controller\Admin;

use App\Enum\StatutReservation;
use App\Repository\ClientRepository;
use App\Repository\ReservationRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function index(
        ReservationRepository $reservationRepo,
        ClientRepository      $clientRepo,
        VoyageRepository      $voyageRepo
    ): Response {
        return $this->render('admin/dashboard/index.html.twig', [
            'nbReservationsTotal'    => $reservationRepo->count([]),
            'nbReservationsAttente'  => $reservationRepo->countByStatut(StatutReservation::EnAttente),
            'nbReservationsConfirmees' => $reservationRepo->countByStatut(StatutReservation::Confirmee),
            'nbClients'              => $clientRepo->countTotal(),
            'nbVoyages'              => $voyageRepo->countActifs(),
            'revenusMois'            => $reservationRepo->sumRevenusMois(),
            'revenusTotal'           => $reservationRepo->sumRevenusTotal(),
            'reservationsRecentes'   => $reservationRepo->findRecentes(8),
            'voyagesAVenir'          => $reservationRepo->findVoyagesAVenir(5),
        ]);
    }
}
