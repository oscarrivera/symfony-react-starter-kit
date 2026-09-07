# Seguridad

## Avisos

No abrir issues públicos con explotaciones, dumps ni secretos. Enviar un correo al mantenedor del repositorio e incluir: impacto, versión/commit, pasos mínimos. No se publicará un CVE improvisado desde el issue tracker.

El usuario semilla `tech@example.test` / `ChangeMe_now-1` es de desarrollo. Hay que cambiarlo (o borrar el usuario y recrearlo) antes de cualquier red no local.

## Secretos

- No commitear `.env`, `api/var/data.sqlite` ni claves reales.
- `APP_SECRET` ≥ 32 caracteres. Es la clave HS256. Rotarla invalida todos los JWT vigentes.
- El refresh token en claro solo viaja en la cookie; en SQLite se guarda `sha256`. Rotación en cada `/api/token/refresh`.
- Contraseñas: Argon2id. Nunca loguear `password`, `Authorization` ni el valor de `refresh_token`.

## Cabeceras

La API fuerza `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: origin` y `CSP default-src 'none'`. Sirve JSON, no HTML. El frontend tiene su propio origen (Vite); no reutiliza esa CSP.

CORS: lista blanca en `CORS_ORIGINS`. Sin coincidencia no hay `Access-Control-Allow-Origin`.

## JWT y cookies

- Access token: 15 min, HS256, claims `sub`, `email`, `role`, `iat`, `exp`. Robo vía XSS en el SPA sigue siendo posible mientras el token esté en memoria; el TTL corto limita la ventana. No usar HS256 con un secreto predecible.
- Refresh: cookie `HttpOnly; SameSite=Strict; Path=/api/token`. `Secure` debe estar a 1 detrás de HTTPS. Strict + orígenes distintos (5173 vs 8080) exige el proxy de Vite u otro mismo-sitio.
- Rate limit de login: 5 POST / 15 min / IP. Tras un proxy hay que fijar IPs de confianza; este MVP no confía en `X-Forwarded-For`.

## Alcance

Esto no es un producto endurecido para internet abierto: no hay WAF, auditoría de accesos, rotación automática de `APP_SECRET` ni protección anti-replay más allá de la rotación del refresh.
