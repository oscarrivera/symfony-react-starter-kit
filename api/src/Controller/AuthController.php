<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\AuthenticatedUser;
use App\Domain\User;
use App\Http\HttpException;
use App\Http\Json;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use App\Security\JwtService;
use App\Security\PasswordHasher;
use App\Security\RateLimiter;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AuthController
{
    private readonly ValidatorInterface $validator;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly JwtService $jwt,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly RateLimiter $rateLimiter,
        private readonly bool $cookieSecure,
        private readonly int $refreshTtl,
    ) {
        $this->validator = Validation::createValidator();
    }

    public function login(Request $request): Response
    {
        $ip = $request->getClientIp() ?? '0.0.0.0';
        $this->rateLimiter->assertAllowed($ip);
        $this->rateLimiter->record($ip);

        $data = Json::decode($request);
        $violations = $this->validator->validate($data, new Assert\Collection([
            'fields' => [
                'email' => [
                    new Assert\NotBlank(),
                    new Assert\Type('string'),
                    new Assert\Email(),
                ],
                'password' => [
                    new Assert\NotBlank(),
                    new Assert\Type('string'),
                    new Assert\Length(min: 1, max: 1024),
                ],
            ],
            'allowExtraFields' => false,
        ]));
        if (count($violations) > 0) {
            throw new HttpException(400, 'validation_failed');
        }

        $email = strtolower(trim((string) $data['email']));
        $password = (string) $data['password'];
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            $this->hasher->verifyUnknown($password);
            throw new HttpException(401, 'invalid_credentials');
        }
        if (!$this->hasher->verify($password, $user->passwordHash)) {
            throw new HttpException(401, 'invalid_credentials');
        }

        return $this->tokenResponse($user, 200);
    }

    public function refresh(Request $request): Response
    {
        $raw = $request->cookies->get('refresh_token');
        if (!is_string($raw) || $raw === '') {
            throw new HttpException(401, 'unauthorized');
        }

        $user = $this->refreshTokens->consume($raw);
        if ($user === null) {
            $response = Json::error('unauthorized', 401);
            $response->headers->clearCookie('refresh_token', '/api/token', null, $this->cookieSecure, true, Cookie::SAMESITE_STRICT);

            return $response;
        }

        return $this->tokenResponse($user, 200);
    }

    public function logout(Request $request): Response
    {
        $raw = $request->cookies->get('refresh_token');
        if (is_string($raw) && $raw !== '') {
            $this->refreshTokens->deleteByRaw($raw);
        }
        $response = Json::ok(['ok' => true]);
        $response->headers->clearCookie('refresh_token', '/api/token', null, $this->cookieSecure, true, Cookie::SAMESITE_STRICT);

        return $response;
    }

    private function tokenResponse(User $user, int $status): Response
    {
        $auth = AuthenticatedUser::fromUser($user);
        [$token, $expiresAt] = $this->jwt->issue($auth);
        $rawRefresh = $this->refreshTokens->issue($user->id, $this->refreshTtl);

        $response = Json::ok([
            'token' => $token,
            'expiresAt' => $expiresAt,
        ], $status);
        $response->headers->setCookie($this->refreshCookie($rawRefresh));

        return $response;
    }

    private function refreshCookie(string $raw): Cookie
    {
        // Path limited to /api/token so the browser only attaches the cookie to refresh/logout.
        // SameSite=Strict is viable because the SPA talks to the API through a same-origin Vite proxy.
        return Cookie::create('refresh_token')
            ->withValue($raw)
            ->withExpires(time() + $this->refreshTtl)
            ->withPath('/api/token')
            ->withSecure($this->cookieSecure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT);
    }
}
