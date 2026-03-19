<?php

namespace App\Presentation\Controller;

use App\Infrastructure\Security\ApiTokenService;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly ApiTokenService $tokenService,
    ) {
    }

    /**
     * @throws \JsonException
     */
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $user = $this->getUser();
        $apiAccessToken = null;

        if ($user instanceof UserEntity && $user->id !== null) {
            $apiAccessToken = $this->tokenService->createToken($user->id, $user->getUserIdentifier());
        }

        return $this->render('home/index.html.twig', [
            'apiAccessToken' => $apiAccessToken,
        ]);
    }
}
