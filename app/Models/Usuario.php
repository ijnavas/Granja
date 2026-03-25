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
     * Busca un usuario por email
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Busca un usuario por ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, email, activo, created_at FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
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
        $user = $this->findByEmail($email);
        if (!$user) return null;

        if (!password_verify($password, $user['password_hash'])) return null;

        // Re-hashear si el coste ha cambiado
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $this->updatePassword($user['id'], $password);
        }

        return $user;
    }

    /**
     * Actualiza el hash de contraseña (uso interno)
     */
    private function updatePassword(int $id, string $password): void
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios SET password_hash = :hash WHERE id = :id'
        );
        $stmt->execute([
            'hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'id'   => $id,
        ]);
    }
}
