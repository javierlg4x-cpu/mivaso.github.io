<?php
/**
 * Mi Vaso — panel de administración.
 * Define a qué correo llegan las cotizaciones, revisa el historial y prueba el envío.
 */
declare(strict_types=1);
require __DIR__ . '/sesion.php';
$msg = ''; $err = '';

function codigo_instalacion(string $f): string {
    if (!is_file($f)) {
        $c = strtoupper(implode('-', str_split(bin2hex(random_bytes(6)), 4)));
        @file_put_contents($f, "<?php\n// Código de instalación del panel Mi Vaso. Úsalo una vez en /admin/ y luego este archivo se borra solo.\n// CODIGO: $c\n", LOCK_EX);
        @chmod($f, 0600);
    }
    return preg_match('/CODIGO: ([A-F0-9-]+)/', (string) @file_get_contents($f), $m) ? $m[1] : '';
}
function cotizaciones(): array {
    $out = [];
    foreach (glob(MV_COTIZACIONES . '/MV-*', GLOB_ONLYDIR) ?: [] as $d) {
        $j = json_decode((string) @file_get_contents($d . '/datos.json'), true);
        if (is_array($j)) { $j['_id'] = basename($d); $out[] = $j; }
    }
    usort($out, fn($a, $b) => strcmp((string) $b['recibido'], (string) $a['recibido']));
    return $out;
}
function dir_cotizacion(string $id): ?string {
    if (!preg_match('/^MV-\d{6}-[A-F0-9]{4}-[a-f0-9]{16}$/', $id)) return null;
    $d = MV_COTIZACIONES . '/' . $id;
    return is_dir($d) ? $d : null;
}
function borrar_dir(string $d): void { foreach (glob($d . '/*') ?: [] as $f) @unlink($f); @rmdir($d); }

// ---------------------------------------------------------------- archivos (requiere sesión)
if ($logueado && ($_GET['p'] ?? '') === 'archivo') {
    $d = dir_cotizacion((string) ($_GET['id'] ?? ''));
    $f = basename((string) ($_GET['f'] ?? ''));
    if (!$d || $f === '' || $f === 'datos.json' || !is_file("$d/$f")) { http_response_code(404); exit('No encontrado'); }
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    $inline = in_array($ext, ['jpg', 'jpeg', 'png'], true) && empty($_GET['descargar']);
    $tipos = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
    header('Content-Type: ' . ($inline ? $tipos[$ext] : 'application/octet-stream'));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $f) . '"');
    header('Content-Length: ' . filesize("$d/$f"));
    readfile("$d/$f"); exit;
}

// ---------------------------------------------------------------- acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acc = (string) ($_POST['accion'] ?? '');
    if (!csrf_ok()) { $err = 'La sesión expiró. Vuelve a intentarlo.'; }
    elseif ($acc === 'instalar' && $cfg['admin_hash'] === '') {
        if (!mv_limite('admin', 6, 900)) $err = 'Demasiados intentos. Espera 15 minutos.';
        elseif (!hash_equals(codigo_instalacion($CODIGO), strtoupper(trim((string) ($_POST['codigo'] ?? ''))))) $err = 'El código de instalación no coincide.';
        elseif (strlen((string) ($_POST['clave'] ?? '')) < 10) $err = 'La clave debe tener al menos 10 caracteres.';
        elseif ($_POST['clave'] !== ($_POST['clave2'] ?? '')) $err = 'Las claves no coinciden.';
        else {
            $cfg['admin_hash'] = password_hash((string) $_POST['clave'], PASSWORD_DEFAULT);
            if ($cfg['destinatarios'] === [] || !empty($_POST['correo'])) $cfg['destinatarios'] = mv_lista_correos((string) ($_POST['correo'] ?? '')) ?: $cfg['destinatarios'];
            if (mv_guardar_config($cfg)) { @unlink($CODIGO); session_regenerate_id(true); $_SESSION['admin'] = true; ir('ok=instalado'); }
            $err = 'No se pudo guardar la configuración. Revisa que la carpeta "privado" tenga permisos de escritura.';
        }
    }
    elseif ($acc === 'entrar') {
        if (!mv_limite('admin', 6, 900, false)) $err = 'Demasiados intentos fallidos. Espera 15 minutos.';
        elseif ($cfg['admin_hash'] !== '' && password_verify((string) ($_POST['clave'] ?? ''), $cfg['admin_hash'])) {
            session_regenerate_id(true); $_SESSION['admin'] = true; ir();
        } else { mv_limite('admin', 6, 900, true); $err = 'Clave incorrecta.'; usleep(400000); }
    }
    elseif ($logueado && $acc === 'salir') { $_SESSION = []; session_destroy(); ir(); }
    elseif ($logueado && $acc === 'ajustes') {
        $dest = mv_lista_correos((string) ($_POST['destinatarios'] ?? ''));
        $rem = trim((string) ($_POST['remitente'] ?? ''));
        if (!$dest) $err = 'Indica al menos un correo de destino válido.';
        elseif (!filter_var($rem, FILTER_VALIDATE_EMAIL)) $err = 'El remitente debe ser un correo válido.';
        else {
            $cfg['destinatarios'] = $dest;
            $cfg['cc'] = mv_lista_correos((string) ($_POST['cc'] ?? ''));
            $cfg['remitente'] = strtolower($rem);
            $cfg['remitente_nombre'] = mv_texto($_POST['remitente_nombre'] ?? '', 60) ?: 'Web Mi Vaso';
            $cfg['copia_cliente'] = !empty($_POST['copia_cliente']);
            $cfg['guardar_historial'] = !empty($_POST['guardar_historial']);
            $cfg['smtp']['activo'] = !empty($_POST['smtp_activo']);
            $cfg['smtp']['host'] = preg_replace('/[^a-z0-9.-]/i', '', (string) ($_POST['smtp_host'] ?? ''));
            $cfg['smtp']['puerto'] = max(1, min(65535, (int) ($_POST['smtp_puerto'] ?? 465)));
            $cfg['smtp']['seguridad'] = in_array($_POST['smtp_seguridad'] ?? '', ['ssl', 'tls', 'ninguna'], true) ? $_POST['smtp_seguridad'] : 'ssl';
            $cfg['smtp']['usuario'] = mv_texto($_POST['smtp_usuario'] ?? '', 160);
            if ((string) ($_POST['smtp_clave'] ?? '') !== '') $cfg['smtp']['clave'] = (string) $_POST['smtp_clave'];
            if ($cfg['smtp']['activo'] && $cfg['smtp']['host'] === '') $err = 'Para usar SMTP indica el servidor.';
            elseif (mv_guardar_config($cfg)) ir('p=ajustes&ok=guardado');
            else $err = 'No se pudo guardar. Revisa los permisos de la carpeta "privado".';
        }
    }
    elseif ($logueado && $acc === 'probar') {
        $html = '<div style="font-family:Helvetica,Arial,sans-serif"><h2 style="color:#4B873E">Correo de prueba</h2><p>Si lees esto, las cotizaciones de mivaso.cl llegarán a esta casilla.</p><p style="color:#7A8276;font-size:12px">Enviado el ' . date('d-m-Y H:i') . '</p></div>';
        [$cab, $cuerpo] = mv_mime($cfg, '', '', "Correo de prueba de mivaso.cl.\nSi lees esto, las cotizaciones llegarán a esta casilla.", $html);
        $e = mv_enviar($cfg, $cfg['destinatarios'], $cfg['cc'], 'Prueba de correo — mivaso.cl', $cab, $cuerpo);
        if ($e === '') ir('p=ajustes&ok=prueba'); else $err = $e;
        $_GET['p'] = 'ajustes';
    }
    elseif ($logueado && $acc === 'clave') {
        if (!password_verify((string) ($_POST['actual'] ?? ''), $cfg['admin_hash'])) $err = 'La clave actual no es correcta.';
        elseif (strlen((string) ($_POST['nueva'] ?? '')) < 10) $err = 'La nueva clave debe tener al menos 10 caracteres.';
        elseif ($_POST['nueva'] !== ($_POST['nueva2'] ?? '')) $err = 'Las claves nuevas no coinciden.';
        else { $cfg['admin_hash'] = password_hash((string) $_POST['nueva'], PASSWORD_DEFAULT); mv_guardar_config($cfg); ir('p=ajustes&ok=clave'); }
        $_GET['p'] = 'ajustes';
    }
    elseif ($logueado && $acc === 'google') {
        $g = $cfg['google'];
        $ga = strtoupper(trim((string) ($_POST['ga4'] ?? '')));
        $ads = array_values(array_filter(array_map(fn($x) => strtoupper(trim($x)), preg_split('/[\s,;]+/', (string) ($_POST['ads'] ?? '')))));
        $gtm = strtoupper(trim((string) ($_POST['gtm'] ?? '')));
        $conv = fn($k) => trim((string) ($_POST[$k] ?? ''));
        if ($ga !== '' && !preg_match('/^G-[A-Z0-9]{4,15}$/', $ga)) $err = 'El ID de Google Analytics debe tener la forma G-XXXXXXX.';
        elseif (array_filter($ads, fn($x) => !preg_match('/^AW-\d{6,14}$/', $x))) $err = 'Los ID de Google Ads deben tener la forma AW-123456789.';
        elseif ($gtm !== '' && !preg_match('/^GTM-[A-Z0-9]{4,12}$/', $gtm)) $err = 'El ID de Tag Manager debe tener la forma GTM-XXXXXX.';
        else {
            foreach (['conv_cotizacion', 'conv_contacto', 'conv_whatsapp'] as $k) {
                $v = $conv($k);
                if ($v !== '' && !preg_match('#^AW-\d{6,14}/[A-Za-z0-9_-]{4,40}$#', $v)) { $err = 'Las etiquetas de conversión deben tener la forma AW-123456789/AbC-dEfGh.'; break; }
                $g[$k] = $v;
            }
            if ($err === '') {
                $g['ga4'] = $ga; $g['ads'] = $ads; $g['gtm'] = $gtm;
                $g['verificacion'] = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['verificacion'] ?? ''));
                $cfg['google'] = $g;
                if (mv_guardar_config($cfg)) { $e2 = mv_publicar_todo(mv_contexto(null, $cfg)); ir('p=google&ok=' . ($e2 ? 'google_err' : 'google')); }
                $err = 'No se pudo guardar. Revisa los permisos de la carpeta "privado".';
            }
        }
        $_GET['p'] = 'google';
    }
    elseif ($logueado && $acc === 'eliminar') {
        $d = dir_cotizacion((string) ($_POST['id'] ?? ''));
        if ($d) { borrar_dir($d); ir('ok=eliminada'); }
    }
}

$okTxt = ['instalado' => 'Panel listo. Revisa en «Ajustes» a qué correo llegan las cotizaciones y envía un correo de prueba.',
          'guardado' => 'Ajustes guardados.', 'prueba' => 'Correo de prueba enviado. Revisa la bandeja de entrada (y spam).',
          'clave' => 'Clave actualizada.', 'eliminada' => 'Cotización eliminada.',
          'google' => 'Etiquetas de Google guardadas y aplicadas en todas las páginas.',
          'google_err' => 'Etiquetas guardadas, pero algunas páginas no se pudieron actualizar. Revisa los permisos de escritura.'][$_GET['ok'] ?? ''] ?? '';

// ---------------------------------------------------------------- editor de productos (pantalla completa)
if ($logueado && ($_GET['p'] ?? '') === 'producto') { require __DIR__ . '/editor-pagina.php'; exit; }
if ($logueado && ($_GET['p'] ?? '') === 'pagina') { require __DIR__ . '/constructor-pagina.php'; exit; }

// ---------------------------------------------------------------- vista
$pag = (string) ($_GET['p'] ?? '');
?><!doctype html>
<html lang="es-CL"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Panel Mi Vaso</title>
<link rel="stylesheet" href="panel.css"><meta name="csrf" content="<?= mv_h($_SESSION['csrf']) ?>"><script src="panel.js" defer></script></head><body>
<?php if (!$logueado): ?>
  <div class="caja-login caja">
  <?php if ($cfg['admin_hash'] === ''): codigo_instalacion($CODIGO); ?>
    <h1>Instalar panel</h1>
    <p class="ayuda">Primera vez: abre con el administrador de archivos de tu hosting el archivo <strong>privado/codigo-instalacion.php</strong> y copia el código que aparece en la línea «CODIGO».</p>
    <?php if ($err): ?><div class="mal"><?= mv_h($err) ?></div><?php endif; ?>
    <form method="post"><?= campo_csrf() ?><input type="hidden" name="accion" value="instalar">
      <label for="codigo">Código de instalación</label><input id="codigo" name="codigo" required autocomplete="off" placeholder="XXXX-XXXX-XXXX">
      <label for="correo">Correo donde recibir las cotizaciones</label><input id="correo" name="correo" type="email" value="<?= mv_h($cfg['destinatarios'][0] ?? '') ?>">
      <label for="clave">Crea una clave <small>(mínimo 10 caracteres)</small></label><input id="clave" name="clave" type="password" required minlength="10" autocomplete="new-password">
      <label for="clave2">Repite la clave</label><input id="clave2" name="clave2" type="password" required minlength="10" autocomplete="new-password">
      <p><button class="btn">Crear panel</button></p>
    </form>
  <?php else: ?>
    <h1>Panel Mi Vaso</h1>
    <?php if ($err): ?><div class="mal"><?= mv_h($err) ?></div><?php endif; ?>
    <form method="post"><?= campo_csrf() ?><input type="hidden" name="accion" value="entrar">
      <label for="clave">Clave</label><input id="clave" name="clave" type="password" required autocomplete="current-password" autofocus>
      <p><button class="btn">Entrar</button></p>
    </form>
  <?php endif; ?>
  </div>
<?php else: ?>
<header class="top"><div class="in">
  <span class="marca">MI VASO · Panel</span>
  <nav><a href="./"<?= $pag === '' || $pag === 'ver' ? ' aria-current="page"' : '' ?>>Cotizaciones</a><a href="./?p=paginas"<?= $pag === 'paginas' ? ' aria-current="page"' : '' ?>>Páginas y menú</a><a href="./?p=productos"<?= $pag === 'productos' ? ' aria-current="page"' : '' ?>>Productos</a><a href="./?p=categorias"<?= $pag === 'categorias' ? ' aria-current="page"' : '' ?>>Categorías</a><a href="./?p=google"<?= $pag === 'google' ? ' aria-current="page"' : '' ?>>Google</a><a href="./?p=ajustes"<?= $pag === 'ajustes' ? ' aria-current="page"' : '' ?>>Correo y clave</a><a href="../" target="_blank" rel="noopener">Ver sitio</a>
  <form method="post"><?= campo_csrf() ?><input type="hidden" name="accion" value="salir"><button class="btn">Salir</button></form></nav>
</div></header>
<main class="in">
<?php if ($okTxt): ?><div class="ok"><?= mv_h($okTxt) ?></div><?php endif; ?>
<?php if ($err): ?><div class="mal"><?= mv_h($err) ?></div><?php endif; ?>

<?php if ($pag === 'productos' || $pag === 'categorias'): require __DIR__ . '/catalogo-pagina.php'; ?>

<?php elseif ($pag === 'paginas'): require __DIR__ . '/paginas-lista.php'; ?>

<?php elseif ($pag === 'google'): $g = $cfg['google']; ?>
  <h1>Google Analytics y Google Ads</h1>
  <form method="post" class="caja"><?= campo_csrf() ?><input type="hidden" name="accion" value="google">
    <p class="ayuda">Estas etiquetas se insertan en todas las páginas del sitio. Vienen cargadas con las que ya usaba el sitio en WordPress, así que las estadísticas y campañas continúan sin cortes.</p>
    <div class="grid2">
      <div><label for="ga4">Google Analytics 4 <small>(ID de medición)</small></label><input id="ga4" name="ga4" value="<?= mv_h($g['ga4']) ?>" placeholder="G-XXXXXXXXXX"></div>
      <div><label for="ads">Google Ads <small>(uno o más ID, separados por coma)</small></label><input id="ads" name="ads" value="<?= mv_h(implode(', ', $g['ads'])) ?>" placeholder="AW-123456789"></div>
    </div>
    <h2 style="margin-top:22px">Conversiones de Google Ads</h2>
    <p class="ayuda">En Google Ads → Objetivos → Conversiones, crea (o abre) cada conversión, elige «Usar Google tag» y copia el valor de <strong>send_to</strong>, que tiene la forma <code>AW-123456789/AbCdEfGh</code>. Si lo dejas vacío, igual se registra el evento en Analytics.</p>
    <label for="conv_cotizacion">Cotización enviada</label><input id="conv_cotizacion" name="conv_cotizacion" value="<?= mv_h($g['conv_cotizacion']) ?>" placeholder="AW-846443580/xxxxxxxx">
    <div class="grid2">
      <div><label for="conv_contacto">Formulario de contacto enviado</label><input id="conv_contacto" name="conv_contacto" value="<?= mv_h($g['conv_contacto']) ?>"></div>
      <div><label for="conv_whatsapp">Clic en WhatsApp</label><input id="conv_whatsapp" name="conv_whatsapp" value="<?= mv_h($g['conv_whatsapp']) ?>"></div>
    </div>
    <h2 style="margin-top:22px">Avanzado</h2>
    <div class="grid2">
      <div><label for="gtm">Google Tag Manager <small>(opcional)</small></label><input id="gtm" name="gtm" value="<?= mv_h($g['gtm']) ?>" placeholder="GTM-XXXXXX"></div>
      <div><label for="verificacion">Verificación de Search Console <small>(solo el código del content)</small></label><input id="verificacion" name="verificacion" value="<?= mv_h($g['verificacion']) ?>"></div>
    </div>
    <p style="margin-top:18px"><button class="btn">Guardar y aplicar</button></p>
  </form>
  <div class="caja">
    <h2>Eventos que el sitio envía a Analytics</h2>
    <table class="lista"><thead><tr><th>Evento</th><th>Cuándo</th></tr></thead><tbody>
      <tr><td><code>generate_lead</code></td><td>Se envía una cotización (con folio, productos y unidades) o el formulario de contacto. Márcalo como evento clave en Analytics.</td></tr>
      <tr><td><code>agregar_cotizacion</code></td><td>Alguien agrega un producto a su cotización (producto, color, cantidad, si subió logo).</td></tr>
      <tr><td><code>contacto_whatsapp</code>, <code>contacto_telefono</code>, <code>contacto_correo</code></td><td>Clic en WhatsApp, teléfono o correo.</td></tr>
    </tbody></table>
    <p class="ayuda" style="margin-bottom:0">Las visitas y el rendimiento se revisan en <a href="https://analytics.google.com/" target="_blank" rel="noopener">analytics.google.com</a> y <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Search Console</a>, con la misma cuenta que usaba Site Kit.</p>
  </div>

<?php elseif ($pag === 'ajustes'): ?>
  <h1>Correo y clave</h1>
  <form method="post" class="caja"><?= campo_csrf() ?><input type="hidden" name="accion" value="ajustes">
    <h2>¿A dónde llegan las cotizaciones y mensajes?</h2>
    <label for="destinatarios">Correos de destino <small>(separa varios con coma)</small></label>
    <input id="destinatarios" name="destinatarios" required value="<?= mv_h(implode(', ', $cfg['destinatarios'])) ?>">
    <label for="cc">Con copia a <small>(opcional)</small></label>
    <input id="cc" name="cc" value="<?= mv_h(implode(', ', $cfg['cc'])) ?>">
    <div class="grid2">
      <div><label for="remitente">Remitente <small>(casilla del dominio, ej. web@mivaso.cl)</small></label><input id="remitente" name="remitente" type="email" required value="<?= mv_h($cfg['remitente']) ?>"></div>
      <div><label for="remitente_nombre">Nombre del remitente</label><input id="remitente_nombre" name="remitente_nombre" value="<?= mv_h($cfg['remitente_nombre']) ?>"></div>
    </div>
    <label class="check"><input type="checkbox" name="copia_cliente" value="1"<?= $cfg['copia_cliente'] ? ' checked' : '' ?>>Enviar al cliente una copia del resumen de su cotización</label>
    <label class="check"><input type="checkbox" name="guardar_historial" value="1"<?= $cfg['guardar_historial'] ? ' checked' : '' ?>>Guardar cada cotización en este panel (recomendado: si un correo falla, no se pierde)</label>

    <h2 style="margin-top:26px">Envío por SMTP <small>(opcional, mejora la entrega y evita el spam)</small></h2>
    <p class="ayuda">Si los correos no llegan o caen en spam, usa los datos SMTP de tu casilla (los entrega tu hosting o tu proveedor de correo).</p>
    <label class="check"><input type="checkbox" name="smtp_activo" value="1"<?= $cfg['smtp']['activo'] ? ' checked' : '' ?>>Usar SMTP en vez de la función mail() del servidor</label>
    <div class="grid2">
      <div><label for="smtp_host">Servidor</label><input id="smtp_host" name="smtp_host" value="<?= mv_h($cfg['smtp']['host']) ?>" placeholder="mail.mivaso.cl"></div>
      <div class="grid2" style="gap:0 12px"><div><label for="smtp_puerto">Puerto</label><input id="smtp_puerto" name="smtp_puerto" type="number" value="<?= (int) $cfg['smtp']['puerto'] ?>"></div>
        <div><label for="smtp_seguridad">Seguridad</label><select id="smtp_seguridad" name="smtp_seguridad"><?php foreach (['ssl' => 'SSL (465)', 'tls' => 'STARTTLS (587)', 'ninguna' => 'Ninguna'] as $k => $v): ?><option value="<?= $k ?>"<?= $cfg['smtp']['seguridad'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div></div>
      <div><label for="smtp_usuario">Usuario</label><input id="smtp_usuario" name="smtp_usuario" value="<?= mv_h($cfg['smtp']['usuario']) ?>" autocomplete="off"></div>
      <div><label for="smtp_clave">Clave <small><?= $cfg['smtp']['clave'] !== '' ? '(guardada; déjala vacía para no cambiarla)' : '' ?></small></label><input id="smtp_clave" name="smtp_clave" type="password" autocomplete="new-password"></div>
    </div>
    <p class="acciones" style="margin-top:20px"><button class="btn">Guardar ajustes</button></p>
  </form>
  <form method="post" class="caja"><?= campo_csrf() ?><input type="hidden" name="accion" value="probar">
    <h2>Probar el envío</h2><p class="ayuda">Envía un correo de prueba a: <strong><?= mv_h(implode(', ', array_merge($cfg['destinatarios'], $cfg['cc']))) ?></strong> (guarda los cambios antes).</p>
    <button class="btn borde">Enviar correo de prueba</button>
  </form>
  <form method="post" class="caja"><?= campo_csrf() ?><input type="hidden" name="accion" value="clave">
    <h2>Cambiar clave del panel</h2>
    <div class="grid2"><div><label for="actual">Clave actual</label><input id="actual" name="actual" type="password" required autocomplete="current-password"></div><div></div>
    <div><label for="nueva">Nueva clave</label><input id="nueva" name="nueva" type="password" minlength="10" required autocomplete="new-password"></div>
    <div><label for="nueva2">Repite la nueva clave</label><input id="nueva2" name="nueva2" type="password" minlength="10" required autocomplete="new-password"></div></div>
    <p><button class="btn borde">Cambiar clave</button></p>
  </form>
  <p class="ayuda">Límite de subida del servidor: <?= mv_h(ini_get('post_max_size')) ?> por envío · <?= mv_h(ini_get('upload_max_filesize')) ?> por archivo.</p>

<?php elseif ($pag === 'ver' && ($d = dir_cotizacion((string) ($_GET['id'] ?? '')))): $c = json_decode((string) file_get_contents("$d/datos.json"), true); $id = basename($d); $s = $c['solicitante']; ?>
  <p><a href="./">← Volver a cotizaciones</a></p>
  <h1>Cotización <?= mv_h($c['folio']) ?></h1>
  <div class="caja grid2">
    <div><h2>Solicitante</h2><dl class="dl"><dt>Nombre</dt><dd><?= mv_h($s['nombre']) ?></dd><?php if ($s['empresa']): ?><dt>Empresa</dt><dd><?= mv_h($s['empresa']) ?></dd><?php endif; ?>
      <dt>Correo</dt><dd><a href="mailto:<?= mv_h($s['correo']) ?>?subject=<?= rawurlencode('Cotización ' . $c['folio']) ?>"><?= mv_h($s['correo']) ?></a></dd><dt>Teléfono</dt><dd><?= mv_h($s['telefono']) ?></dd><dt>RUT</dt><dd><?= mv_h($s['rut']) ?></dd></dl></div>
    <div><h2>Pedido</h2><dl class="dl"><dt>Recibida</dt><dd><?= mv_h(date('d-m-Y H:i', strtotime($c['recibido']))) ?></dd><dt>Fecha requerida</dt><dd><?= mv_h(date('d-m-Y', strtotime($c['fecha_requerida']))) ?></dd>
      <dt>Entrega</dt><dd><?= mv_h($c['entrega'] . ($c['entrega'] === 'Despacho' ? ' · ' . $c['comuna'] : '')) ?></dd><dt>Total</dt><dd class="num"><?= number_format(array_sum(array_column($c['lineas'], 'cantidad')), 0, ',', '.') ?> unidades</dd></dl></div>
  </div>
  <div class="caja"><h2>Productos</h2>
  <?php foreach ($c['lineas'] as $n => $l): ?>
    <div class="prodv">
      <div><?php if ($l['vista'] !== null): ?><a href="?p=archivo&amp;id=<?= mv_h($id) ?>&amp;f=vista-<?= (int) $l['vista'] ?>.jpg" target="_blank"><img src="?p=archivo&amp;id=<?= mv_h($id) ?>&amp;f=vista-<?= (int) $l['vista'] ?>.jpg" alt="Vista previa"></a><?php else: ?><span class="ayuda">Sin vista previa</span><?php endif; ?></div>
      <div><h2><?= ($n + 1) . '. ' . mv_h($l['producto']) ?></h2><dl class="dl">
        <?php if ($l['variante']): ?><dt>Modelo</dt><dd><?= mv_h($l['variante']) ?></dd><?php endif; ?>
        <dt>Color</dt><dd><?= mv_h($l['color']) ?> <?= $l['color_stock'] ? '(en stock)' : '' ?></dd>
        <dt>Cantidad</dt><dd class="num"><?= number_format((int) $l['cantidad'], 0, ',', '.') ?> u.</dd>
        <dt>Impresión</dt><dd><?= mv_h($l['tintas'] . ($l['tinta_color'] ? ' · tinta ' . $l['tinta_color'] : '')) ?></dd>
        <dt>Logo</dt><dd><?php if ($l['logo'] !== null): ?><a href="?p=archivo&amp;id=<?= mv_h($id) ?>&amp;descargar=1&amp;f=<?= rawurlencode('logo-' . (int) $l['logo'] . '-' . $l['logo_nombre']) ?>">Descargar <?= mv_h($l['logo_nombre']) ?></a><?php else: ?>Sin logo<?php endif; ?></dd>
      </dl></div>
    </div>
  <?php endforeach; ?>
  </div>
  <div class="caja"><h2>Comentarios</h2><p style="white-space:pre-wrap;margin:0"><?= mv_h($c['mensaje'] ?: 'Sin comentarios.') ?></p></div>
  <form method="post" onsubmit="return confirm('¿Eliminar esta cotización y sus archivos? No se puede deshacer.')"><?= campo_csrf() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= mv_h($id) ?>"><button class="btn peligro">Eliminar cotización</button></form>

<?php else: $lista = cotizaciones(); ?>
  <h1>Cotizaciones recibidas</h1>
  <p class="ayuda">Llegan a: <strong><?= mv_h(implode(', ', $cfg['destinatarios'])) ?></strong> · <a href="./?p=ajustes">cambiar</a><?= empty($cfg['guardar_historial']) ? ' · El historial está desactivado en Ajustes.' : '' ?></p>
  <div class="caja scroll">
  <?php if (!$lista): ?><p class="ayuda" style="margin:0">Aún no hay cotizaciones guardadas. Aparecerán aquí cuando alguien envíe una desde el sitio.</p>
  <?php else: ?>
    <table class="lista"><thead><tr><th>Folio</th><th>Recibida</th><th>Cliente</th><th>Productos</th><th class="num">Unidades</th><th>Para el</th></tr></thead><tbody>
    <?php foreach ($lista as $c): ?>
      <tr><td><a href="?p=ver&amp;id=<?= mv_h($c['_id']) ?>"><strong><?= mv_h($c['folio']) ?></strong></a></td>
        <td><?= mv_h(date('d-m-Y H:i', strtotime($c['recibido']))) ?></td>
        <td><?= mv_h($c['solicitante']['nombre']) ?><?= $c['solicitante']['empresa'] ? '<br><small>' . mv_h($c['solicitante']['empresa']) . '</small>' : '' ?></td>
        <td><?= mv_h(implode(', ', array_unique(array_column($c['lineas'], 'producto')))) ?></td>
        <td class="num"><?= number_format(array_sum(array_column($c['lineas'], 'cantidad')), 0, ',', '.') ?></td>
        <td><?= mv_h(date('d-m-Y', strtotime($c['fecha_requerida']))) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
  </div>
<?php endif; ?>
</main>
<?php endif; ?>
</body></html>
