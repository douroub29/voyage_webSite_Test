<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Entity\Client;
use App\Form\AvisFormType;
use App\Repository\AvisRepository;
use App\Repository\VoyageRepository;
use App\Service\AvisService;
use Symfony\Component\Form\FormInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(VoyageRepository $voyageRepo, AvisRepository $avisRepo): Response
    {
        $avisForm = $this->isGranted('ROLE_CLIENT')
            ? $this->createAvisForm(new Avis())->createView()
            : null;

        return $this->render('home/index.html.twig', [
            'voyagesVedettes' => $voyageRepo->findVedettes(6),
            'avis'            => $avisRepo->findPublies(12),
            'avisForm'        => $avisForm,
        ]);
    }

    /** Un client connecté publie un avis : il apparaît immédiatement sur l'accueil. */
    #[Route('/avis/nouveau', name: 'app_avis_new', methods: ['POST'])]
    #[IsGranted('ROLE_CLIENT')]
    public function nouvelAvis(Request $request, AvisService $avisService): Response
    {
        /** @var Client $client */
        $client = $this->getUser();

        $avis = new Avis();
        $form = $this->createAvisForm($avis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avisService->publier($avis, $client);

            $this->addFlash('success', 'Merci ! Votre avis a été publié.');
        } else {
            $erreurs = [];
            foreach ($form->getErrors(true) as $erreur) {
                $erreurs[] = $erreur->getMessage();
            }
            $this->addFlash('danger', 'Avis non publié : ' . ($erreurs ? implode(' ', $erreurs) : 'formulaire invalide.'));
        }

        return $this->redirect($this->generateUrl('app_home') . '#avis');
    }

    private function createAvisForm(Avis $avis): FormInterface
    {
        return $this->createForm(AvisFormType::class, $avis, [
            'action' => $this->generateUrl('app_avis_new'),
        ]);
    }
}
