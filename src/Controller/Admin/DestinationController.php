<?php

namespace App\Controller\Admin;

use App\Entity\Destination;
use App\Form\Admin\DestinationFormType;
use App\Repository\DestinationRepository;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/destinations')]
#[IsGranted('ROLE_ADMIN')]
class DestinationController extends AbstractController
{
    #[Route('', name: 'admin_destination_index')]
    public function index(DestinationRepository $repo): Response
    {
        return $this->render('admin/destination/index.html.twig', [
            'destinations' => $repo->findAll(),
        ]);
    }

    #[Route('/nouveau', name: 'admin_destination_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, FileUploadService $uploader): Response
    {
        $destination = new Destination();
        $form        = $this->createForm(DestinationFormType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('photoFile')->getData();
            if ($file) {
                $destination->setPhoto($uploader->upload($file, 'destinations'));
            }
            $em->persist($destination);
            $em->flush();
            $this->addFlash('success', 'Destination créée avec succès.');
            return $this->redirectToRoute('admin_destination_index');
        }

        return $this->render('admin/destination/form.html.twig', [
            'form'    => $form->createView(),
            'titre'   => 'Nouvelle destination',
            'entity'  => $destination,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_destination_edit', methods: ['GET', 'POST'])]
    public function edit(Destination $destination, Request $request, EntityManagerInterface $em, FileUploadService $uploader): Response
    {
        $form = $this->createForm(DestinationFormType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('photoFile')->getData();
            if ($file) {
                if ($destination->getPhoto()) {
                    $uploader->delete($destination->getPhoto());
                }
                $destination->setPhoto($uploader->upload($file, 'destinations'));
            }
            $em->flush();
            $this->addFlash('success', 'Destination modifiée.');
            return $this->redirectToRoute('admin_destination_index');
        }

        return $this->render('admin/destination/form.html.twig', [
            'form'   => $form->createView(),
            'titre'  => 'Modifier la destination',
            'entity' => $destination,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_destination_delete', methods: ['POST'])]
    public function delete(Destination $destination, Request $request, EntityManagerInterface $em, FileUploadService $uploader): Response
    {
        if ($this->isCsrfTokenValid('delete' . $destination->getId(), $request->request->get('_token'))) {
            if ($destination->getPhoto()) {
                $uploader->delete($destination->getPhoto());
            }
            $em->remove($destination);
            $em->flush();
            $this->addFlash('success', 'Destination supprimée.');
        }
        return $this->redirectToRoute('admin_destination_index');
    }
}
