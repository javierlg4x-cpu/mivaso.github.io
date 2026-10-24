<?php
/**
 * Mi Vaso — reglas del constructor de productos: validación de datos y subida de archivos.
 * Todo lo que llega desde el editor pasa por aquí antes de guardarse.
 */
declare(strict_types=1);
require_once __DIR__ . '/sitio.php';

const MV_MEDIOS_DIR = __DIR__ . '/../medios';
const MV_MEDIOS_URL = '/medios';
const MV_MEDIOS_IDX = __DIR__ . '/datos/medios.json';
const MV_MAX_SUBIDA = 8 * 1024 * 1024;

const MV_TIPOS_BLOQUE = ['specs', 'diseno', 'adjuntos', 'usos', 'texto', 'imagen', 'colores', 'video', 'faq'];
const MV_FORMAS = ['vaso', 'copa', 'portavaso', 'foto'];

function t($v, int $max): string { return mv_texto($v, $max); }
function tl($v, int $max): string { return mv_texto($v, $max, true); }
function n($v, float $min, float $max, float $def): float { $x = is_numeric($v) ? (float) $v : $def; return max($min, min($max, $x)); }

/** URL permitida para imágenes y archivos: del propio sitio o https. */
function url_ok($u): string {
    $u = trim((string) $u);
    if ($u === '') return '';
    if (preg_match('#^/medios/[A-Za-z0-9/_.-]+$#', $u)) return $u;
    if (preg_match('#^https://[A-Za-z0-9.-]+(/[^\s"\'<>]*)?$#', $u)) return $u;
    return '';
}
function img_ok($i): ?array {
    if (!is_array($i) || url_ok($i['src'] ?? '') === '') return null;
    $src = url_ok($i['src']);
    return ['src' => $src, 'medio' => url_ok($i['medio'] ?? '') ?: $src, 'mini' => url_ok($i['mini'] ?? '') ?: $src, 'alt' => t($i['alt'] ?? '', 160)];
}

/** Limpia y valida un producto que viene del editor. Devuelve [producto, errores]. */
function mv_normalizar_producto(array $in, array $datos, string $slugOriginal = ''): array {
    $err = [];
    $nombre = t($in['nombre'] ?? '', 120);
    if ($nombre === '') $err[] = 'El producto necesita un nombre.';
    $slug = t($in['slug'] ?? '', 80) ?: mv_slugify($nombre);
    if (!mv_slug_valido($slug)) $err[] = 'La dirección (slug) solo puede tener minúsculas, números y guiones, y no puede ser una palabra reservada.';
    foreach ($datos['productos'] as $p) if ($p['slug'] === $slug && $slug !== $slugOriginal) $err[] = 'Ya existe otro producto con la dirección «' . $slug . '».';
    foreach ($datos['categorias'] as $c) if ($c['slug'] === $slug) $err[] = 'La dirección «' . $slug . '» la usa una categoría.';
    foreach (mv_paginas()['paginas'] as $pg) if ($pg['slug'] === $slug) $err[] = 'La dirección «' . $slug . '» la usa la página «' . $pg['titulo'] . '».';
    $cat = t($in['categoria'] ?? '', 40);
    if (!mv_categoria($datos, $cat)) $err[] = 'Elige una categoría.';

    $imgs = [];
    foreach (array_slice((array) ($in['imagenes'] ?? []), 0, 40) as $i) if ($x = img_ok($i)) $imgs[] = $x;

    // Cotizador
    $ci = (array) ($in['cotizador'] ?? []);
    $fi = (array) ($ci['forma'] ?? []);
    $tipo = in_array($fi['tipo'] ?? '', MV_FORMAS, true) ? $fi['tipo'] : 'vaso';
    $forma = ['tipo' => $tipo, 'referencial' => !empty($fi['referencial'])];
    if ($tipo === 'vaso') {
        $forma += ['fondo' => n($fi['fondo'] ?? 0, 10, 400, 53), 'boca' => n($fi['boca'] ?? 0, 10, 400, 74), 'alto' => n($fi['alto'] ?? 0, 10, 500, 138)];
        if (in_array($fi['tapa'] ?? '', ['bombilla', 'cafe'], true)) $forma['tapa'] = $fi['tapa'];
    } elseif ($tipo === 'copa') {
        $forma += ['estilo' => in_array($fi['estilo'] ?? '', ['flauta', 'tulipa', 'balon'], true) ? $fi['estilo'] : 'tulipa',
                   'pie' => n($fi['pie'] ?? 0, 10, 300, 75), 'boca' => n($fi['boca'] ?? 0, 10, 400, 85), 'alto' => n($fi['alto'] ?? 0, 30, 500, 200)];
    } elseif ($tipo === 'portavaso') {
        $forma += ['diametro' => n($fi['diametro'] ?? 0, 10, 300, 81), 'alto' => n($fi['alto'] ?? 0, 5, 200, 32)];
    } else {
        $a = (array) ($fi['area'] ?? []);
        $ax = n($a['x'] ?? 0.3, 0, 0.95, 0.3); $ay = n($a['y'] ?? 0.35, 0, 0.95, 0.35);
        $forma += ['area' => ['x' => round($ax, 4), 'y' => round($ay, 4), 'w' => round(n($a['w'] ?? 0.4, 0.03, 1 - $ax, 0.4), 4), 'h' => round(n($a['h'] ?? 0.3, 0.03, 1 - $ay, 0.3), 4)],
                   'curva' => !empty($fi['curva']), 'anchoMm' => n($fi['anchoMm'] ?? 0, 0, 2000, 0), 'medidas' => t($fi['medidas'] ?? '', 80),
                   'imagen' => url_ok($fi['imagen'] ?? '')];
    }
    $colores = []; $ids = [];
    foreach (array_slice((array) ($ci['colores'] ?? []), 0, 40) as $c) {
        $nom = t($c['nombre'] ?? '', 60);
        if ($nom === '') continue;
        $idIn = (string) ($c['id'] ?? '');
        $id = (preg_match('/^color-\d+-/', $idIn) ? mv_slugify($nom) : (mv_slugify($idIn) ?: mv_slugify($nom))) ?: 'color';
        while (in_array($id, $ids, true)) $id .= '-2';
        $ids[] = $id;
        $hex = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($c['hex'] ?? '')) ? strtoupper($c['hex']) : '#DCE9EE';
        $colores[] = ['id' => $id, 'nombre' => $nom, 'hex' => $hex, 'alfa' => round(n($c['alfa'] ?? 0.8, 0.1, 1, 0.8), 2), 'stock' => !empty($c['stock']), 'foto' => img_ok($c['foto'] ?? null)];
    }
    $tintas = array_values(array_intersect(['1', '2', '3', 'full'], array_map('strval', (array) ($ci['tintas'] ?? []))));
    $variantes = [];
    foreach (array_slice((array) ($ci['variantes'] ?? []), 0, 12) as $v) if (($v = t($v, 80)) !== '') $variantes[] = $v;
    $cot = ['activo' => !empty($ci['activo']), 'forma' => $forma, 'colores' => $colores, 'tintas' => $tintas ?: ['1'], 'variantes' => $variantes];
    if ($cot['activo'] && !$colores) $err[] = 'Para activar el cotizador agrega al menos un color.';
    if ($cot['activo'] && $tipo === 'foto' && !$imgs && !array_filter(array_column($colores, 'foto'))) $err[] = 'La vista previa por foto necesita al menos una imagen del producto o de un color.';

    // Bloques
    $bloques = []; $bids = [];
    foreach (array_slice((array) ($in['bloques'] ?? []), 0, 20) as $b) {
        $tipoB = $b['tipo'] ?? '';
        if (!in_array($tipoB, MV_TIPOS_BLOQUE, true)) continue;
        $id = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($b['id'] ?? ''))) ?: $tipoB;
        while (in_array($id, $bids, true)) $id .= '-2';
        $bids[] = $id;
        $o = ['id' => $id, 'tipo' => $tipoB, 'titulo' => t($b['titulo'] ?? '', 60)];
        switch ($tipoB) {
            case 'specs': $o['filas'] = []; foreach (array_slice((array) ($b['filas'] ?? []), 0, 30) as $r) if (t($r[0] ?? '', 60) !== '') $o['filas'][] = [t($r[0], 60), t($r[1] ?? '', 160)]; break;
            case 'diseno': $o['color'] = ($b['color'] ?? '') === 'cmyk' ? 'cmyk' : 'pantone'; $o['plantilla'] = !empty($b['plantilla']); break;
            case 'adjuntos': $o['items'] = []; foreach (array_slice((array) ($b['items'] ?? []), 0, 20) as $a) if (url_ok($a['url'] ?? '') !== '') $o['items'][] = ['titulo' => t($a['titulo'] ?? '', 120) ?: 'Archivo', 'url' => url_ok($a['url'])]; break;
            case 'usos': $o['texto'] = t($b['texto'] ?? '', 300); $o['iconos'] = []; foreach (array_slice((array) ($b['iconos'] ?? []), 0, 20) as $i) if (url_ok($i['src'] ?? '') !== '') $o['iconos'][] = ['src' => url_ok($i['src']), 'alt' => t($i['alt'] ?? '', 80)]; break;
            case 'texto': $o['contenido'] = tl($b['contenido'] ?? '', 8000); break;
            case 'imagen': $o['img'] = img_ok($b['img'] ?? null); $o['pie'] = t($b['pie'] ?? '', 200); break;
            case 'video': $o['url'] = url_ok($b['url'] ?? ''); break;
            case 'faq': $o['items'] = []; foreach (array_slice((array) ($b['items'] ?? []), 0, 30) as $q) if (t($q['pregunta'] ?? '', 200) !== '') $o['items'][] = ['pregunta' => t($q['pregunta'], 200), 'respuesta' => tl($q['respuesta'] ?? '', 2000)]; break;
        }
        if ($o['titulo'] === '') $o['titulo'] = ['specs' => 'Detalles del producto', 'diseno' => 'Diseño', 'adjuntos' => 'Adjuntos', 'usos' => 'Usos del producto',
            'texto' => 'Información', 'imagen' => 'Imagen', 'colores' => 'Colores', 'video' => 'Video', 'faq' => 'Preguntas frecuentes'][$tipoB];
        $bloques[] = $o;
    }

    $mini = url_ok($in['miniatura'] ?? '') ?: ($imgs[0]['mini'] ?? '');
    $prod = [
        'slug' => $slug, 'nombre' => $nombre, 'corto' => t($in['corto'] ?? '', 80) ?: $nombre,
        'categoria' => $cat, 'estado' => ($in['estado'] ?? '') === 'publicado' ? 'publicado' : 'borrador',
        'orden' => (int) ($in['orden'] ?? 999), 'titulo_ficha' => t($in['titulo_ficha'] ?? '', 120),
        'descripcion' => tl($in['descripcion'] ?? '', 6000), 'imagenes' => $imgs, 'miniatura' => $mini,
        'destacado' => ['activo' => !empty($in['destacado']['activo']), 'img' => url_ok($in['destacado']['img'] ?? '')],
        'cotizador' => $cot, 'bloques' => $bloques,
        'seo' => ['titulo' => t($in['seo']['titulo'] ?? '', 70), 'descripcion' => t($in['seo']['descripcion'] ?? '', 300)],
        'actualizado' => date('c'),
    ];
    if ($prod['estado'] === 'publicado' && $mini === '') $err[] = 'Sube al menos una imagen antes de publicar.';
    return [$prod, $err];
}

/** Valida una categoría. */
function mv_normalizar_categoria(array $in, array $datos, string $idOriginal = ''): array {
    $err = [];
    $titulo = t($in['titulo'] ?? '', 80);
    if ($titulo === '') $err[] = 'La categoría necesita un nombre.';
    $slug = t($in['slug'] ?? '', 80) ?: mv_slugify($titulo);
    $id = $idOriginal ?: (mv_slugify(t($in['menu'] ?? '', 30)) ?: mv_slugify($titulo));
    if (!mv_slug_valido($slug)) $err[] = 'La dirección de la categoría no es válida.';
    foreach ($datos['categorias'] as $c) {
        if ($c['id'] !== $idOriginal && $c['slug'] === $slug) $err[] = 'Otra categoría ya usa la dirección «' . $slug . '».';
        if (!$idOriginal && $c['id'] === $id) $id .= '-2';
    }
    foreach ($datos['productos'] as $p) if ($p['slug'] === $slug) $err[] = 'Un producto ya usa la dirección «' . $slug . '».';
    foreach (mv_paginas()['paginas'] as $pg) if ($pg['slug'] === $slug) $err[] = 'La página «' . $pg['titulo'] . '» ya usa la dirección «' . $slug . '».';
    return [[
        'id' => $id, 'slug' => $slug, 'titulo' => $titulo, 'menu' => mb_strtoupper(t($in['menu'] ?? '', 30) ?: $titulo),
        'h' => mb_strtoupper($titulo), 'singular' => t($in['singular'] ?? '', 40) ?: $titulo,
        'img' => url_ok($in['img'] ?? ''), 'seo' => t($in['seo'] ?? '', 300), 'orden' => (int) ($in['orden'] ?? 99),
    ], $err];
}

// ---------------------------------------------------------------- subida de archivos
/** Guarda un archivo subido en /medios. Devuelve [objeto, error]. */
function mv_subir(array $f): array {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) return [null, 'No se recibió el archivo.'];
    if ($f['size'] > MV_MAX_SUBIDA) return [null, 'El archivo pesa más de 8 MB.'];
    $mime = function_exists('finfo_open') ? (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']) : '';
    $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    if (!isset($tipos[$mime])) return [null, 'Formato no permitido. Usa JPG, PNG, WEBP o PDF.'];
    $ext = $tipos[$mime];
    $sub = date('Y/m');
    $dir = MV_MEDIOS_DIR . '/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return [null, 'No se pudo crear la carpeta medios. Revisa los permisos.'];
    $base = substr(mv_slugify(pathinfo((string) $f['name'], PATHINFO_FILENAME)) ?: 'archivo', 0, 50) . '-' . bin2hex(random_bytes(3));
    $ruta = "$dir/$base.$ext";
    $url = MV_MEDIOS_URL . "/$sub/$base.$ext";
    if ($ext === 'pdf') {
        if (!move_uploaded_file($f['tmp_name'], $ruta)) return [null, 'No se pudo guardar el archivo.'];
        $obj = ['tipo' => 'pdf', 'src' => $url, 'nombre' => (string) $f['name']];
    } else {
        $obj = mv_procesar_imagen($f['tmp_name'], $ruta, "$dir/$base", MV_MEDIOS_URL . "/$sub/$base", $ext);
        if (!$obj) return [null, 'No se pudo procesar la imagen.'];
        $obj['nombre'] = (string) $f['name'];
    }
    $obj['fecha'] = date('c');
    $idx = json_decode((string) @file_get_contents(MV_MEDIOS_IDX), true) ?: [];
    array_unshift($idx, $obj);
    @file_put_contents(MV_MEDIOS_IDX, json_encode(array_slice($idx, 0, 2000), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return [$obj, ''];
}

/** Reescala (máx. 2000 px), quita metadatos y crea versiones de 1024 px y miniatura cuadrada de 300 px. */
function mv_procesar_imagen(string $tmp, string $ruta, string $baseRuta, string $baseUrl, string $ext): ?array {
    $info = @getimagesize($tmp);
    if (!$info || $info[0] < 1 || $info[0] * $info[1] > 40000000) return null;
    if (!function_exists('imagecreatefromstring')) {
        if (!move_uploaded_file($tmp, $ruta)) return null;
        $u = "$baseUrl.$ext";
        return ['tipo' => 'imagen', 'src' => $u, 'medio' => $u, 'mini' => $u, 'alt' => '', 'ancho' => $info[0], 'alto' => $info[1]];
    }
    $im = @imagecreatefromstring((string) file_get_contents($tmp));
    if (!$im) return null;
    $guardar = function ($img, string $r) use ($ext) {
        if ($ext === 'png') { imagesavealpha($img, true); return imagepng($img, $r, 7); }
        if ($ext === 'webp') return imagewebp($img, $r, 85);
        return imagejpeg($img, $r, 86);
    };
    $escalar = function ($src, int $max) {
        $w = imagesx($src); $h = imagesy($src); $f = min(1, $max / max($w, $h));
        if ($f >= 1) return $src;
        $nw = (int) round($w * $f); $nh = (int) round($h * $f);
        $d = imagecreatetruecolor($nw, $nh); imagealphablending($d, false); imagesavealpha($d, true);
        imagecopyresampled($d, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $d;
    };
    $grande = $escalar($im, 2000);
    if (!$guardar($grande, $ruta)) return null;
    $medio = $escalar($grande, 1024);
    $guardar($medio, "$baseRuta-1024.$ext");
    // Miniatura cuadrada (como las de WordPress): se encaja completa sobre fondo blanco/transparente
    $w = imagesx($grande); $h = imagesy($grande); $f = 300 / max($w, $h);
    $mini = imagecreatetruecolor(300, 300); imagealphablending($mini, false); imagesavealpha($mini, true);
    imagefill($mini, 0, 0, $ext === 'jpg' ? imagecolorallocate($mini, 255, 255, 255) : imagecolorallocatealpha($mini, 255, 255, 255, 127));
    imagealphablending($mini, true);
    imagecopyresampled($mini, $grande, (int) ((300 - $w * $f) / 2), (int) ((300 - $h * $f) / 2), 0, 0, (int) round($w * $f), (int) round($h * $f), $w, $h);
    $guardar($mini, "$baseRuta-300.$ext");
    return ['tipo' => 'imagen', 'src' => "$baseUrl.$ext", 'medio' => "$baseUrl-1024.$ext", 'mini' => "$baseUrl-300.$ext", 'alt' => '', 'ancho' => imagesx($grande), 'alto' => imagesy($grande)];
}
