<?php
/** Mi Vaso — API del constructor de productos (solo para el panel con sesión iniciada). */
declare(strict_types=1);
require __DIR__ . '/sesion.php';

header('Cache-Control: no-store');
function json_out(array $d, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Prepara el HTML de una vista previa para mostrarlo dentro del panel (iframe srcdoc):
 *  dirección base absoluta y hoja de estilos incrustada, para que se vea igual que el sitio en cualquier servidor. */
function vista_html(string $html, string $ruta, string $extra): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = preg_replace('/[^A-Za-z0-9.:\[\]-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $raiz = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
    $base = ($https ? 'https' : 'http') . '://' . $host . $raiz . '/' . $ruta;
    $css = (string) @file_get_contents(dirname(__DIR__) . '/assets/css/estilos.css');
    $html = preg_replace_callback('#<link rel="stylesheet" href="[^"]*estilos\.css">#', fn() => '<style>' . str_replace('</', '<\/', $css) . '</style>', $html, 1);
    return str_replace('<head>', '<head><base href="' . mv_h($base) . '">' . $extra, $html);
}
if (!$logueado) json_out(['ok' => false, 'error' => 'Tu sesión expiró. Vuelve a entrar al panel.'], 401);
$accion = (string) ($_GET['accion'] ?? '');
if ($accion === 'medios') {
    $idx = json_decode((string) @file_get_contents(MV_MEDIOS_IDX), true) ?: [];
    json_out(['ok' => true, 'medios' => array_slice($idx, 0, 400)]);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_ok()) json_out(['ok' => false, 'error' => 'Solicitud no válida. Recarga la página.'], 400);

if ($accion === 'subir') {
    if (empty($_FILES['archivo'])) json_out(['ok' => false, 'error' => (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 ? 'El archivo supera el límite del servidor.' : 'No se recibió ningún archivo.'], 400);
    [$obj, $e] = mv_subir($_FILES['archivo']);
    json_out($obj ? ['ok' => true, 'archivo' => $obj] : ['ok' => false, 'error' => $e], $obj ? 200 : 400);
}

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) json_out(['ok' => false, 'error' => 'Datos no válidos.'], 400);
$datos = mv_datos();
$rev = (int) ($datos['revision'] ?? 0);
$revisionOk = fn() => !isset($in['revision']) || (int) $in['revision'] === $rev;
$publicar = function (array $datos) use ($cfg) {
    $datos['revision'] = (int) ($datos['revision'] ?? 0) + 1;
    if (!mv_guardar_datos($datos)) return ['No se pudo guardar el catálogo. Revisa los permisos de la carpeta privado/datos.'];
    return mv_publicar_todo(mv_contexto($datos, $cfg));
};

switch ($accion) {
    case 'vista': {
        // Vista previa del borrador: se arma la ficha sin guardar nada
        [$p] = mv_normalizar_producto((array) ($in['producto'] ?? []), $datos, (string) ($in['slugOriginal'] ?? ''));
        if (!mv_categoria($datos, $p['categoria'])) $p['categoria'] = $datos['categorias'][0]['id'] ?? '';
        $p['slug'] = mv_slug_valido($p['slug']) ? $p['slug'] : 'vista-previa';
        $cfgSin = $cfg; $cfgSin['google'] = [];      // la vista previa no envía datos a Google
        $html = ficha_html(mv_contexto($datos, $cfgSin), $p, true);
        $aviso = '<script>window.MIVASO_VISTA_PREVIA=true;document.addEventListener("click",function(e){var a=e.target.closest("a[href]");if(a&&!a.closest("#cotiza")&&a.getAttribute("href").charAt(0)!=="#"){e.preventDefault();}},true);</script>';
        $html = vista_html($html, $p['slug'] . '/', $aviso);
        header('Content-Type: text/html; charset=utf-8');
        echo $html; exit;
    }
    case 'guardar': {
        if (!$revisionOk()) json_out(['ok' => false, 'error' => 'El catálogo cambió en otra pestaña. Recarga la página para no perder cambios ajenos.'], 409);
        $orig = (string) ($in['slugOriginal'] ?? '');
        [$p, $err] = mv_normalizar_producto((array) ($in['producto'] ?? []), $datos, $orig);
        if ($err) json_out(['ok' => false, 'error' => implode(' ', array_unique($err))], 422);
        $i = null;
        foreach ($datos['productos'] as $k => $x) if ($x['slug'] === $orig && $orig !== '') $i = $k;
        if ($i === null) {
            $max = 0; foreach ($datos['productos'] as $x) if ($x['categoria'] === $p['categoria']) $max = max($max, (int) $x['orden']);
            $p['orden'] = $max + 1;
            $datos['productos'][] = $p;
        } else {
            $p['orden'] = $datos['productos'][$i]['orden'];
            $datos['productos'][$i] = $p;
        }
        $errPub = $publicar($datos);
        $d2 = mv_datos();
        json_out(['ok' => !$errPub, 'error' => implode(' ', $errPub), 'producto' => $p, 'revision' => (int) ($d2['revision'] ?? 0),
                  'url' => $p['estado'] === 'publicado' ? '/' . $p['slug'] . '/' : null]);
    }
    case 'eliminar': {
        $slug = (string) ($in['slug'] ?? '');
        $antes = count($datos['productos']);
        $datos['productos'] = array_values(array_filter($datos['productos'], fn($x) => $x['slug'] !== $slug));
        if (count($datos['productos']) === $antes) json_out(['ok' => false, 'error' => 'No existe ese producto.'], 404);
        $e = $publicar($datos);
        json_out(['ok' => !$e, 'error' => implode(' ', $e)]);
    }
    case 'orden': {
        $orden = array_flip(array_map('strval', (array) ($in['slugs'] ?? [])));
        foreach ($datos['productos'] as &$x) if (isset($orden[$x['slug']])) $x['orden'] = $orden[$x['slug']] + 1;
        unset($x);
        $e = $publicar($datos);
        json_out(['ok' => !$e, 'error' => implode(' ', $e), 'revision' => $rev + 1]);
    }
    case 'categoria': {
        $idOrig = (string) ($in['idOriginal'] ?? '');
        [$c, $err] = mv_normalizar_categoria((array) ($in['categoria'] ?? []), $datos, $idOrig);
        if ($err) json_out(['ok' => false, 'error' => implode(' ', array_unique($err))], 422);
        $i = null;
        foreach ($datos['categorias'] as $k => $x) if ($x['id'] === $idOrig && $idOrig !== '') $i = $k;
        if ($i === null) { $c['orden'] = count($datos['categorias']) + 1; $datos['categorias'][] = $c; }
        else { $c['orden'] = $datos['categorias'][$i]['orden']; $datos['categorias'][$i] = $c; }
        $e = $publicar($datos);
        json_out(['ok' => !$e, 'error' => implode(' ', $e), 'categoria' => $c]);
    }
    case 'categoria_eliminar': {
        $id = (string) ($in['id'] ?? '');
        foreach ($datos['productos'] as $x) if ($x['categoria'] === $id) json_out(['ok' => false, 'error' => 'La categoría tiene productos. Muévelos o elimínalos primero.'], 409);
        $datos['categorias'] = array_values(array_filter($datos['categorias'], fn($x) => $x['id'] !== $id));
        $e = $publicar($datos);
        json_out(['ok' => !$e, 'error' => implode(' ', $e)]);
    }
    case 'categorias_orden': {
        $orden = array_flip(array_map('strval', (array) ($in['ids'] ?? [])));
        foreach ($datos['categorias'] as &$x) if (isset($orden[$x['id']])) $x['orden'] = $orden[$x['id']] + 1;
        unset($x);
        $e = $publicar($datos);
        json_out(['ok' => !$e, 'error' => implode(' ', $e)]);
    }
    // ---------------------------------------------------------------- páginas y menú
    case 'pagina_vista': {
        $PG = mv_paginas();
        [$pg] = mv_normalizar_pagina((array) ($in['pagina'] ?? []), $PG, $datos, (string) ($in['idOriginal'] ?? ''));
        if (!mv_slug_valido($pg['slug']) && $pg['slug'] !== '' && empty($pg['sistema'])) $pg['slug'] = 'vista-previa';
        if ($pg['slug'] === '' && empty($pg['sistema'])) $pg['slug'] = 'vista-previa';
        $cfgSin = $cfg; $cfgSin['google'] = [];
        $html = p_dinamica(mv_contexto($datos, $cfgSin, $PG), $pg, true);
        $js = '<script>window.MIVASO_VISTA_PREVIA=true;'
            . 'document.addEventListener("click",function(e){var a=e.target.closest("a[href]");if(a&&a.getAttribute("href").charAt(0)!=="#"&&!a.hasAttribute("data-ampliar"))e.preventDefault();'
            . 'var s=e.target.closest("[data-sec]");if(s&&!e.target.closest("button,input,textarea,select,summary,.bnr__flecha"))parent.postMessage({mvSeccion:s.getAttribute("data-sec")},"*");},true);'
            . 'document.addEventListener("submit",function(e){e.preventDefault();},true);'
            . 'window.addEventListener("message",function(e){if(e.data&&e.data.mvIr){var s=document.querySelector("[data-sec=\""+e.data.mvIr+"\"]");if(s){s.scrollIntoView({behavior:"smooth",block:"start"});s.classList.add("mv-marca");setTimeout(function(){s.classList.remove("mv-marca")},1400);}}});'
            . '</script><style>[data-sec]{cursor:pointer}[data-sec]:hover{outline:2px dashed rgba(75,135,62,.7);outline-offset:-2px}.mv-marca{outline:3px solid #4B873E!important;outline-offset:-3px}.anima{opacity:1!important;transform:none!important}</style>';
        $html = vista_html($html, $pg['slug'] !== '' ? $pg['slug'] . '/' : '', $js);
        header('Content-Type: text/html; charset=utf-8');
        echo $html; exit;
    }
    case 'pagina_guardar': {
        $PG = mv_paginas();
        if (isset($in['revision']) && (int) $in['revision'] !== (int) ($PG['revision'] ?? 0)) json_out(['ok' => false, 'error' => 'Las páginas cambiaron en otra pestaña. Recarga para no perder cambios ajenos.'], 409);
        $idOrig = (string) ($in['idOriginal'] ?? '');
        [$pg, $err] = mv_normalizar_pagina((array) ($in['pagina'] ?? []), $PG, $datos, $idOrig);
        if ($err) json_out(['ok' => false, 'error' => implode(' ', array_unique($err))], 422);
        $i = null;
        foreach ($PG['paginas'] as $k => $x) if ($x['id'] === $idOrig && $idOrig !== '') $i = $k;
        if ($i === null) $PG['paginas'][] = $pg; else $PG['paginas'][$i] = $pg;
        // Menú: agregar o quitar automáticamente
        $enMenu = false; foreach ($PG['menu'] as $m) if (($m['ref'] ?? '') === $pg['id']) $enMenu = true;
        if (!empty($in['enMenu']) && !$enMenu) $PG['menu'][] = ['id' => 'm-' . bin2hex(random_bytes(3)), 'tipo' => 'pagina', 'ref' => $pg['id'], 'etiqueta' => mb_strtoupper(mv_texto($in['etiquetaMenu'] ?? '', 40) ?: $pg['titulo']), 'nivel' => 0, 'visible' => true];
        if (isset($in['enMenu']) && empty($in['enMenu']) && $enMenu && empty($pg['sistema'])) $PG['menu'] = array_values(array_filter($PG['menu'], fn($m) => ($m['ref'] ?? '') !== $pg['id']));
        $PG['revision'] = (int) ($PG['revision'] ?? 0) + 1;
        if (!mv_guardar_paginas($PG)) json_out(['ok' => false, 'error' => 'No se pudo guardar. Revisa los permisos de la carpeta privado/datos.'], 500);
        $e = mv_publicar_todo(mv_contexto($datos, $cfg, $PG));
        $enMenu = false; foreach ($PG['menu'] as $m) if (($m['ref'] ?? '') === $pg['id']) $enMenu = true;
        json_out(['ok' => !$e, 'error' => implode(' ', $e), 'pagina' => $pg, 'revision' => $PG['revision'], 'enMenu' => $enMenu,
                  'url' => $pg['estado'] === 'publicado' ? '/' . ($pg['slug'] !== '' ? $pg['slug'] . '/' : '') : null]);
    }
    case 'pagina_eliminar': {
        $PG = mv_paginas(); $id = (string) ($in['id'] ?? '');
        $pg = mv_pagina_por_id($PG, $id);
        if (!$pg) json_out(['ok' => false, 'error' => 'No existe esa página.'], 404);
        if (!empty($pg['sistema'])) json_out(['ok' => false, 'error' => 'Esta página es parte del sitio y no se puede eliminar (sí puedes editarla o sacarla del menú).'], 409);
        $PG['paginas'] = array_values(array_filter($PG['paginas'], fn($x) => $x['id'] !== $id));
        $PG['menu'] = array_values(array_filter($PG['menu'], fn($m) => ($m['ref'] ?? '') !== $id));
        $PG['revision'] = (int) ($PG['revision'] ?? 0) + 1;
        if (!mv_guardar_paginas($PG)) json_out(['ok' => false, 'error' => 'No se pudo guardar.'], 500);
        $e = mv_publicar_todo(mv_contexto($datos, $cfg, $PG));
        json_out(['ok' => !$e, 'error' => implode(' ', $e)]);
    }
    case 'menu_guardar': {
        $PG = mv_paginas();
        $PG['menu'] = mv_normalizar_menu((array) ($in['menu'] ?? []), $PG);
        $PG['revision'] = (int) ($PG['revision'] ?? 0) + 1;
        if (!mv_guardar_paginas($PG)) json_out(['ok' => false, 'error' => 'No se pudo guardar.'], 500);
        $e = mv_publicar_todo(mv_contexto($datos, $cfg, $PG));
        json_out(['ok' => !$e, 'error' => implode(' ', $e), 'menu' => $PG['menu']]);
    }
    case 'regenerar': {
        $e = mv_publicar_todo(mv_contexto($datos, $cfg));
        json_out(['ok' => !$e, 'error' => implode(' ', $e)]);
    }
}
json_out(['ok' => false, 'error' => 'Acción desconocida.'], 400);
