<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Google\GoogleAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class GoogleController extends AbstractController
{
    use TargetPathTrait;

    private const string FIREWALL_NAME = 'main';

    #[Route('/connect/google', name: 'connect_google_start')]
    public function connect(Request $request, GoogleAuthenticator $googleAuth): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $targetPath = $request->query->getString('_target_path');
        if ('' !== $targetPath) {
            $this->saveTargetPath($request->getSession(), self::FIREWALL_NAME, $targetPath);
        }

        try {
            return $this->redirect($googleAuth->getAuthorizationUrl());
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Ошибка при входе через Google: ' . $e->getMessage());

            $routeParameters = [];
            if ('' !== $targetPath) {
                $routeParameters['_target_path'] = $targetPath;
            }

            return $this->redirectToRoute('app_login', $routeParameters);
        }
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function check(
        Request $request,
        GoogleAuthenticator $googleAuth,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        Security $security
    ): Response {
        try {
            $googleUser = $googleAuth->getUserFromCode($request);
            $email = mb_strtolower(trim((string) $googleUser->getEmail()));

            if (!$email) {
                throw new \RuntimeException('Email not provided');
            }

            if ($googleUser->getEmailVerified() !== true) {
                throw new \RuntimeException('Google account email is not verified');
            }

            // Поиск или создание пользователя
            /** @var UserEntity|null $user */
            $user = $entityManager->getRepository(UserEntity::class)->findOneBy(['email' => $email]);

            if (!$user) {
                $user = new UserEntity();
                $user->email = $email;
                // Генерируем случайный пароль для Google пользователей
                $user->setPassword($passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
                // Устанавливаем базовую роль
                $user->setRoles(['ROLE_USER']);

                $entityManager->persist($user);
                $entityManager->flush();
            }

            $response = $security->login($user, 'form_login', self::FIREWALL_NAME);
            if ($response instanceof Response) {
                return $response;
            }

            $targetPath = $this->getTargetPath($request->getSession(), self::FIREWALL_NAME);
            if ($targetPath !== null) {
                $this->removeTargetPath($request->getSession(), self::FIREWALL_NAME);

                return new RedirectResponse($targetPath);
            }

            return $this->redirectToRoute('app_home');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Ошибка при входе через Google: ' . $e->getMessage());

            $routeParameters = [];
            $targetPath = $this->getTargetPath($request->getSession(), self::FIREWALL_NAME);
            if ($targetPath !== null) {
                $routeParameters['_target_path'] = $targetPath;
            }

            return $this->redirectToRoute('app_login', $routeParameters);
        }
    }
}
