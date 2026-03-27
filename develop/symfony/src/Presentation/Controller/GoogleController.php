<?php

namespace App\Presentation\Controller;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Google\GoogleAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use App\Security\LoginFormAuthenticator;

class GoogleController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google_start')]
    public function connect(GoogleAuthenticator $googleAuth): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $authUrl = $googleAuth->getAuthorizationUrl();
        return $this->redirect($authUrl);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function check(
        Request $request,
        GoogleAuthenticator $googleAuth,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $authenticator
    ): Response {
        try {
            $googleUser = $googleAuth->getUserFromCode($request);
            $email = $googleUser->getEmail();

            if (!$email) {
                throw new \Exception('Email not provided');
            }

            // Поиск или создание пользователя
            $user = $entityManager->getRepository(UserEntity::class)->findOneBy(['email' => $email]);

            if (!$user) {
                $user = new UserEntity();
                $user->email = $email;
//                $user->setUsername($googleUser->getName() ?? explode('@', $email)[0]);
                $user->setPassword($passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));

                $entityManager->persist($user);
                $entityManager->flush();
            }

            // Автоматический вход
            return $userAuthenticator->authenticateUser(
                $user,
                $authenticator,
                $request
            );

        } catch (\Exception $e) {
            $this->addFlash('error', 'Ошибка при входе через Google: ' . $e->getMessage());
            return $this->redirectToRoute('app_login');
        }
    }
}
