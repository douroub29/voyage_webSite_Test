<?php

namespace App\Controller;

use App\Entity\Voyage;
use App\Repository\HotelRepository;
use App\Repository\SocieteRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
    public function show(Voyage $voyage, HotelRepository $hotelRepo): Response
    {
        $hotels = $hotelRepo->findByDestination($voyage->getDestination()->getId());

        return $this->render('voyage/show.html.twig', [
            'voyage' => $voyage,
            'hotels' => $hotels,
        ]);
    }
}
