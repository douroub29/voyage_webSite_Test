<?php

namespace App\Controller\Admin;

use App\Entity\Societe;
use App\Form\Admin\SocieteFormType;
use App\Repository\SocieteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/societes')]
#[IsGranted('ROLE_ADMIN')]
class SocieteController extends AbstractController
{
    #[Route('', name: 'admin_societe_index')]
    public function index(SocieteRepository $repo): Response
    {
        return $this->render('admin/societe/index.html.twig', [
            'societes' => $repo->findAllOrdered(),
        ]);
    }

    #[Route('/nouveau', name: 'admin_societe_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        return $this->handleForm(new Societe(), $request, $em, 'Nouvelle société', 'Société créée avec succès.');
    }

    #[Route('/{id}/modifier', name: 'admin_societe_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Societe $societe, Request $request, EntityManagerInterface $em): Response
    {
        return $this->handleForm($societe, $request, $em, 'Modifier la société', 'Société modifiée.');
    }

    #[Route('/{id}/supprimer', name: 'admin_societe_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Societe $societe, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $societe->getId(), $request->request->get('_token'))) {
            // Les voyages de la société sont conservés (societe_id passe à NULL).
            foreach ($societe->getVoyages() as $voyage) {
                $voyage->setSociete(null);
            }
            $em->remove($societe);
            $em->flush();
            $this->addFlash('success', 'Société supprimée.');
        }
        return $this->redirectToRoute('admin_societe_index');
    }

    private function handleForm(Societe $societe, Request $request, EntityManagerInterface $em, string $titre, string $message): Response
    {
        $form = $this->createForm(SocieteFormType::class, $societe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($societe);
            $em->flush();
            $this->addFlash('success', $message);
            return $this->redirectToRoute('admin_societe_index');
        }

        return $this->render('admin/societe/form.html.twig', [
            'form'  => $form->createView(),
            'titre' => $titre,
        ]);
    }
}
