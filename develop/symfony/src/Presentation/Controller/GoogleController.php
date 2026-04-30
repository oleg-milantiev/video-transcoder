<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Logging\LogServiceInterface;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Google\GoogleAuthenticator;
use App\Infrastructure\Persistence\Doctrine\User\TariffEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;
use Throwable;

class GoogleController extends AbstractController
{
    use TargetPathTrait;

    private const string FIREWALL_NAME = 'main';
    private const string TARIFF_FREE_ID = '905048e3-fd0f-408d-bffd-a596e896a92c';

    public function __construct(
        private readonly GoogleAuthenticator $googleAuth,
        private readonly LogServiceInterface $logService,
    ) {
    }

    /**
     * Initiates Google OAuth flow.
     * Redirects already-authenticated users to the home page.
     * Saves the originally requested path in the session so it can be
     * restored after a successful login.
     */
    #[Route('/connect/google', name: 'connect_google_start')]
    public function connect(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $targetPath = $request->query->getString('_target_path');
        if ('' !== $targetPath) {
            $this->saveTargetPath($request->getSession(), self::FIREWALL_NAME, $targetPath);
        }

        try {
            return $this->redirect($this->googleAuth->getAuthorizationUrl());
        } catch (Throwable $e) {
            $this->logService->log('user', 'google', null, LogLevel::ERROR, 'Google connect error', [
                'message' => $e->getMessage(),
            ]);
            $this->addFlash('error', 'Google connect error. Try again later');

            $routeParameters = [];
            if ('' !== $targetPath) {
                $routeParameters['_target_path'] = $targetPath;
            }

            return $this->redirectToRoute('app_login', $routeParameters);
        }
    }

    /**
     * Google OAuth callback.
     * Exchanges the authorization code for user data, auto-registers the user
     * if they do not exist yet (assigning the Free tariff), records the login,
     * and establishes a Symfony session.
     * Redirects to the saved target path or to the home page on success,
     * or back to the login page on failure.
     */
    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function check(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
    ): Response {
        try {
            $googleUser = $this->googleAuth->getUserFromCode($request);
            $email = mb_strtolower(trim((string)$googleUser->getEmail()));

            if (!$email) {
                throw new RuntimeException('Email not provided');
            }

            if ($googleUser->getEmailVerified() !== true) {
                throw new RuntimeException('Google account email is not verified');
            }

            /** @var UserEntity|null $user */
            $user = $em->getRepository(UserEntity::class)->findOneBy(['email' => $email]);

            if (!$user) {
                $user = new UserEntity();
                $user->email = $email;
                $user->setPassword($passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
                $user->setRoles(['ROLE_USER']);
                $user->tariff = $em->getReference(TariffEntity::class, SymfonyUuid::fromString(self::TARIFF_FREE_ID));

                $em->persist($user);
                $em->flush();

                $this->logService->log(
                    'user',
                    'create',
                    Uuid::fromString($user->id->toRfc4122()),
                    LogLevel::INFO,
                    'Created User via Google',
                    [
                        'email' => $email,
                        'tariffId' => self::TARIFF_FREE_ID,
                    ]
                );
            }

            $user->loginedAt = new DateTimeImmutable();
            $em->flush();

            $this->logService->log(
                'user',
                'login',
                Uuid::fromString($user->id->toRfc4122()),
                LogLevel::INFO,
                'Login via Google',
                [
                    'email' => $email,
                ]
            );

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
        } catch (Throwable $e) {
            $this->logService->log('user', 'google', null, LogLevel::ERROR, 'Google login error', [
                'message' => $e->getMessage(),
            ]);
            $this->addFlash('error', 'Google login error. Try again later');

            $routeParameters = [];
            $targetPath = $this->getTargetPath($request->getSession(), self::FIREWALL_NAME);
            if ($targetPath !== null) {
                $routeParameters['_target_path'] = $targetPath;
            }

            return $this->redirectToRoute('app_login', $routeParameters);
        }
    }
}
