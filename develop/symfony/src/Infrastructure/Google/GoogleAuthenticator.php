<?php

namespace App\Infrastructure\Google;

use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class GoogleAuthenticator
{
    private Google $provider;
    private SessionInterface $session;

    public function __construct(
        SessionInterface $session,
        #[Autowire('%env(OAUTH_GOOGLE_CLIENT_ID)%')]
        string $clientId,
        #[Autowire('%env(OAUTH_GOOGLE_CLIENT_SECRET)%')]
        string $clientSecret,
        #[Autowire('%env(OAUTH_GOOGLE_REDIRECT_URI)%')]
        string $redirectUri
    ) {
        $this->session = $session;

        $this->provider = new Google([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirectUri,
        ]);
    }

    /**
     * Получить URL для редиректа на Google
     */
    public function getAuthorizationUrl(): string
    {
        $authUrl = $this->provider->getAuthorizationUrl([
            'scope' => ['email', 'profile']
        ]);

        // Сохраняем state в сессии для защиты от CSRF
        $this->session->set('oauth2state', $this->provider->getState());

        return $authUrl;
    }

    /**
     * Получить пользователя от Google по коду авторизации
     *
     * @throws \RuntimeException
     */
    public function getUserFromCode(Request $request): GoogleUser
    {
        // Проверяем наличие ошибки от Google
        $error = $request->query->get('error');
        if ($error) {
            $errorDescription = $request->query->get('error_description', 'Unknown error');
            throw new \RuntimeException("Google OAuth error: $error - $errorDescription");
        }

        // Проверяем state (защита от CSRF)
        $state = $request->query->get('state');
        $savedState = $this->session->get('oauth2state');

        if (!$state || $state !== $savedState) {
            throw new \RuntimeException('Invalid OAuth state - possible CSRF attack');
        }

        // Очищаем state из сессии после проверки
        $this->session->remove('oauth2state');

        // Получаем код авторизации
        $code = $request->query->get('code');
        if (!$code) {
            throw new \RuntimeException('No authorization code provided');
        }

        try {
            // Получаем токен доступа
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $code
            ]);

            // Получаем данные пользователя
            /** @var GoogleUser $resourceOwner */
            $resourceOwner = $this->provider->getResourceOwner($token);

            return $resourceOwner;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to get access token: ' . $e->getMessage(), 0, $e);
        }
    }
}
