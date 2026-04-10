<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Paginador reutilizable.
 *
 * Uso en controller:
 *   $page  = max(1, (int)($_GET['page'] ?? 1));
 *   $total = $model->countByUsuario($uid, $filtros);
 *   $pag   = new Paginator($total, $page, 50);
 *   $rows  = $model->allByUsuario($uid, $filtros, $pag->perPage, $pag->offset);
 *
 * Uso en vista:
 *   <?php include __DIR__ . '/../partials/pagination.php'; ?>
 */
class Paginator
{
    public int $page;
    public int $perPage;
    public int $total;
    public int $totalPages;
    public int $offset;

    public function __construct(int $total, int $page = 1, int $perPage = 50)
    {
        $this->perPage    = max(1, $perPage);
        $this->total      = max(0, $total);
        $this->totalPages = max(1, (int) ceil($this->total / $this->perPage));
        $this->page       = max(1, min($page, $this->totalPages));
        $this->offset     = ($this->page - 1) * $this->perPage;
    }

    public function hasPages(): bool
    {
        return $this->totalPages > 1;
    }

    /**
     * Genera URL manteniendo los GET params actuales y reemplazando page.
     */
    public static function url(int $page, string $baseUrl = ''): string
    {
        $params         = $_GET;
        $params['page'] = $page;
        return $baseUrl . '?' . http_build_query($params);
    }
}
