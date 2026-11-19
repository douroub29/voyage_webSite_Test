<?php

namespace App\Controller\Admin;

use App\Entity\Voyage;
use App\Form\Admin\VoyageFormType;
use App\Repository\VoyageRepository;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/voyages')]
#[IsGranted('ROLE_ADMIN')]
class VoyageAdminController extends AbstractController
{
    #[Route('', name: 'admin_voyage_index')]
    public function index(VoyageRepository $repo): Response
    {
        return $this->render('admin/voyage/index.html.twig', [
            'voyages' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/nouveau', name: 'admin_voyage_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, FileUploadService $uploader): Response
    {
        $voyage = new Voyage();
        $form   = $this->createForm(VoyageFormType::class, $voyage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('photoFile')->getData();
            if ($file) {
                $voyage->setPhoto($uploader->upload($file, 'voyages'));
            }
            $em->persist($voyage);
            $em->flush();
            $this->addFlash('success', 'Voyage créé avec succès.');
            return $this->redirectToRoute('admin_voyage_index');
        }

        return $this->render('admin/voyage/form.html.twig', [
            'form'  => $form->createView(),
            'titre' => 'Nouveau voyage',
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_voyage_edit', methods: ['GET', 'POST'])]
    public function edit(Voyage $voyage, Request $request, EntityManagerInterface $em, FileUploadService $uploader): Response
    {
        $form = $this->createForm(VoyageFormType::class, $voyage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('photoFile')->getData();
            if ($file) {
                if ($voyage->getPhoto()) $uploader->delete($voyage->getPhoto());
                $voyage->setPhoto($uploader->upload($file, 'voyages'));
            }
            $em->flush();
            $this->addFlash('success', 'Voyage modifié.');
            return $this->redirectToRoute('admin_voyage_index');
        }

        return $this->render('admin/voyage/form.html.twig', [
            'form'  => $form->createView(),
            'titre' => 'Modifier le voyage',
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_voyage_delete', methods: ['POST'])]
    public function delete(Voyage $voyage, Request $request, EntityManagerInterface $em, FileUploadService $uploader): Response
    {
        if ($this->isCsrfTokenValid('delete' . $voyage->getId(), $request->request->get('_token'))) {
            if (!$voyage->getReservations()->isEmpty()) {
                $this->addFlash('danger', 'Ce voyage a des réservations : désactivez-le au lieu de le supprimer.');
                return $this->redirectToRoute('admin_voyage_index');
            }
            if ($voyage->getPhoto()) $uploader->delete($voyage->getPhoto());
            $em->remove($voyage);
            $em->flush();
            $this->addFlash('success', 'Voyage supprimé.');
        }
        return $this->redirectToRoute('admin_voyage_index');
    }

    #[Route('/{id}/toggle', name: 'admin_voyage_toggle', methods: ['POST'])]
    public function toggle(Voyage $voyage, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('toggle' . $voyage->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_voyage_index');
        }
        if (!$voyage->isActif() && $voyage->isDepartPasse()) {
            $this->addFlash('danger', 'Impossible de réactiver un voyage dont la date de départ est atteinte.');
            return $this->redirectToRoute('admin_voyage_index');
        }
        $voyage->setActif(!$voyage->isActif());
        $em->flush();
        $this->addFlash('success', 'Statut du voyage mis à jour.');
        return $this->redirectToRoute('admin_voyage_index');
    }
}
