<?php

declare(strict_types=1);

namespace App;

use App\Controller\AuthController;
use App\Controller\WorkOrderController;
use App\Database\Schema;
use App\Database\Sqlite;
use App\Domain\AuthenticatedUser;
use App\Domain\WorkOrderPolicy;
use App\Http\HttpException;
use App\Http\Json;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use App\Repository\WorkOrderRepository;
use App\Security\JwtService;
use App\Security\PasswordHasher;
use App\Security\RateLimiter;
use PDO;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class Kernel implements HttpKernelInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly UrlMatcher $matcher,
        private readonly AuthController $auth,
        private readonly WorkOrderController $workOrders,
        private readonly JwtService $jwt,
        private readonly PDO $pdo,
    ) {
    }

    public static function boot(string $apiRoot): self
    {
        self::loadEnv($apiRoot);
        $config = Config::fromEnv($apiRoot);
        $pdo = Sqlite::connect($config->databasePath);
        $hasher = new PasswordHasher();
        $users = new UserRepository($pdo);
        Schema::migrate($pdo, $hasher, $users);

        $jwt = new JwtService($config->appSecret, $config->jwtTtl);
        $refreshTokens = new RefreshTokenRepository($pdo, $users);
        $auth = new AuthController(
            $users,
            $hasher,
            $jwt,
            $refreshTokens,
            new RateLimiter($pdo),
            $config->cookieSecure,
            $config->refreshTtl,
        );
        $workOrders = new WorkOrderController(new WorkOrderRepository($pdo), new WorkOrderPolicy());

        $context = new RequestContext();
        $matcher = new UrlMatcher(self::routes(), $context);

        return new self($config, $matcher, $auth, $workOrders, $jwt, $pdo);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return $this->finalize($request, new Response('', 204));
        }

        try {
            $response = $this->dispatch($request);
        } catch (ResourceNotFoundException) {
            $response = Json::error('not_found', 404);
        } catch (MethodNotAllowedException) {
            $response = Json::error('method_not_allowed', 405);
        } catch (HttpException $e) {
            $details = $e->payload['fields'] ?? $e->payload;
            $response = Json::error($e->getMessage(), $e->status, is_array($details) ? $details : []);
        } catch (\Throwable) {
            $response = Json::error('internal_error', 500);
        }

        return $this->finalize($request, $response);
    }

    private function dispatch(Request $request): Response
    {
        $this->matcher->getContext()->fromRequest($request);
        $params = $this->matcher->match($request->getPathInfo());
        $request->attributes->add($params);
        $name = (string) $params['_controller'];

        $public = ['auth.login', 'auth.refresh', 'auth.logout'];
        $user = in_array($name, $public, true) ? null : $this->requireUser($request);

        return match ($name) {
            'auth.login' => $this->auth->login($request),
            'auth.refresh' => $this->auth->refresh($request),
            'auth.logout' => $this->auth->logout($request),
            'work_orders.list' => $this->workOrders->list($this->authed($user)),
            'work_orders.create' => $this->workOrders->create($request, $this->authed($user)),
            'work_orders.get' => $this->workOrders->get((string) $params['id'], $this->authed($user)),
            'work_orders.patch' => $this->workOrders->patch($request, (string) $params['id'], $this->authed($user)),
            default => throw new ResourceNotFoundException(),
        };
    }

    private function authed(?AuthenticatedUser $user): AuthenticatedUser
    {
        return $user ?? throw new HttpException(401, 'unauthorized');
    }

    private function requireUser(Request $request): AuthenticatedUser
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            throw new HttpException(401, 'unauthorized');
        }

        try {
            return $this->jwt->parse($matches[1]);
        } catch (\Throwable) {
            throw new HttpException(401, 'unauthorized');
        }
    }

    private function finalize(Request $request, Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'origin');
        $response->headers->set('Content-Security-Policy', "default-src 'none'");
        $response->headers->set('Cache-Control', 'no-store');

        $origin = $request->headers->get('Origin');
        if (is_string($origin) && in_array($origin, $this->config->corsOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PATCH, OPTIONS');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }

    private static function routes(): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add('auth.login', new Route('/api/login', ['_controller' => 'auth.login'], methods: ['POST']));
        $routes->add('auth.refresh', new Route('/api/token/refresh', ['_controller' => 'auth.refresh'], methods: ['POST']));
        $routes->add('auth.logout', new Route('/api/token/logout', ['_controller' => 'auth.logout'], methods: ['POST']));
        $routes->add('work_orders.list', new Route('/api/work-orders', ['_controller' => 'work_orders.list'], methods: ['GET']));
        $routes->add('work_orders.create', new Route('/api/work-orders', ['_controller' => 'work_orders.create'], methods: ['POST']));
        $routes->add('work_orders.get', new Route('/api/work-orders/{id}', ['_controller' => 'work_orders.get'], ['id' => '\\d+'], methods: ['GET']));
        $routes->add('work_orders.patch', new Route('/api/work-orders/{id}', ['_controller' => 'work_orders.patch'], ['id' => '\\d+'], methods: ['PATCH']));

        return $routes;
    }

    private static function loadEnv(string $apiRoot): void
    {
        $dotenv = new Dotenv();
        foreach ([$apiRoot . '/.env', dirname($apiRoot) . '/.env'] as $file) {
            if (is_file($file)) {
                $dotenv->load($file);
                break;
            }
        }
    }
}
