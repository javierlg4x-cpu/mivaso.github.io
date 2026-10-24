<?php
/**
 * Mi Vaso — funciones compartidas por enviar.php y el panel /admin/.
 * Esta carpeta no es accesible desde la web (ver privado/.htaccess).
 */
declare(strict_types=1);
date_default_timezone_set('America/Santiago');

const MV_PRIVADO = __DIR__;
const MV_CONFIG  = __DIR__ . '/config.php';
const MV_COTIZACIONES = __DIR__ . '/cotizaciones';

function mv_config_por_defecto(): array {
    return [
        'destinatarios'    => ['contacto@mivaso.cl'],
        'cc'               => [],
        'remitente'        => 'web@mivaso.cl',
        'remitente_nombre' => 'Web Mi Vaso',
        'copia_cliente'    => false,
        'guardar_historial'=> true,
        'smtp' => ['activo' => false, 'host' => '', 'puerto' => 465, 'seguridad' => 'ssl', 'usuario' => '', 'clave' => ''],
        'admin_hash'       => '',
        // Etiquetas que ya usaba el sitio en WordPress (Site Kit y Google Ads)
        'google' => ['ga4' => 'G-200QXRBRFL', 'ads' => ['AW-846443580', 'AW-7587623391'], 'gtm' => '', 'verificacion' => '',
                     'conv_cotizacion' => '', 'conv_contacto' => '', 'conv_whatsapp' => ''],
    ];
}

function mv_config(): array {
    $c = is_file(MV_CONFIG) ? (include MV_CONFIG) : [];
    if (!is_array($c)) $c = [];
    $d = mv_config_por_defecto();
    $c = array_replace($d, $c);
    $c['smtp'] = array_replace($d['smtp'], is_array($c['smtp']) ? $c['smtp'] : []);
    $c['google'] = array_replace($d['google'], is_array($c['google']) ? $c['google'] : []);
    return $c;
}

function mv_guardar_config(array $c): bool {
    $txt = "<?php\n// Configuración del sitio Mi Vaso. Se edita desde /admin/.\nreturn " . var_export($c, true) . ";\n";
    $tmp = MV_CONFIG . '.tmp';
    if (@file_put_contents($tmp, $txt, LOCK_EX) === false) return false;
    @chmod($tmp, 0600);
    if (function_exists('opcache_invalidate')) @opcache_invalidate(MV_CONFIG, true);
    return @rename($tmp, MV_CONFIG);
}

function mv_texto($v, int $max, bool $multilinea = false): string {
    $v = is_string($v) ? $v : '';
    $v = str_replace("\0", '', $v);
    $v = $multilinea ? preg_replace("/\r\n?/", "\n", $v) : preg_replace('/[\r\n\t]+/', ' ', $v);
    $v = trim((string) $v);
    return function_exists('mb_substr') ? mb_substr($v, 0, $max, 'UTF-8') : substr($v, 0, $max);
}

function mv_lista_correos(string $txt): array {
    $out = [];
    foreach (preg_split('/[\s,;]+/', $txt) as $c) {
        $c = trim($c);
        if ($c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL)) $out[] = strtolower($c);
    }
    return array_values(array_unique($out));
}

function mv_h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function mv_cod(string $s): string { return '=?UTF-8?B?' . base64_encode($s) . '?='; }

/**
 * Arma un correo MIME.
 * $inline: [['cid'=>..., 'nombre'=>..., 'tipo'=>..., 'datos'=>...]]  imágenes dentro del HTML
 * $adjuntos: igual, como archivos adjuntos
 * Devuelve [cabeceras (string sin To/Subject), cuerpo]
 */
function mv_mime(array $cfg, string $responderA, string $responderNombre, string $texto, string $html, array $inline = [], array $adjuntos = []): array {
    $b = function () { return 'mv_' . bin2hex(random_bytes(10)); };
    $cab = [
        'From: ' . mv_cod($cfg['remitente_nombre']) . ' <' . $cfg['remitente'] . '>',
        'MIME-Version: 1.0',
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $cfg['remitente'])[1] ?? 'mivaso.cl') . '>',
        'X-Mailer: MiVaso-Web',
    ];
    if ($responderA !== '') $cab[] = 'Reply-To: ' . ($responderNombre !== '' ? mv_cod($responderNombre) . ' ' : '') . '<' . $responderA . '>';

    $alt = $b();
    $parteAlt = "Content-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n"
        . "--$alt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($texto))
        . "--$alt\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
        . "--$alt--\r\n";

    $parte = $parteAlt;
    if ($inline) {
        $rel = $b();
        $p = "Content-Type: multipart/related; boundary=\"$rel\"\r\n\r\n--$rel\r\n" . $parteAlt;
        foreach ($inline as $f) {
            $p .= "--$rel\r\nContent-Type: {$f['tipo']}; name=\"{$f['nombre']}\"\r\nContent-Transfer-Encoding: base64\r\n"
                . "Content-ID: <{$f['cid']}>\r\nContent-Disposition: inline; filename=\"{$f['nombre']}\"\r\n\r\n" . chunk_split(base64_encode($f['datos']));
        }
        $parte = $p . "--$rel--\r\n";
    }
    if ($adjuntos) {
        $mix = $b();
        $p = "Content-Type: multipart/mixed; boundary=\"$mix\"\r\n\r\n--$mix\r\n" . $parte;
        foreach ($adjuntos as $f) {
            $p .= "--$mix\r\nContent-Type: {$f['tipo']}; name=\"{$f['nombre']}\"\r\nContent-Transfer-Encoding: base64\r\n"
                . "Content-Disposition: attachment; filename=\"{$f['nombre']}\"\r\n\r\n" . chunk_split(base64_encode($f['datos']));
        }
        $parte = $p . "--$mix--\r\n";
    }
    // Separar la primera línea Content-Type (va como cabecera) del resto (cuerpo)
    [$ct, $cuerpo] = explode("\r\n\r\n", $parte, 2);
    $cab[] = $ct;
    return [implode("\r\n", $cab), $cuerpo];
}

/** Envía un correo a varios destinatarios. Devuelve '' si salió bien o el mensaje de error. */
function mv_enviar(array $cfg, array $para, array $cc, string $asunto, string $cabeceras, string $cuerpo): string {
    $para = array_values(array_filter($para, fn($x) => filter_var($x, FILTER_VALIDATE_EMAIL)));
    $cc   = array_values(array_filter($cc, fn($x) => filter_var($x, FILTER_VALIDATE_EMAIL)));
    if (!$para) return 'No hay un correo de destino configurado.';
    if (!empty($cfg['smtp']['activo'])) return mv_smtp($cfg, $para, $cc, $asunto, $cabeceras, $cuerpo);

    $cab = $cabeceras . ($cc ? "\r\nCc: " . implode(', ', $cc) : '');
    $ok = @mail(implode(', ', $para), mv_cod($asunto), $cuerpo, $cab, '-f' . $cfg['remitente']);
    if (!$ok) $ok = @mail(implode(', ', $para), mv_cod($asunto), $cuerpo, $cab);
    return $ok ? '' : 'El servidor no pudo enviar el correo con mail(). Revisa el remitente o configura SMTP en el panel.';
}

/** Cliente SMTP mínimo (SSL implícito o STARTTLS, AUTH LOGIN). */
function mv_smtp(array $cfg, array $para, array $cc, string $asunto, string $cabeceras, string $cuerpo): string {
    $s = $cfg['smtp'];
    $host = (string) $s['host']; $puerto = (int) $s['puerto']; $seg = (string) $s['seguridad'];
    if ($host === '') return 'Falta el servidor SMTP.';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $dir = ($seg === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $puerto;
    $fp = @stream_socket_client($dir, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return "No se pudo conectar a $host:$puerto ($errstr).";
    stream_set_timeout($fp, 30);
    $leer = function () use ($fp) {
        $r = '';
        while (($l = fgets($fp, 1024)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; }
        return $r;
    };
    $cmd = function (string $c, array $esperado) use ($fp, $leer) {
        if ($c !== '') fwrite($fp, $c . "\r\n");
        $r = $leer();
        if (!in_array((int) substr($r, 0, 3), $esperado, true)) throw new RuntimeException(trim($r) ?: 'sin respuesta');
        return $r;
    };
    try {
        $cmd('', [220]);
        $yo = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['SERVER_NAME'] ?? 'mivaso.cl') ?: 'mivaso.cl';
        $cmd("EHLO $yo", [250]);
        if ($seg === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('No se pudo iniciar TLS.');
            $cmd("EHLO $yo", [250]);
        }
        if ((string) $s['usuario'] !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode((string) $s['usuario']), [334]);
            $cmd(base64_encode((string) $s['clave']), [235]);
        }
        $cmd('MAIL FROM:<' . $cfg['remitente'] . '>', [250]);
        foreach (array_merge($para, $cc) as $r) $cmd('RCPT TO:<' . $r . '>', [250, 251]);
        $cmd('DATA', [354]);
        $msg = 'To: ' . implode(', ', $para) . "\r\n" . ($cc ? 'Cc: ' . implode(', ', $cc) . "\r\n" : '')
             . 'Subject: ' . mv_cod($asunto) . "\r\n" . $cabeceras . "\r\n\r\n" . $cuerpo;
        $msg = preg_replace('/(?<!\r)\n/', "\r\n", $msg);
        $msg = preg_replace('/^\./m', '..', $msg);            // "dot-stuffing"
        fwrite($fp, $msg . "\r\n.\r\n");
        $cmd('', [250]);
        $cmd('QUIT', [221]);
        fclose($fp);
        return '';
    } catch (Throwable $e) {
        @fclose($fp);
        return 'SMTP: ' . $e->getMessage();
    }
}

function mv_ip(): string { return (string) ($_SERVER['REMOTE_ADDR'] ?? '0'); }

/** Límite simple de intentos por IP. Devuelve true si todavía está permitido. */
function mv_limite(string $tipo, int $max, int $ventana, bool $registrar = true): bool {
    $f = MV_PRIVADO . '/limites/' . $tipo . '_' . hash('sha256', mv_ip() . MV_PRIVADO) . '.json';
    if (!is_dir(dirname($f))) @mkdir(dirname($f), 0700, true);
    $t = is_file($f) ? (json_decode((string) @file_get_contents($f), true) ?: []) : [];
    $t = array_values(array_filter($t, fn($x) => is_int($x) && $x > time() - $ventana));
    if (count($t) >= $max) return false;
    if ($registrar) { $t[] = time(); @file_put_contents($f, json_encode($t), LOCK_EX); }
    return true;
}
