<?php
declare(strict_types=1);

namespace App\Infrastructure\Google;

use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class GoogleAuthenticator
{
    private const SESSION_STATE_KEY = 'oauth2state';

    private Google $provider;
    private RequestStack $requestStack;
    private bool $isConfigured;

    public function __construct(
        RequestStack $requestStack,
        #[Autowire('%env(OAUTH_GOOGLE_CLIENT_ID)%')]
        string $clientId,
        #[Autowire('%env(OAUTH_GOOGLE_CLIENT_SECRET)%')]
        string $clientSecret,
        #[Autowire('%env(OAUTH_GOOGLE_REDIRECT_URI)%')]
        string $redirectUri
    ) {
        $this->requestStack = $requestStack;
        $this->isConfigured = '' !== trim($clientId)
            && '' !== trim($clientSecret)
            && '' !== trim($redirectUri);

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
        $this->assertConfigured();
        $session = $this->getSession();

        $authUrl = $this->provider->getAuthorizationUrl([
            'scope' => ['email', 'profile']
        ]);

        // Сохраняем state в сессии для защиты от CSRF
        $session->set(self::SESSION_STATE_KEY, $this->provider->getState());

        return $authUrl;
    }

    /**
     * Получить пользователя от Google по коду авторизации
     *
     * @throws \RuntimeException
     */
    public function getUserFromCode(Request $request): GoogleUser
    {
        $session = $this->getSession();

        // Проверяем наличие ошибки от Google
        $error = $request->query->getString('error');
        if ($error) {
            $errorDescription = $request->query->getString('error_description', 'Unknown error');
            throw new \RuntimeException("Google OAuth error: $error - $errorDescription");
        }

        // Проверяем state (защита от CSRF)
        $state = $request->query->getString('state');
        $savedState = $session->get(self::SESSION_STATE_KEY);
        $session->remove(self::SESSION_STATE_KEY);

        if (!$state || $state !== $savedState) {
            throw new \RuntimeException('Invalid OAuth state - possible CSRF attack');
        }

        // Получаем код авторизации
        $code = $request->query->getString('code');
        if (!$code) {
            throw new \RuntimeException('No authorization code provided');
        }

        try {
            $this->assertConfigured();

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

    /**
     * @throws \RuntimeException
     */
    private function assertConfigured(): void
    {
        if ($this->isConfigured) {
            return;
        }

        throw new \RuntimeException('Google OAuth is not configured. Please set OAUTH_GOOGLE_CLIENT_ID, OAUTH_GOOGLE_CLIENT_SECRET and OAUTH_GOOGLE_REDIRECT_URI.');
    }

    /**
     * @throws \RuntimeException
     */
    private function getSession(): SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasSession()) {
            throw new \RuntimeException('Google OAuth requires an active session.');
        }

        return $request->getSession();
    }
}
