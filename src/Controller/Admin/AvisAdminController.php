<?php

namespace App\Controller\Admin;

use App\Entity\Avis;
use App\Enum\StatutAvis;
use App\Repository\AvisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/avis')]
#[IsGranted('ROLE_ADMIN')]
class AvisAdminController extends AbstractController
{
    #[Route('', name: 'admin_avis_index')]
    public function index(AvisRepository $repo): Response
    {
        return $this->render('admin/avis/index.html.twig', [
            'avis' => $repo->findBy([], ['dateAvis' => 'DESC']),
        ]);
    }

    #[Route('/{id}/publier', name: 'admin_avis_publier', methods: ['POST'])]
    public function publier(Avis $avis, EntityManagerInterface $em): Response
    {
        $avis->setStatut(StatutAvis::Publie);
        $em->flush();
        $this->addFlash('success', 'Avis publié.');
        return $this->redirectToRoute('admin_avis_index');
    }

    #[Route('/{id}/rejeter', name: 'admin_avis_rejeter', methods: ['POST'])]
    public function rejeter(Avis $avis, EntityManagerInterface $em): Response
    {
        $avis->setStatut(StatutAvis::Rejete);
        $em->flush();
        $this->addFlash('warning', 'Avis rejeté.');
        return $this->redirectToRoute('admin_avis_index');
    }

    #[Route('/{id}/supprimer', name: 'admin_avis_delete', methods: ['POST'])]
    public function delete(Avis $avis, EntityManagerInterface $em): Response
    {
        $em->remove($avis);
        $em->flush();
        $this->addFlash('success', 'Avis supprimé.');
        return $this->redirectToRoute('admin_avis_index');
    }
}
