<?php

namespace App\Controller\Admin;

use App\Entity\Hotel;
use App\Form\Admin\HotelFormType;
use App\Repository\HotelRepository;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/hotels')]
#[IsGranted('ROLE_ADMIN')]
class HotelAdminController extends AbstractController
{
    #[Route('', name: 'admin_hotel_index')]
    public function index(HotelRepository $repo): Response
    {
        return $this->render('admin/hotel/index.html.twig', [
            'hotels' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/nouveau', name: 'admin_hotel_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, FileUploadService $uploader): Response
    {
        $hotel = new Hotel();
        $form  = $this->createForm(HotelFormType::class, $hotel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('photoPrincipaleFile')->getData();
            if ($file) {
                $hotel->setPhotoPrincipale($uploader->upload($file, 'hotels'));
            }
            $em->persist($hotel);
            $em->flush();
            $this->addFlash('success', 'Hôtel créé avec succès.');
            return $this->redirectToRoute('admin_hotel_index');
        }

        return $this->render('admin/hotel/form.html.twig', [
            'form'  => $form->createView(),
            'titre' => 'Nouvel hôtel',
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_hotel_edit', methods: ['GET', 'POST'])]
    public function edit(Hotel $hotel, Request $request, EntityManagerInterface $em, FileUploadService $uploader): Response
    {
        $form = $this->createForm(HotelFormType::class, $hotel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('photoPrincipaleFile')->getData();
            if ($file) {
                if ($hotel->getPhotoPrincipale()) $uploader->delete($hotel->getPhotoPrincipale());
                $hotel->setPhotoPrincipale($uploader->upload($file, 'hotels'));
            }
            $em->flush();
            $this->addFlash('success', 'Hôtel modifié.');
            return $this->redirectToRoute('admin_hotel_index');
        }

        return $this->render('admin/hotel/form.html.twig', [
            'form'  => $form->createView(),
            'titre' => "Modifier l'hôtel",
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_hotel_delete', methods: ['POST'])]
    public function delete(Hotel $hotel, Request $request, EntityManagerInterface $em, FileUploadService $uploader): Response
    {
        if ($this->isCsrfTokenValid('delete' . $hotel->getId(), $request->request->get('_token'))) {
            if ($hotel->getPhotoPrincipale()) $uploader->delete($hotel->getPhotoPrincipale());
            $em->remove($hotel);
            $em->flush();
            $this->addFlash('success', 'Hôtel supprimé.');
        }
        return $this->redirectToRoute('admin_hotel_index');
    }
}
