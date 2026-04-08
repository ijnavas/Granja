<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Usuario
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Columnas "seguras" de usuarios — NUNCA password_hash, recevet_password_enc ni recevet_session_cookie.
     * Se usan en findById/findByEmail y cualquier consumo "normal" del modelo.
     */
    private const SAFE_COLS =
        'id, nombre, apellidos, email, movil, rol, activo, '
      . 'recevet_usuario, email_pedidos, created_at, updated_at';

    /**
     * Busca un usuario por email (sin secretos).
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::SAFE_COLS . ' FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Busca un usuario por ID (sin secretos).
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::SAFE_COLS . ' FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Variante interna que SÍ devuelve password_hash + estado de lockout.
     * Solo para authenticate() y lógica de bloqueo.
     */
    private function findByEmailWithHash(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, email, password_hash, failed_login_attempts, locked_until
             FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Account lockout ──────────────────────────────────────────
    // Umbral: 10 intentos fallidos → 30 min de bloqueo.
    public const LOCKOUT_THRESHOLD = 10;
    public const LOCKOUT_MINUTES   = 30;

    /**
     * ¿La cuenta está bloqueada AHORA mismo?
     * Devuelve segundos restantes (>0) si está bloqueada, 0 si no.
     */
    public function lockoutSecondsRemaining(string $email): int
    {
        $stmt = $this->db->prepare(
            'SELECT locked_until FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $until = $stmt->fetchColumn();
        if (!$until) return 0;
        $rem = strtotime((string)$until) - time();
        return $rem > 0 ? $rem : 0;
    }

    /**
     * Registra un intento fallido. Si supera el umbral, bloquea la cuenta.
     * Devuelve true si con este intento la cuenta ha quedado bloqueada.
     */
    public function registerFailedLogin(string $email): bool
    {
        // Solo actúa si la cuenta existe (no filtra: la ruta de error es la misma).
        $stmt = $this->db->prepare(
            'SELECT id, failed_login_attempts FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        if (!$row) return false;

        $newCount = (int)$row['failed_login_attempts'] + 1;
        $shouldLock = $newCount >= self::LOCKOUT_THRESHOLD;

        if ($shouldLock) {
            $lockUntil = date('Y-m-d H:i:s', time() + self::LOCKOUT_MINUTES * 60);
            $upd = $this->db->prepare(
                'UPDATE usuarios
                 SET failed_login_attempts = :n,
                     locked_until          = :until,
                     last_failed_login_at  = NOW()
                 WHERE id = :id'
            );
            $upd->execute(['n' => $newCount, 'until' => $lockUntil, 'id' => (int)$row['id']]);
        } else {
            $upd = $this->db->prepare(
                'UPDATE usuarios
                 SET failed_login_attempts = :n,
                     last_failed_login_at  = NOW()
                 WHERE id = :id'
            );
            $upd->execute(['n' => $newCount, 'id' => (int)$row['id']]);
        }
        return $shouldLock;
    }

    /**
     * Resetea los contadores de lockout tras un login exitoso.
     */
    public function clearFailedLogins(int $id): void
    {
        $this->db->prepare(
            'UPDATE usuarios
             SET failed_login_attempts = 0,
                 locked_until          = NULL
             WHERE id = :id'
        )->execute(['id' => $id]);
    }

    /**
     * ¿Hay sesión Recevet activa? (no expone la cookie)
     */
    public function hasRecevetSession(int $id): bool
    {
        $stmt = $this->db->prepare(
            'SELECT recevet_session_cookie FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Devuelve recevet_password_enc + recevet_session_cookie (uso restringido a RecevtController).
     */
    public function findRecevetSecretsById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT recevet_usuario, recevet_password_enc, recevet_session_cookie
             FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateRecevet(int $id, string $usuario, ?string $passwordEnc, ?string $sessionCookie = null): void
    {
        $sql    = 'UPDATE usuarios SET recevet_usuario = :usuario, recevet_password_enc = :password_enc';
        $params = ['usuario' => $usuario, 'password_enc' => $passwordEnc, 'id' => $id];

        if ($sessionCookie !== null) {
            $sql .= ', recevet_session_cookie = :session_cookie';
            $params['session_cookie'] = $sessionCookie ?: null;
        }

        $sql .= ' WHERE id = :id';
        $this->db->prepare($sql)->execute($params);
    }

    public function updateEmailPedidos(int $id, string $email): void
    {
        $this->db->prepare('UPDATE usuarios SET email_pedidos = :email WHERE id = :id')
            ->execute(['email' => strtolower(trim($email)), 'id' => $id]);
    }

    public function updatePerfil(int $id, string $nombre, string $apellidos, string $email, string $movil): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios SET nombre = :nombre, apellidos = :apellidos, email = :email, movil = :movil WHERE id = :id'
        );
        return $stmt->execute([
            'nombre'    => trim($nombre),
            'apellidos' => trim($apellidos),
            'email'     => strtolower(trim($email)),
            'movil'     => trim($movil),
            'id'        => $id,
        ]);
    }

    public function emailExistsForOther(string $email, int $exceptId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM usuarios WHERE email = :email AND id != :id');
        $stmt->execute(['email' => strtolower(trim($email)), 'id' => $exceptId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function changePassword(int $id, string $currentPassword, string $newPassword): bool|string
    {
        $stmt = $this->db->prepare('SELECT password_hash FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $hash = $stmt->fetchColumn();
        if (!$hash || !password_verify($currentPassword, $hash)) {
            return 'La contraseña actual no es correcta.';
        }
        $this->resetPasswordById($id, $newPassword);
        return true;
    }

    /**
     * Comprueba si un email ya existe
     */
    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM usuarios WHERE email = :email'
        );
        $stmt->execute(['email' => $email]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Crea un nuevo usuario y devuelve su ID
     */
    public function create(string $nombre, string $email, string $password): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (nombre, email, password_hash)
             VALUES (:nombre, :email, :hash)'
        );
        $stmt->execute([
            'nombre' => trim($nombre),
            'email'  => strtolower(trim($email)),
            'hash'   => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Verifica email + contraseña y devuelve el usuario si es válido
     */
    public function authenticate(string $email, string $password): ?array
    {
        $row = $this->findByEmailWithHash($email);
        if (!$row) return null;

        if (!password_verify($password, $row['password_hash'])) return null;

        // Re-hashear si el coste ha cambiado
        if (password_needs_rehash($row['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $this->updatePassword((int)$row['id'], $password);
        }

        // Devolver el usuario "seguro" (sin password_hash) — el resto del sistema lo espera así.
        return $this->findById((int)$row['id']);
    }

    /**
     * Actualiza el hash de contraseña (uso interno)
     */
    private function updatePassword(int $id, string $password): void
    {
        $this->resetPasswordById($id, $password);
    }

    /**
     * Actualiza la contraseña de un usuario por ID (uso público para reset)
     */
    public function resetPasswordById(int $id, string $password): void
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios SET password_hash = :hash WHERE id = :id'
        );
        $stmt->execute([
            'hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'id'   => $id,
        ]);
    }

    // ── Password reset tokens ────────────────────────────────────

    /**
     * Crea un token de restablecimiento (válido 1 hora).
     *
     * En BD se guarda HASH(sha256) del token, nunca el plain.
     * Si la BD se filtra, los tokens activos no se pueden usar.
     *
     * Devuelve el token plain (64 chars hex) que se envía por email.
     */
    public function createPasswordReset(string $email): string
    {
        // Limpiar tokens anteriores del mismo email
        $del = $this->db->prepare('DELETE FROM password_resets WHERE email = :email');
        $del->execute(['email' => $email]);

        $plain     = bin2hex(random_bytes(32));     // 64 chars hex
        $hashed    = hash('sha256', $plain);        // 64 chars hex
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $stmt = $this->db->prepare(
            'INSERT INTO password_resets (email, token, expires_at)
             VALUES (:email, :token, :expires_at)'
        );
        $stmt->execute(['email' => $email, 'token' => $hashed, 'expires_at' => $expiresAt]);

        return $plain;
    }

    /**
     * Busca un reset válido (no expirado) por su token plain.
     * Internamente lo hashea y busca el hash en BD.
     * Devuelve ['email' => ...] o null.
     */
    public function findValidReset(string $token): ?array
    {
        $hashed = hash('sha256', $token);
        $stmt = $this->db->prepare(
            'SELECT email FROM password_resets
             WHERE token = :token AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['token' => $hashed]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Elimina el token de reset (después de usarlo, garantiza un solo uso)
     */
    public function deletePasswordReset(string $token): void
    {
        $hashed = hash('sha256', $token);
        $stmt = $this->db->prepare('DELETE FROM password_resets WHERE token = :token');
        $stmt->execute(['token' => $hashed]);
    }
}
