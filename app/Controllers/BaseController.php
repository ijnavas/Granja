<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Core\SecurityLog;

abstract class BaseController
{
    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        view($view, $data, $layout);
    }

    protected function redirect(string $path): never
    {
        redirect($path);
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    protected function postString(string $key): string
    {
        return trim((string)($this->post($key, '')));
    }

    /**
     * Extrae solo las claves permitidas del array indicado (por defecto $_POST).
     *
     * Sirve como allowlist explícita para evitar mass assignment: en lugar de
     * pasar $_POST entero a un modelo, se pasa solo lo que esperas.
     *
     *   $data = $this->only(['nombre', 'email']);
     *   $this->model->create($data);
     *
     * Las claves ausentes en la fuente NO aparecen en el resultado (no se
     * rellenan con null). Si necesitas defaults, úsalos en el modelo o en
     * un array_merge previo.
     */
    protected function only(array $keys, ?array $source = null): array
    {
        $source = $source ?? $_POST;
        $out = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $source)) {
                $out[$k] = $source[$k];
            }
        }
        return $out;
    }

    /**
     * Verifica que un recurso pertenezca al usuario en sesión usando el
     * `find($id, $userId)` del modelo. Si no, registra el intento como
     * `idor_attempt` en SecurityLog y aborta vía redirect.
     *
     *   $lote = $this->ownOrAbort($this->loteModel, $loteId, 'lotes');
     *
     * @param object $model            Instancia del modelo (debe tener find($id, $uid))
     * @param int    $id               ID del recurso
     * @param string $redirectTo       Ruta a la que redirigir si falla (default: dashboard)
     * @param string $resourceName     Nombre del recurso para el log (default: clase del modelo)
     * @return array                   Fila del recurso si pertenece al usuario
     */
    protected function ownOrAbort(
        object $model,
        int $id,
        string $redirectTo = 'dashboard',
        ?string $resourceName = null
    ): array {
        $uid = (int) Session::get('usuario_id');
        $row = $model->find($id, $uid);
        if (!$row) {
            SecurityLog::log('idor_attempt', [
                'user_id'  => $uid,
                'resource' => $resourceName ?? (new \ReflectionClass($model))->getShortName(),
                'target_id'=> $id,
            ]);
            Session::flash('error', 'Recurso no encontrado o sin permisos.');
            $this->redirect($redirectTo);
        }
        return $row;
    }

    /**
     * Variante "soft" de ownOrAbort: devuelve el `$id` si pertenece al
     * usuario, o `null` si no. Usa esto cuando estás validando IDs
     * opcionales dentro de un bucle (p.ej. cuadras_asig_id[]) y quieres
     * descartar el ID en lugar de abortar todo el request.
     *
     *   $cuadraId = $this->ownedIdOrNull($this->cuadraModel, $cuadraId);
     *
     * @return int|null  El ID si pertenece al usuario; null en caso contrario.
     */
    protected function ownedIdOrNull(object $model, ?int $id): ?int
    {
        if (!$id) return null;
        $uid = (int) Session::get('usuario_id');
        if ($model->find($id, $uid)) return $id;
        SecurityLog::log('idor_attempt', [
            'user_id'  => $uid,
            'resource' => (new \ReflectionClass($model))->getShortName(),
            'target_id'=> $id,
        ]);
        return null;
    }
}
