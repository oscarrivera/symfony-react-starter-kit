# Parte de trabajo

Aplicación interna para técnicos de campo: alta y seguimiento de partes de trabajo. API PHP 8.2+ (componentes Symfony, sin FrameworkBundle) y SPA React 18.

## Por qué esta arquitectura

Se usan componentes (`http-foundation`, `routing`, `http-kernel`, `dotenv`, `validator`) en lugar de FrameworkBundle. El arranque es un `Kernel` explícito: rutas, CORS, cabeceras y auth se leen en un único flujo. Hay menos magia (bundles, DIC compilado, convenciones ocultas) y el perímetro es más fácil de auditar.

El acceso JWT vive en memoria en el cliente. El refresh va en cookie `HttpOnly` + `Secure` + `SameSite=Strict`. SQLite cubre el MVP; no hay cola ni segundo tenant.

## Cómo ejecutarlo

Requisitos: Docker, Node 20+, npm. Copiar variables:

```bash
cp .env.example .env
# Editar APP_SECRET. El valor de ejemplo no sirve en producción.
```

API (http://localhost:8080):

```bash
docker compose up --build
```

La base `api/var/data.sqlite` se crea al primer request. `vendor/` se instala dentro del contenedor.

Frontend (http://localhost:5173), con proxy Vite hacia la API:

```bash
cd frontend
npm install
npm run dev
```

Tests:

```bash
cd frontend && npm test
docker compose exec api vendor/bin/phpunit
# sin compose, desde api/: composer install && vendor/bin/phpunit
```

## Credenciales de demostración

| Campo | Valor |
| --- | --- |
| Correo | `tech@example.test` |
| Contraseña | `ChangeMe_now-1` |
| Rol | `tech` |

**Cambiar esa contraseña antes de exponer el servicio.** El usuario se inserta solo si la tabla `users` está vacía. No es una cuenta de administración.

## API

| Método | Ruta | Auth | Descripción |
| --- | --- | --- | --- |
| POST | `/api/login` | No | `{email, password}` → `{token, expiresAt}`. Cookie `refresh_token`. |
| POST | `/api/token/refresh` | Cookie | Rota el refresh y emite un access token nuevo. |
| POST | `/api/token/logout` | Cookie | Revoca el refresh y borra la cookie. |
| GET | `/api/work-orders` | Bearer | Lista. Un técnico solo ve los suyos; `role=admin` ve todos. |
| POST | `/api/work-orders` | Bearer | Crea. `assignedEmail` = usuario actual (admin puede asignar otro). |
| GET | `/api/work-orders/{id}` | Bearer | Detalle. Ajeno → `404`. |
| PATCH | `/api/work-orders/{id}` | Bearer | `title`, `description`, `status`. Ajeno → `404`. |

Validación: título 3–120, descripción ≤ 2000, `status` ∈ `open` \| `in_progress` \| `done`.

JWT: HS256, TTL 15 minutos (`JWT_TTL`). Refresh: 32 bytes aleatorios, SHA-256 en SQLite, TTL 7 días. Login: 5 intentos / 15 min por IP.

## Decisiones de seguridad

- Access token en `Authorization: Bearer`. No se persiste en `localStorage`.
- Cookie de refresh: `HttpOnly`, `SameSite=Strict`, `Path=/api/token`. `Secure` según `COOKIE_SECURE` (obligatorio en producción). Strict funciona porque el SPA llama a `/api` en el mismo origen (proxy Vite). Sin proxy, el navegador no enviaría esa cookie en un POST cross-site.
- CORS por allowlist (`CORS_ORIGINS`). Credenciales activas solo si el `Origin` está en la lista.
- Contraseñas: `password_hash` / `PASSWORD_ARGON2ID`. En login de correo inexistente se ejecuta un `password_verify` dummy.
- Cabeceras: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: origin`, `Content-Security-Policy: default-src 'none'`.
- CSRF: no hay token CSRF. El access token no lo adjunta el navegador solo; un formulario clásico cross-site no puede enviar el Bearer. La cookie de refresh es Strict y de path estrecho. Si se sirve el SPA y la API en orígenes distintos sin proxy, hay que replantear SameSite.
- No se registran contraseñas ni tokens. Los 500 no reenvían el cuerpo de la petición.
- Un técnico no distingue “no existe” de “no es tuyo” (`404`).

Detalle operativo: [SECURITY.md](SECURITY.md).

## Fuera de alcance (a propósito)

OAuth/OIDC, multi-tenant, cola asíncrona, Doctrine ORM, FrameworkBundle, panel de administración, registro público, 2FA, backups, métricas.

## Licencia

MIT. Véase [LICENSE](LICENSE).
