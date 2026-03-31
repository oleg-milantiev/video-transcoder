<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Google\GoogleAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
        EventDispatcherInterface $eventDispatcher
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
                // Генерируем случайный пароль для Google пользователей
                $user->setPassword($passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
                // Устанавливаем базовую роль
                $user->setRoles(['ROLE_USER']);

                $entityManager->persist($user);
                $entityManager->flush();
            }

            // Создаём и устанавливаем аутентификационный токен
            $token = new UsernamePasswordToken($user, 'google_provider', $user->getRoles());
            $this->container->get('security.token_storage')->setToken($token);

            // Отправляем событие интерактивного входа
            $event = new InteractiveLoginEvent($request, $token);
            $eventDispatcher->dispatch($event, InteractiveLoginEvent::class);

            // Перенаправляем на главную страницу
            return $this->redirectToRoute('app_home');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Ошибка при входе через Google: ' . $e->getMessage());
            return $this->redirectToRoute('app_login');
        }
    }
}
