<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Generador de emails HTML con el branding BALTAE.
 *
 * Uso:
 *   $html = EmailTemplate::build('Pedido de pienso', $bodyHtml, 'blue');
 *   (new Mailer())->send($to, $subject, $html, true);
 *
 * Colores de acento disponibles:
 *   'blue'   — informativo (pedidos, inventarios, notificaciones generales)
 *   'orange' — warning (alertas de seguridad, login nueva IP)
 *   'red'    — critical (bajas, silos bajo mínimo)
 */
class EmailTemplate
{
    // ── Paleta ──────────────────────────────────────────────────
    private const ACCENT = [
        'blue'   => 'linear-gradient(90deg,#3b82f6,#1d4ed8)',
        'orange' => 'linear-gradient(90deg,#f59e0b,#d97706)',
        'red'    => 'linear-gradient(90deg,#ef4444,#dc2626)',
    ];

    // ── API pública ─────────────────────────────────────────────

    /**
     * Construye el email HTML completo (header + body + footer).
     */
    public static function build(string $bodyHtml, string $accent = 'blue'): string
    {
        $bar = self::ACCENT[$accent] ?? self::ACCENT['blue'];

        return '<!DOCTYPE html>'
            . '<html lang="es"><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#e5e7eb">'
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#e5e7eb;padding:24px 0">'
            . '<tr><td align="center">'
            . '<table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;font-family:Arial,\'Helvetica Neue\',Helvetica,sans-serif;color:#111827;font-size:14px;line-height:1.6;border-radius:4px;overflow:hidden">'
            // Header
            . self::header()
            // Accent bar
            . '<tr><td style="height:4px;background:' . $bar . ';font-size:0;line-height:0">&nbsp;</td></tr>'
            // Body
            . '<tr><td style="padding:32px 32px 24px">'
            . $bodyHtml
            . '</td></tr>'
            // Divider + Footer
            . '<tr><td style="padding:0 32px"><div style="border-top:1px solid #e5e7eb"></div></td></tr>'
            . self::footer()
            // Bottom bar
            . '<tr><td style="height:6px;background:#111827;font-size:0;line-height:0">&nbsp;</td></tr>'
            . '</table>'
            . '</td></tr></table>'
            . '</body></html>';
    }

    // ── Bloques de contenido reutilizables ───────────────────────

    /**
     * Título + subtítulo opcional (fecha, etc.)
     */
    public static function title(string $text, ?string $subtitle = null, string $color = '#1e3a5f'): string
    {
        $h = '<h1 style="margin:0 0 6px;font-size:20px;font-weight:700;color:' . $color . '">' . e($text) . '</h1>';
        if ($subtitle) {
            $h .= '<p style="margin:0 0 24px;font-size:13px;color:#9ca3af">' . $subtitle . '</p>';
        } else {
            $h .= '<div style="margin-bottom:24px"></div>';
        }
        return $h;
    }

    /**
     * Tabla de datos clave-valor (ej: Silo, Granja, Stock actual…).
     *
     * @param array $rows [['label' => 'Silo', 'value' => 'Principal', 'bold' => true, 'color' => '#dc2626'], ...]
     */
    public static function dataTable(array $rows): string
    {
        $html = '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px">';
        foreach ($rows as $i => $r) {
            $isLast    = ($i === count($rows) - 1);
            $border    = $isLast ? '' : 'border-bottom:1px solid #f3f4f6;';
            $valStyle  = '';
            if (!empty($r['bold']))  $valStyle .= 'font-weight:700;';
            if (!empty($r['color'])) $valStyle .= 'color:' . $r['color'] . ';';
            if (!empty($r['size']))  $valStyle .= 'font-size:' . $r['size'] . ';';

            $html .= '<tr>'
                . '<td style="padding:8px 0;' . $border . 'color:#6b7280;font-size:13px;width:170px">' . e($r['label']) . '</td>'
                . '<td style="padding:8px 0;' . $border . $valStyle . '">' . ($r['value'] ?? '') . '</td>'
                . '</tr>';
        }
        $html .= '</table>';
        return $html;
    }

    /**
     * Tabla con cabeceras y filas (ej: lotes en consumo, detalle bajas).
     *
     * @param array  $headers ['Lote', 'Nave', ...]
     * @param array  $rows    [['LOT-001', 'Nave 1', ...], ...]
     * @param array  $aligns  ['left', 'left', 'center', 'right', ...]
     * @param string $headerBg Color de fondo de cabecera
     */
    public static function table(array $headers, array $rows, array $aligns = [], string $headerBg = '#1e3a5f'): string
    {
        $html = '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden">';

        // thead
        $html .= '<thead><tr style="background:' . $headerBg . ';color:#ffffff">';
        foreach ($headers as $i => $h) {
            $align = $aligns[$i] ?? 'left';
            $html .= '<th style="padding:8px 12px;text-align:' . $align . ';font-weight:600">' . e($h) . '</th>';
        }
        $html .= '</tr></thead>';

        // tbody
        $html .= '<tbody>';
        foreach ($rows as $ri => $row) {
            $bg = $ri % 2 === 0 ? '#ffffff' : '#f9fafb';
            $html .= '<tr style="background:' . $bg . '">';
            foreach ($row as $ci => $cell) {
                $align  = $aligns[$ci] ?? 'left';
                $border = ($ri < count($rows) - 1) ? 'border-bottom:1px solid #f3f4f6;' : '';
                $html  .= '<td style="padding:7px 12px;' . $border . 'text-align:' . $align . '">' . $cell . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Subtítulo de sección (h2 pequeño).
     */
    public static function sectionTitle(string $text): string
    {
        return '<h2 style="margin:0 0 12px;font-size:14px;font-weight:700;color:#374151">' . e($text) . '</h2>';
    }

    /**
     * Caja de alerta coloreada (para warnings, errores…).
     */
    public static function alertBox(string $innerHtml, string $type = 'warning'): string
    {
        $styles = match ($type) {
            'danger'  => ['bg' => '#fef2f2', 'border' => '#fecaca', 'text' => '#991b1b'],
            'success' => ['bg' => '#f0fdf4', 'border' => '#bbf7d0', 'text' => '#166534'],
            default   => ['bg' => '#fffbeb', 'border' => '#fde68a', 'text' => '#92400e'],
        };
        return '<div style="background:' . $styles['bg'] . ';border:1px solid ' . $styles['border']
            . ';border-radius:8px;padding:20px;margin-bottom:24px;color:' . $styles['text'] . '">'
            . $innerHtml
            . '</div>';
    }

    /**
     * Botón CTA centrado.
     */
    public static function button(string $text, string $url, string $bg = '#1d4ed8'): string
    {
        return '<div style="text-align:center;margin:24px 0 8px">'
            . '<a href="' . e($url) . '" style="display:inline-block;background:' . $bg
            . ';color:#ffffff;font-weight:700;font-size:14px;padding:12px 32px;border-radius:6px;text-decoration:none">'
            . e($text)
            . '</a></div>';
    }

    /**
     * Párrafo de texto.
     */
    public static function paragraph(string $text, string $color = '#374151'): string
    {
        return '<p style="margin:0 0 20px;font-size:14px;color:' . $color . '">' . $text . '</p>';
    }

    /**
     * Bloque de resumen gris (ej: acumulado mensual).
     */
    public static function summaryBox(string $titleText, array $rows): string
    {
        $html = '<div style="margin-top:20px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px 20px">'
            . '<h3 style="margin:0 0 8px;font-size:13px;font-weight:700;color:#374151">' . e($titleText) . '</h3>'
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:12px">';
        foreach ($rows as $r) {
            $valStyle = !empty($r['color']) ? 'color:' . $r['color'] . ';' : '';
            $html .= '<tr>'
                . '<td style="color:#6b7280;padding:3px 0">' . e($r['label']) . '</td>'
                . '<td style="font-weight:700;text-align:right;' . $valStyle . '">' . ($r['value'] ?? '') . '</td>'
                . '</tr>';
        }
        $html .= '</table></div>';
        return $html;
    }

    // ── Partes privadas del layout ──────────────────────────────

    private static function header(): string
    {
        return '<tr><td style="background:#111827;padding:24px 32px;text-align:center">'
            . '<table cellpadding="0" cellspacing="0" border="0" align="center"><tr>'
            . '<td style="width:40px;height:40px;background:#3b82f6;border-radius:10px;text-align:center;vertical-align:middle;font-size:22px;font-weight:800;color:#ffffff;font-family:Arial,sans-serif;letter-spacing:-1px">B</td>'
            . '<td style="padding-left:14px;font-size:22px;font-weight:700;color:#ffffff;letter-spacing:.5px;font-family:Arial,sans-serif">BALTAE</td>'
            . '</tr></table>'
            . '</td></tr>';
    }

    private static function footer(): string
    {
        return '<tr><td style="padding:20px 32px 16px;text-align:center">'
            . '<p style="margin:0 0 6px;font-size:12px;color:#9ca3af">Este email ha sido generado automaticamente por</p>'
            . '<p style="margin:0 0 12px"><a href="https://granja.baltae.com" style="color:#3b82f6;font-weight:700;font-size:13px;text-decoration:none">granja.baltae.com</a></p>'
            . '<p style="margin:0;font-size:10px;color:#d1d5db;line-height:1.5">'
            . 'BALTAE Soluciones Ganaderas &middot; Este mensaje es confidencial y esta dirigido exclusivamente a su destinatario.<br>'
            . 'Si lo ha recibido por error, elimine el mensaje y notifiquelo al remitente.</p>'
            . '</td></tr>';
    }
}
