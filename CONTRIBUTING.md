# Contribuir

Cambios pequeños y revisables. Identificadores y comentarios en inglés; documentación de producto en castellano.

1. Arrancar API y frontend según el README. No commitear `.env` ni `*.sqlite`.
2. Añadir o ajustar tests (PHPUnit en `api/tests`, Vitest en `frontend/src`).
3. No relajar CORS, no guardar el access token en almacenamiento persistente del navegador, no loguear secretos.
4. Mantener `declare(strict_types=1)` y validación en los endpoints que mutan datos.

El usuario semilla de desarrollo no debe llegar a un entorno compartido con la contraseña por defecto.
