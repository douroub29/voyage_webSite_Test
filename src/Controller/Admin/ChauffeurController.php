<?php

namespace App\Controller\Admin;

use App\Entity\Chauffeur;
use App\Form\Admin\ChauffeurFormType;
use App\Repository\ChauffeurRepository;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/chauffeurs')]
#[IsGranted('ROLE_ADMIN')]
class ChauffeurController extends AbstractController
{
    #[Route('', name: 'admin_chauffeur_index')]
    public function index(ChauffeurRepository $repo): Response
    {
        return $this->render('admin/chauffeur/index.html.twig', [
            'chauffeurs' => $repo->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'admin_chauffeur_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, FileUploadService $uploader): Response
    {
        $chauffeur = new Chauffeur();
        $form      = $this->createForm(ChauffeurFormType::class, $chauffeur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('photoFile')->getData();
            if ($file) {
                $chauffeur->setPhoto($uploader->upload($file, 'chauffeurs'));
            }
            $em->persist($chauffeur);
            $em->flush();
            $this->addFlash('success', 'Chauffeur ajouté avec succès.');
            return $this->redirectToRoute('admin_chauffeur_index');
        }

        return $this->render('admin/chauffeur/form.html.twig', [
            'form'  => $form->createView(),
            'titre' => 'Nouveau chauffeur',
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_chauffeur_edit', methods: ['GET', 'POST'])]
    public function edit(Chauffeur $chauffeur, Request $request, EntityManagerInterface $em, FileUploadService $uploader): Response
    {
        $form = $this->createForm(ChauffeurFormType::class, $chauffeur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('photoFile')->getData();
            if ($file) {
                if ($chauffeur->getPhoto()) $uploader->delete($chauffeur->getPhoto());
                $chauffeur->setPhoto($uploader->upload($file, 'chauffeurs'));
            }
            $em->flush();
            $this->addFlash('success', 'Chauffeur modifié.');
            return $this->redirectToRoute('admin_chauffeur_index');
        }

        return $this->render('admin/chauffeur/form.html.twig', [
            'form'  => $form->createView(),
            'titre' => 'Modifier le chauffeur',
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_chauffeur_delete', methods: ['POST'])]
    public function delete(Chauffeur $chauffeur, Request $request, EntityManagerInterface $em, FileUploadService $uploader): Response
    {
        if ($this->isCsrfTokenValid('delete' . $chauffeur->getId(), $request->request->get('_token'))) {
            if ($chauffeur->getPhoto()) $uploader->delete($chauffeur->getPhoto());
            $em->remove($chauffeur);
            $em->flush();
            $this->addFlash('success', 'Chauffeur supprimé.');
        }
        return $this->redirectToRoute('admin_chauffeur_index');
    }
}
