<?php
declare(strict_types=1);

/**
 * Configuración de la aplicación.
 *
 * Los secretos viven en .env (fuera del repo). Este archivo sólo mapea
 * variables de entorno a la estructura que espera el resto de la app.
 *
 * Si necesitas valores locales distintos (ej. desarrollo), crea un .env
 * a partir de .env.example.
 */

use App\Core\Env;

return [
    'app' => [
        'name'     => Env::get('APP_NAME', 'BALTAE'),
        'base_url' => Env::get('APP_BASE_URL', 'https://granja.baltae.com'),
        'env'      => Env::get('APP_ENV', 'production'),
        'debug'    => Env::getBool('APP_DEBUG', false),
    ],
    'db' => [
        'host'    => Env::get('DB_HOST', 'localhost'),
        'name'    => Env::get('DB_NAME', ''),
        'user'    => Env::get('DB_USER', ''),
        'pass'    => Env::get('DB_PASS', ''),
        'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    ],
    'mail' => [
        'enabled'    => Env::getBool('MAIL_ENABLED', false),
        'from_email' => Env::get('MAIL_FROM_EMAIL', ''),
        'from_name'  => Env::get('MAIL_FROM_NAME', 'APP BALTAE'),
        'smtp' => [
            'host'   => Env::get('MAIL_SMTP_HOST', ''),
            'user'   => Env::get('MAIL_SMTP_USER', ''),
            'pass'   => Env::get('MAIL_SMTP_PASS', ''),
            'port'   => Env::getInt('MAIL_SMTP_PORT', 465),
            'secure' => Env::get('MAIL_SMTP_SECURE', 'ssl'),
        ],
    ],
    'anthropic' => [
        'api_key' => Env::get('ANTHROPIC_API_KEY', ''),
    ],
];
