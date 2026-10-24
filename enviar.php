<?php
/**
 * Mi Vaso — recibe el formulario de contacto y las cotizaciones.
 * El correo de destino, el remitente y el SMTP se configuran en /admin/.
 */
declare(strict_types=1);
require __DIR__ . '/privado/nucleo.php';

const MAX_LOGO   = 5 * 1024 * 1024;
const MAX_VISTA  = 2 * 1024 * 1024;
const MAX_LINEAS = 30;
const MIN_SEGUNDOS = 3;

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$quiereJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function responder(bool $ok, string $texto, int $codigo = 200, array $extra = []): void {
    global $quiereJson;
    http_response_code($codigo);
    if ($quiereJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(($ok ? ['ok' => true, 'mensaje' => $texto] : ['ok' => false, 'error' => $texto]) + $extra, JSON_UNESCAPED_UNICODE);
        exit;
    }
    $volver = '/';
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    if ($ref && parse_url($ref, PHP_URL_HOST) === $host) $volver = strtok($ref, '?#');
    if ($ok) { header('Location: ' . $volver . '?enviado=1', true, 303); exit; }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Mi Vaso</title><div style="font-family:Helvetica,Arial,sans-serif;max-width:40em;margin:3em auto"><p>'
        . mv_h($texto) . '</p><p><a href="' . mv_h($volver) . '">Volver</a></p></div>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); responder(false, 'Método no permitido.', 405); }

// El POST llegó vacío: los archivos superaron post_max_size del servidor
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    responder(false, 'Los archivos son demasiado pesados para el servidor. Usa logos más livianos (PNG o JPG).', 413);
}

// --- Anti-spam ---
if (mv_texto($_POST['web'] ?? '', 200) !== '') responder(true, '¡Mensaje enviado! Te responderemos a la brevedad.', 200, ['folio' => 'MV-0']);
$ts = (int) mv_texto($_POST['ts'] ?? '', 20);
if ($ts <= 0 || (time() - $ts) < MIN_SEGUNDOS || (time() - $ts) > 86400 * 3) {
    responder(false, 'No pudimos validar el envío. Recarga la página e inténtalo de nuevo.', 400);
}
if (!mv_limite('envios', 8, 3600, false)) {
    responder(false, 'Recibimos varios mensajes desde tu conexión. Escríbenos por WhatsApp al +56 9 5043 5981.', 429);
}

$cfg = mv_config();
$tipo     = mv_texto($_POST['tipo'] ?? '', 20) === 'cotizacion' ? 'cotizacion' : 'contacto';
$nombre   = mv_texto($_POST['nombre'] ?? '', 120);
$correo   = mv_texto($_POST['correo'] ?? '', 160);
$telefono = mv_texto($_POST['telefono'] ?? '', 40);
$mensaje  = mv_texto($_POST['mensaje'] ?? '', 4000, true);
if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) responder(false, 'Completa tu nombre y un correo válido.', 422);

$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
function tipo_real($finfo, string $ruta): string { return $finfo ? (string) finfo_file($finfo, $ruta) : ''; }
function nombre_seguro(string $n, string $ext): string {
    $b = preg_replace('/[^A-Za-z0-9_-]+/', '-', pathinfo($n, PATHINFO_FILENAME));
    $b = trim(substr((string) $b, 0, 50), '-') ?: 'archivo';
    return $b . '.' . $ext;
}

$estilo = 'font-family:Helvetica,Arial,sans-serif;color:#262B24';
$fila = fn($k, $v) => '<tr><td style="padding:6px 12px 6px 0;color:#5B6358;white-space:nowrap;vertical-align:top">' . mv_h($k) . '</td><td style="padding:6px 0;font-weight:bold">' . nl2br(mv_h($v)) . '</td></tr>';

// =====================================================================
// CONTACTO
// =====================================================================
if ($tipo === 'contacto') {
    if ($mensaje === '') responder(false, 'Escribe tu mensaje.', 422);
    $texto = "NUEVO MENSAJE DESDE MIVASO.CL\n\nNombre: $nombre\nCorreo: $correo\nTeléfono: " . ($telefono ?: '(no indicado)') . "\n\nMENSAJE\n$mensaje\n";
    $html = '<div style="' . $estilo . ';max-width:640px"><h2 style="color:#4B873E;margin:0 0 12px">Nuevo mensaje desde mivaso.cl</h2><table cellpadding="0" cellspacing="0">'
        . $fila('Nombre', $nombre) . $fila('Correo', $correo) . $fila('Teléfono', $telefono ?: '(no indicado)')
        . '</table><h3 style="margin:20px 0 6px">Mensaje</h3><p style="white-space:pre-wrap;margin:0">' . mv_h($mensaje) . '</p>'
        . '<p style="color:#7A8276;font-size:12px;margin-top:24px">Responde a este correo para contestarle directamente.</p></div>';
    [$cab, $cuerpo] = mv_mime($cfg, $correo, $nombre, $texto, $html);
    $err = mv_enviar($cfg, $cfg['destinatarios'], $cfg['cc'], 'Contacto web — ' . $nombre, $cab, $cuerpo);
    if ($err !== '') { error_log('[mivaso] ' . $err); responder(false, 'No pudimos enviar el mensaje en este momento. Escríbenos a ' . ($cfg['destinatarios'][0] ?? 'contacto@mivaso.cl') . ' o por WhatsApp al +56 9 5043 5981.', 500); }
    mv_limite('envios', 8, 3600, true);
    responder(true, '¡Mensaje enviado! Te responderemos a la brevedad.');
}

// =====================================================================
// COTIZACIÓN
// =====================================================================
$rut     = mv_texto($_POST['rut'] ?? '', 14);
$empresa = mv_texto($_POST['empresa'] ?? '', 120);
$fecha   = mv_texto($_POST['fecha'] ?? '', 10);
$entrega = mv_texto($_POST['entrega'] ?? '', 20) === 'Despacho' ? 'Despacho' : 'Retiro';
$comuna  = mv_texto($_POST['comuna'] ?? '', 80);
if ($telefono === '' || $rut === '') responder(false, 'Faltan tu teléfono y tu RUT.', 422);
$f = DateTime::createFromFormat('!Y-m-d', $fecha);
if (!$f || $f->format('Y-m-d') !== $fecha) responder(false, 'Indica la fecha en que necesitas los productos.', 422);
if ($entrega === 'Despacho' && $comuna === '') responder(false, 'Indica la comuna o ciudad de despacho.', 422);

$lineasIn = json_decode((string) ($_POST['lineas'] ?? ''), true);
if (!is_array($lineasIn) || !$lineasIn) responder(false, 'Tu cotización no tiene productos.', 422);
if (count($lineasIn) > MAX_LINEAS) responder(false, 'Puedes cotizar hasta ' . MAX_LINEAS . ' líneas por solicitud.', 422);

// Logos (se envían una sola vez aunque se usen en varias líneas)
$extLogo = ['png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'pdf' => ['application/pdf'],
            'svg' => ['image/svg+xml', 'text/xml', 'text/plain', 'application/xml', 'text/html'],
            'ai'  => ['application/pdf', 'application/postscript', 'application/illustrator'],
            'eps' => ['application/postscript', 'image/x-eps', 'application/eps', 'application/octet-stream']];
$logos = [];
for ($k = 0; $k < MAX_LINEAS; $k++) {
    $campo = 'logo_' . $k;
    if (!isset($_FILES[$campo]) || ($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
    $fl = $_FILES[$campo];
    if ($fl['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($fl['tmp_name'])) responder(false, 'No pudimos recibir uno de los logos. Inténtalo de nuevo.', 400);
    if ($fl['size'] > MAX_LOGO) responder(false, 'Uno de los logos pesa más de 5 MB.', 413);
    $ext = strtolower(pathinfo((string) $fl['name'], PATHINFO_EXTENSION));
    if (!isset($extLogo[$ext])) responder(false, 'Formato de logo no permitido. Usa PNG, JPG, SVG, PDF, AI o EPS.', 415);
    $mime = tipo_real($finfo, $fl['tmp_name']);
    if ($mime !== '' && !in_array($mime, $extLogo[$ext], true)) responder(false, 'Uno de los logos no corresponde a su extensión.', 415);
    $logos[$k] = ['nombre' => nombre_seguro((string) $fl['name'], $ext), 'tipo' => $extLogo[$ext][0], 'datos' => (string) file_get_contents($fl['tmp_name'])];
}

$lineas = []; $vistas = [];
foreach (array_values($lineasIn) as $i => $l) {
    if (!is_array($l)) responder(false, 'Datos de cotización inválidos.', 422);
    $cant = (int) ($l['cantidad'] ?? 0);
    if ($cant < 100 || $cant > 1000000) responder(false, 'Revisa las cantidades de tu cotización.', 422);
    $logoRef = isset($l['logo']) && is_int($l['logo']) && isset($logos[$l['logo']]) ? $l['logo'] : null;
    $vista = null;
    if (isset($l['vista']) && is_int($l['vista']) && isset($_FILES['vista_' . $l['vista']])) {
        $fv = $_FILES['vista_' . $l['vista']];
        if ($fv['error'] === UPLOAD_ERR_OK && is_uploaded_file($fv['tmp_name']) && $fv['size'] <= MAX_VISTA && tipo_real($finfo, $fv['tmp_name']) === 'image/jpeg') {
            $vista = count($vistas);
            $vistas[] = ['cid' => 'vista' . $vista . '.' . bin2hex(random_bytes(4)) . '@mivaso', 'nombre' => 'vista-previa-' . ($i + 1) . '.jpg', 'tipo' => 'image/jpeg', 'datos' => (string) file_get_contents($fv['tmp_name'])];
        }
    }
    $lineas[] = [
        'producto' => mv_texto($l['nombre'] ?? '', 100), 'slug' => preg_replace('/[^a-z0-9-]/', '', (string) ($l['slug'] ?? '')),
        'color' => mv_texto($l['color'] ?? '', 60), 'color_stock' => !empty($l['colorStock']), 'variante' => mv_texto($l['variante'] ?? '', 80),
        'cantidad' => $cant, 'tintas' => mv_texto($l['tintas'] ?? '', 40), 'tinta_color' => mv_texto($l['tintaColor'] ?? '', 60),
        'logo' => $logoRef, 'logo_nombre' => $logoRef !== null ? $logos[$logoRef]['nombre'] : '', 'vista' => $vista,
    ];
}
$totalU = array_sum(array_column($lineas, 'cantidad'));
$folio = 'MV-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
$fechaTxt = $f->format('d-m-Y');

// --- Texto plano ---
$t = ["SOLICITUD DE COTIZACIÓN $folio", '', 'SOLICITANTE', "Nombre:   $nombre"];
if ($empresa !== '') $t[] = "Empresa:  $empresa";
array_push($t, "Correo:   $correo", "Teléfono: $telefono", "RUT:      $rut", '', "Fecha requerida: $fechaTxt", 'Entrega: ' . $entrega . ($entrega === 'Despacho' ? " ($comuna)" : ''), '', 'PRODUCTOS');
foreach ($lineas as $n => $l) {
    $t[] = ($n + 1) . '. ' . $l['producto'] . ($l['variante'] ? ' — ' . $l['variante'] : '');
    $t[] = '   Color: ' . $l['color'] . ($l['color_stock'] ? ' (en stock)' : '') . ' · Cantidad: ' . number_format($l['cantidad'], 0, ',', '.') . ' u.';
    $t[] = '   Impresión: ' . $l['tintas'] . ($l['tinta_color'] ? ' · tinta ' . $l['tinta_color'] : '') . ' · Logo: ' . ($l['logo_nombre'] ?: 'sin logo');
}
array_push($t, '', 'Total: ' . number_format($totalU, 0, ',', '.') . ' unidades', '', 'COMENTARIOS', $mensaje !== '' ? $mensaje : '(sin comentarios)');
$texto = implode("\n", $t);

// --- HTML ---
$filasProd = '';
foreach ($lineas as $n => $l) {
    $img = $l['vista'] !== null ? '<img src="cid:' . $vistas[$l['vista']]['cid'] . '" width="180" alt="Vista previa" style="display:block;width:180px;height:auto;border-radius:6px;border:1px solid #DCE4D8">' : '<div style="width:180px;padding:30px 0;text-align:center;color:#7A8276;border:1px dashed #DCE4D8">Sin vista previa</div>';
    $filasProd .= '<tr><td style="padding:14px 16px 14px 0;vertical-align:top;border-top:1px solid #DCE4D8">' . $img . '</td>'
        . '<td style="padding:14px 0;vertical-align:top;border-top:1px solid #DCE4D8"><div style="font-size:17px;font-weight:bold;color:#4B873E;margin-bottom:6px">' . ($n + 1) . '. ' . mv_h($l['producto']) . '</div><table cellpadding="0" cellspacing="0" style="font-size:14px">'
        . ($l['variante'] ? $fila('Modelo', $l['variante']) : '')
        . $fila('Color', $l['color'] . ($l['color_stock'] ? ' (en stock)' : ''))
        . $fila('Cantidad', number_format($l['cantidad'], 0, ',', '.') . ' unidades')
        . $fila('Impresión', $l['tintas'] . ($l['tinta_color'] ? ' · tinta ' . $l['tinta_color'] : ''))
        . $fila('Logo', $l['logo_nombre'] ?: 'Sin logo (lo enviará después)')
        . '</table></td></tr>';
}
$html = '<div style="' . $estilo . ';max-width:680px">'
    . '<div style="background:#4B873E;color:#fff;padding:18px 20px;border-radius:8px 8px 0 0"><div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;opacity:.85">Solicitud de cotización</div><div style="font-size:22px;font-weight:bold">' . mv_h($folio) . '</div></div>'
    . '<div style="border:1px solid #DCE4D8;border-top:0;padding:20px;border-radius:0 0 8px 8px">'
    . '<table cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:8px"><tr>'
    . '<td style="vertical-align:top;padding-right:16px"><h3 style="margin:0 0 8px;font-size:13px;letter-spacing:1px;text-transform:uppercase;color:#5B6358">Solicitante</h3><table cellpadding="0" cellspacing="0" style="font-size:14px">'
    . $fila('Nombre', $nombre) . ($empresa !== '' ? $fila('Empresa', $empresa) : '') . $fila('Correo', $correo) . $fila('Teléfono', $telefono) . $fila('RUT', $rut) . '</table></td>'
    . '<td style="vertical-align:top"><h3 style="margin:0 0 8px;font-size:13px;letter-spacing:1px;text-transform:uppercase;color:#5B6358">Pedido</h3><table cellpadding="0" cellspacing="0" style="font-size:14px">'
    . $fila('Fecha requerida', $fechaTxt) . $fila('Entrega', $entrega . ($entrega === 'Despacho' ? ' · ' . $comuna : '')) . $fila('Total', number_format($totalU, 0, ',', '.') . ' unidades') . '</table></td>'
    . '</tr></table>'
    . '<h3 style="margin:18px 0 0;font-size:13px;letter-spacing:1px;text-transform:uppercase;color:#5B6358">Productos</h3><table cellpadding="0" cellspacing="0" style="width:100%">' . $filasProd . '</table>'
    . '<h3 style="margin:18px 0 6px;font-size:13px;letter-spacing:1px;text-transform:uppercase;color:#5B6358">Comentarios</h3><p style="margin:0;white-space:pre-wrap">' . mv_h($mensaje !== '' ? $mensaje : 'Sin comentarios.') . '</p>'
    . '<p style="color:#7A8276;font-size:12px;margin-top:24px">Las vistas previas son referenciales y las arma el cliente en el sitio. Los logos originales van adjuntos. Responde a este correo para contestarle directamente.</p>'
    . '</div></div>';

// --- Historial (copia en el servidor, visible en /admin/) ---
if (!empty($cfg['guardar_historial'])) {
    $dir = MV_COTIZACIONES . '/' . $folio . '-' . bin2hex(random_bytes(8));
    if (@mkdir($dir, 0700, true)) {
        foreach ($vistas as $n => $v) @file_put_contents("$dir/vista-$n.jpg", $v['datos']);
        foreach ($logos as $k => $lg) @file_put_contents("$dir/logo-$k-" . $lg['nombre'], $lg['datos']);
        @file_put_contents("$dir/datos.json", json_encode([
            'folio' => $folio, 'recibido' => date('c'), 'ip' => mv_ip(),
            'solicitante' => compact('nombre', 'empresa', 'correo', 'telefono', 'rut'),
            'fecha_requerida' => $fecha, 'entrega' => $entrega, 'comuna' => $comuna, 'mensaje' => $mensaje,
            'lineas' => $lineas, 'logos' => array_map(fn($k, $lg) => "logo-$k-" . $lg['nombre'], array_keys($logos), $logos),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

$asunto = 'Cotización ' . $folio . ' — ' . ($empresa !== '' ? $empresa : $nombre) . ' — ' . number_format($totalU, 0, ',', '.') . ' u.';
[$cab, $cuerpo] = mv_mime($cfg, $correo, $nombre, $texto, $html, $vistas, array_values($logos));
$err = mv_enviar($cfg, $cfg['destinatarios'], $cfg['cc'], $asunto, $cab, $cuerpo);
if ($err !== '') {
    error_log('[mivaso] ' . $err);
    if (empty($cfg['guardar_historial'])) responder(false, 'No pudimos enviar tu cotización. Escríbenos a ' . ($cfg['destinatarios'][0] ?? 'contacto@mivaso.cl') . ' o por WhatsApp.', 500);
    // Quedó guardada en el historial: el equipo la verá en el panel aunque el correo haya fallado.
}

// Copia al solicitante (opcional, sin logos adjuntos)
if (!empty($cfg['copia_cliente'])) {
    $intro = '<p style="' . $estilo . '">Hola ' . mv_h($nombre) . ', recibimos tu solicitud de cotización. Este es el resumen; te responderemos a la brevedad.</p>';
    [$cab2, $cuerpo2] = mv_mime($cfg, $cfg['destinatarios'][0] ?? '', 'Mi Vaso', "Recibimos tu solicitud de cotización.\n\n" . $texto, $intro . $html, $vistas);
    mv_enviar($cfg, [$correo], [], 'Recibimos tu cotización ' . $folio . ' — Mi Vaso', $cab2, $cuerpo2);
}

mv_limite('envios', 8, 3600, true);
responder(true, '¡Cotización enviada! Te responderemos a la brevedad.', 200, ['folio' => $folio]);
