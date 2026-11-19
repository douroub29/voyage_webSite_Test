<?php

namespace App\Controller;

use App\Repository\UtilisateurRepository;
use App\Service\ActivationMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VerificationController extends AbstractController
{
    // ── Page d'attente ──────────────────────────────────────────
    #[Route('/verifier-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        return $this->render('security/check_email.html.twig');
    }

    // ── Lien cliqué depuis l'email ───────────────────────────────
    #[Route('/activation/{token}', name: 'app_verify_email')]
    public function verify(string $token, UtilisateurRepository $repo, ActivationMailer $activation): Response
    {
        if (!$activation->activer($repo->findOneBy(['verificationToken' => $token]))) {
            $this->addFlash('danger', 'Lien d\'activation invalide ou déjà utilisé.');
            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('success', 'Compte activé avec succès ! Vous pouvez maintenant vous connecter.');
        return $this->redirectToRoute('app_login');
    }

    // ── Renvoi de l'email d'activation ──────────────────────────
    #[Route('/renvoyer-activation', name: 'app_resend_verification', methods: ['POST'])]
    public function resend(Request $request, UtilisateurRepository $repo, ActivationMailer $activation): Response
    {
        $user = $repo->findOneBy(['email' => trim((string) $request->request->get('email', ''))]);

        if (!$user || $user->isVerified()) {
            $this->addFlash('warning', 'Aucun compte en attente d\'activation pour cet email.');
            return $this->redirectToRoute('app_check_email');
        }

        $activation->envoyer($user);

        $this->addFlash('success', 'Email d\'activation renvoyé à ' . $user->getEmail() . '.');
        return $this->redirectToRoute('app_check_email');
    }
}
