<?php

namespace App\Controller;

use App\Entity\Hotel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/hotels')]
class HotelController extends AbstractController
{
    #[Route('/{id}', name: 'app_hotel_show', requirements: ['id' => '\d+'])]
    public function show(Hotel $hotel): Response
    {
        return $this->render('hotel/show.html.twig', [
            'hotel' => $hotel,
        ]);
    }
}
