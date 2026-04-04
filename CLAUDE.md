# BALTAE – Instrucciones para Claude Code

## Flujo de trabajo

- Trabaja siempre directamente en la rama `main`. No crear ramas ni PRs.
- Después de cada sesión de cambios, commitear en `main` y hacer push a `origin/main`.
- El despliegue lo hace el usuario con: `git pull origin main && git ftp push`

## Proyecto

Aplicación PHP de gestión de granjas (BALTAE) en `/Users/ignacionavas/Documents/GitHub/Granja`.
Sin Composer. Autoloader PSR-4 propio. PDO + MySQL.
