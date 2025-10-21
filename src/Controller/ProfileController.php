<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\InvalidPasswordException;
use App\Model\User;
use App\Service\PasswordChangeServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * User Profile Controller
 * Handles user profile operations (password change, etc.)
 */
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private readonly PasswordChangeServiceInterface $passwordChangeService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    /**
     * Change password for logged-in user
     */
    #[Route('/profile/change-password', name: 'profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $currentPassword = $request->request->get('current_password', '');
            $newPassword = $request->request->get('new_password', '');
            $newPasswordConfirm = $request->request->get('new_password_confirm', '');
            $csrfToken = $request->request->get('_csrf_token', '');

            // Validate CSRF token
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('change_password', $csrfToken))) {
                return $this->render('auth/change_password.html.twig', [
                    'error' => 'Sesja wygasła. Spróbuj ponownie.',
                ]);
            }

            // Validate input
            if (empty($currentPassword) || empty($newPassword) || empty($newPasswordConfirm)) {
                return $this->render('auth/change_password.html.twig', [
                    'error' => 'Wszystkie pola są wymagane',
                ]);
            }

            if ($newPassword !== $newPasswordConfirm) {
                return $this->render('auth/change_password.html.twig', [
                    'error' => 'Nowe hasła nie są identyczne',
                ]);
            }

            if (strlen($newPassword) < 8) {
                return $this->render('auth/change_password.html.twig', [
                    'error' => 'Hasło musi mieć minimum 8 znaków',
                ]);
            }

            try {
                $this->passwordChangeService->changePassword(
                    user: $user,
                    currentPassword: $currentPassword,
                    newPassword: $newPassword,
                    ipAddress: $request->getClientIp()
                );

                $this->addFlash('success', 'Hasło zostało pomyślnie zmienione');
                return $this->redirectToRoute('leads_index');
            } catch (InvalidPasswordException $e) {
                return $this->render('auth/change_password.html.twig', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->render('auth/change_password.html.twig', [
            'error' => null,
        ]);
    }
}
