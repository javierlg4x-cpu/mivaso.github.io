<?php
/**
 * Mi Vaso — generador del sitio.
 * Convierte privado/datos/catalogo.json + la configuración en páginas HTML estáticas.
 * Lo usa el panel /admin/ al publicar. Todas las páginas comparten esta plantilla.
 */
declare(strict_types=1);
require_once __DIR__ . '/nucleo.php';
require_once __DIR__ . '/paginas.php';

const MV_DATOS   = __DIR__ . '/datos/catalogo.json';
const MV_RAIZ    = __DIR__ . '/..';
const MV_DOM     = 'https://mivaso.cl';
const MV_U       = 'https://mivaso.cl/wp-content/uploads/';
const MV_WA      = 'https://wa.me/56950435981';
const MV_GUIA    = MV_U . '2025/12/guia-de-impresion-MiVaso.pdf';
// Carpetas que nunca pueden ser el "slug" de un producto o categoría
const MV_RESERVADOS = ['admin', 'privado', 'assets', 'wp-content', 'medios', 'cotizador', 'contacto', 'nosotros', 'productos',
                       'econimia-circular', 'wp-admin', 'wp-includes', 'index', 'enviar', '404'];

// ---------------------------------------------------------------- datos
function mv_datos(): array {
    $d = json_decode((string) @file_get_contents(MV_DATOS), true);
    if (!is_array($d)) $d = ['version' => 1, 'categorias' => [], 'productos' => []];
    usort($d['categorias'], fn($a, $b) => ($a['orden'] ?? 0) <=> ($b['orden'] ?? 0));
    usort($d['productos'], fn($a, $b) => ($a['orden'] ?? 0) <=> ($b['orden'] ?? 0));
    return $d;
}
function mv_guardar_datos(array $d): bool {
    if (!is_dir(dirname(MV_DATOS))) @mkdir(dirname(MV_DATOS), 0750, true);
    // Copia de respaldo de la versión anterior (se guardan las últimas 20)
    if (is_file(MV_DATOS)) {
        $bk = dirname(MV_DATOS) . '/respaldos';
        if (!is_dir($bk)) @mkdir($bk, 0750, true);
        @copy(MV_DATOS, $bk . '/catalogo-' . date('Ymd-His') . '.json');
        $todos = glob($bk . '/catalogo-*.json') ?: []; sort($todos);
        foreach (array_slice($todos, 0, max(0, count($todos) - 20)) as $v) @unlink($v);
    }
    $tmp = MV_DATOS . '.tmp';
    if (@file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
    return @rename($tmp, MV_DATOS);
}
function mv_categoria(array $d, string $id): ?array { foreach ($d['categorias'] as $c) if ($c['id'] === $id) return $c; return null; }
function mv_publicados(array $d, ?string $cat = null): array {
    return array_values(array_filter($d['productos'], fn($p) => ($p['estado'] ?? '') === 'publicado' && ($cat === null || $p['categoria'] === $cat)));
}

// ---------------------------------------------------------------- utilidades HTML
function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function mv_url_segura(string $u): string {
    $u = trim($u);
    return preg_match('#^(https?://|/|\.\./|mailto:|tel:|\#)#i', $u) ? $u : '#';
}
/** Texto con **negrita**, [enlaces](url) y párrafos separados por una línea en blanco. */
function md(string $t, bool $parrafos = true): string {
    $html = e($t);
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
    $html = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
        $u = mv_url_segura(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5));
        $ext = preg_match('#^https?://#', $u) && strpos($u, 'mivaso.cl') === false;
        return '<a href="' . e($u) . '"' . ($ext ? ' target="_blank" rel="noopener"' : '') . '>' . $m[1] . '</a>';
    }, $html);
    if (!$parrafos) return nl2br($html, false);
    $out = '';
    foreach (preg_split("/\n\s*\n/", trim($html)) as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $lineas = preg_split('/\n/', $p);
        // Listas: todas las líneas empiezan con "- " (viñetas) o "1. " (numeradas)
        if (count(array_filter($lineas, fn($l) => preg_match('/^\s*[-•*]\s+/', $l))) === count($lineas)) {
            $out .= '<ul>' . implode('', array_map(fn($l) => '<li>' . preg_replace('/^\s*[-•*]\s+/', '', $l) . '</li>', $lineas)) . '</ul>';
        } elseif (count(array_filter($lineas, fn($l) => preg_match('/^\s*\d+[.)]\s+/', $l))) === count($lineas)) {
            $out .= '<ol>' . implode('', array_map(fn($l) => '<li>' . preg_replace('/^\s*\d+[.)]\s+/', '', $l) . '</li>', $lineas)) . '</ol>';
        } elseif (preg_match('/^#{2,3}\s+(.+)$/s', $p, $m) && count($lineas) === 1) {
            $out .= '<h3>' . $m[1] . '</h3>';
        } else $out .= '<p>' . nl2br($p, false) . '</p>';
    }
    return $out;
}
function img(string $src, string $alt = '', ?int $w = null, ?int $h = null, string $cls = '', bool $lazy = true): string {
    $a = '<img src="' . e($src) . '" alt="' . e($alt) . '"';
    if ($w) $a .= ' width="' . $w . '" height="' . $h . '"';
    if ($cls !== '') $a .= ' class="' . $cls . '"';
    if ($lazy) $a .= ' loading="lazy" decoding="async"';
    return $a . '>';
}
function L(string $pre, string $s): string { return $s !== '' ? $pre . $s . '/' : ($pre !== '' ? $pre : './'); }

// ---------------------------------------------------------------- Google (Analytics, Ads, Search Console)
function mv_google_head(array $cfg): string {
    $g = $cfg['google'] ?? [];
    $out = '';
    if (!empty($g['verificacion'])) $out .= '<meta name="google-site-verification" content="' . e($g['verificacion']) . '">' . "\n";
    if (!empty($g['gtm'])) {
        $id = e($g['gtm']);
        $out .= "<script>if(/^(www\\.)?mivaso\\.cl$/.test(location.hostname))(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','$id');</script>\n";
    }
    $ids = array_values(array_filter(array_merge([$g['ga4'] ?? ''], $g['ads'] ?? [])));
    if ($ids) {
        $conf = '';
        foreach ($ids as $id) $conf .= "gtag('config'," . json_encode($id) . ");";
        $out .= "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}"
              . "if(/^(www\\.)?mivaso\\.cl$/.test(location.hostname)){var g=document.createElement('script');g.async=true;g.src='https://www.googletagmanager.com/gtag/js?id=" . e($ids[0]) . "';document.head.appendChild(g);}"
              . "gtag('consent','default',{ad_storage:'granted',ad_user_data:'granted',ad_personalization:'granted',analytics_storage:'granted'});"
              . "gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',region:['AT','BE','BG','CH','CY','CZ','DE','DK','EE','ES','FI','FR','GB','GR','HR','HU','IE','IS','IT','LI','LT','LU','LV','MT','NL','NO','PL','PT','RO','SE','SI','SK']});"
              . "gtag('js',new Date());$conf"
              . "window.MIVASO_CONVERSIONES=" . json_encode(['cotizacion' => $g['conv_cotizacion'] ?? '', 'contacto' => $g['conv_contacto'] ?? '', 'whatsapp' => $g['conv_whatsapp'] ?? ''], JSON_UNESCAPED_SLASHES) . ";</script>\n";
    }
    return $out;
}
function mv_google_body(array $cfg): string {
    $g = $cfg['google'] ?? [];
    return !empty($g['gtm']) ? '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . e($g['gtm']) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n" : '';
}

// ---------------------------------------------------------------- plantilla base
function pagina(array $S, string $slug, string $titulo, string $desc, string $cuerpo, string $og = '', ?string $activo = null, string $extraJs = '', ?array $jsonld = null, array $opc = []): string {
    $pre = $slug === '' ? '' : '../';
    $url = MV_DOM . '/' . ($slug !== '' ? $slug . '/' : '');
    $og = $og !== '' ? $og : MV_U . '2025/11/MOSAICO-30.png';
    if (strpos($og, '/') === 0) $og = MV_DOM . $og;
    $simple = ($opc['cabecera'] ?? 'normal') === 'simple';
    $pieSimple = ($opc['pie'] ?? 'normal') === 'simple';
    $robots = !empty($opc['noindex']) ? '<meta name="robots" content="noindex">' . "\n" : '';
    $chev = '<svg viewBox="0 0 12 12" width="12" height="12" aria-hidden="true"><path d="M2 4l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>';
    $menu = mv_menu_items($S, $pre);
    $enlace = function (array $m) use ($activo) {
        $cur = $m['slug'] !== null && $activo === $m['slug'] ? ' aria-current="page"' : '';
        return '<a href="' . e($m['href']) . '"' . $cur . ($m['nueva'] ? ' target="_blank" rel="noopener"' : '') . '>' . e($m['t']) . '</a>';
    };
    $li = '';
    foreach ($menu as $m) {
        if ($m['hijos']) {
            $subs = '';
            foreach ($m['hijos'] as $h) $subs .= '<li>' . $enlace($h) . '</li>';
            $li .= '<li class="has-sub">' . $enlace($m) . '<button class="sub-btn" type="button" aria-expanded="false" aria-label="Ver ' . e(mb_strtolower($m['t'])) . '">' . $chev . '</button><ul class="sub">' . $subs . '</ul></li>';
        } else $li .= '<li>' . $enlace($m) . '</li>';
    }
    $li .= '<li><a class="btn btn--nav" href="' . L($pre, 'cotizador') . '"' . ($activo === 'cotizador' ? ' aria-current="page"' : '') . '>COTIZA AQUÍ</a></li>';
    $pie = '';
    foreach ($menu as $m) $pie .= '<li><a href="' . e($m['href']) . '"' . ($m['nueva'] ? ' target="_blank" rel="noopener"' : '') . '>' . e(mv_etiqueta_pie($m['t'])) . '</a></li>';
    $pie .= '<li><a href="' . L($pre, 'cotizador') . '">Cotiza aquí</a></li>';
    $navHtml = $simple
        ? '<a class="btn btn--nav top__cta" href="' . L($pre, 'cotizador') . '">COTIZA AQUÍ</a>'
        : '<button class="menu-btn" type="button" aria-expanded="false" aria-controls="menu"><span class="menu-btn__bars" aria-hidden="true"></span><span class="menu-btn__txt">Menú</span></button>' . "\n    " . '<nav id="menu" class="nav" aria-label="Principal"><ul>' . $li . '</ul></nav>';
    $ods = '';
    foreach (['06', '07', '11', '12', '13', '15'] as $o) $ods .= img(MV_U . "2025/11/S-WEB-Goal-$o-300x300.png", 'Objetivo de Desarrollo Sostenible ' . (int) $o, 64, 64);
    $ld = $jsonld ? '<script type="application/ld+json">' . json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' : '';
    $google = mv_google_head($S['cfg']);
    $U = MV_U; $WA = MV_WA; $e = 'e'; $img = 'img'; $L = 'L';
    $footer = $pieSimple
        ? '<footer class="pie pie--simple"><div class="pie__base"><div class="wrap">© Copyright mivaso.cl · <a href="' . L($pre, '') . '">Ir al sitio</a> · <a href="mailto:contacto@mivaso.cl">contacto@mivaso.cl</a> · <a href="' . MV_WA . '" target="_blank" rel="noopener">+56 9 5043 5981</a></div></div></footer>'
        : <<<HTML
<footer class="pie">
  <div class="wrap pie__cols">
    <div class="pie__marca">
      {$img($U . '2025/11/Logo_Mivaso_blanco_fnd_trans-03-300x65.png', 'Mi Vaso', 200, 43)}
      <p>Llevamos más de 10 años impulsando el triple impacto —económico, social y ambiental— ayudando a reducir costos, promover el marketing sustentable y disminuir la generación de residuos.</p>
    </div>
    <nav class="pie__menu" aria-label="Pie de página"><h2 class="pie__h">Menú</h2><ul>$pie</ul></nav>
    <div class="pie__contacto">
      <h2 class="pie__h">Contacto</h2>
      <ul>
        <li><a href="https://www.facebook.com/mivasoecologico" target="_blank" rel="noopener">Facebook @mivasoecologico</a></li>
        <li><a href="https://www.instagram.com/mivasoecologico/" target="_blank" rel="noopener">Instagram @mivasoecologico</a></li>
        <li><a href="$WA" target="_blank" rel="noopener">+56 9 5043 5981</a> <span class="nota">(solo WhatsApp)</span></li>
        <li><a href="tel:+56225831902">+56 2 2583 1902</a> <span class="nota">(solo llamadas)</span></li>
        <li><a href="mailto:contacto@mivaso.cl">contacto@mivaso.cl</a></li>
        <li>Lunes a viernes de 8:00 a 17:00 horas</li>
        <li>Chile</li>
      </ul>
    </div>
  </div>
  <div class="wrap pie__sellos">
    <div class="pie__ods">$ods{$img($U . '2025/11/LOGO-OBJETIVOS-300x133.png', 'Objetivos de Desarrollo Sostenible', 120, 53)}</div>
    <div class="pie__apoyo">{$img($U . '2025/11/LOGO-SERCOTEC-300x139.png', 'Sercotec', 104, 48)}{$img($U . '2025/11/LOGO-CORFO-300x88.png', 'Corfo', 120, 35)}</div>
  </div>
  <div class="pie__base"><div class="wrap">© Copyright mivaso.cl · Todos los derechos reservados.</div></div>
</footer>
HTML;
    return <<<HTML
<!doctype html>
<html lang="es-CL">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$e($titulo)}</title>
<meta name="description" content="{$e($desc)}">
$robots<link rel="canonical" href="$url">
<meta property="og:type" content="website">
<meta property="og:locale" content="es_CL">
<meta property="og:site_name" content="MiVaso.cl">
<meta property="og:title" content="{$e($titulo)}">
<meta property="og:description" content="{$e($desc)}">
<meta property="og:url" content="$url">
<meta property="og:image" content="{$e($og)}">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#4B873E">
<link rel="icon" href="{$U}2025/11/Favicon-mivaso_Mesa-de-trabajo-1-150x150.png" sizes="32x32">
<link rel="apple-touch-icon" href="{$U}2025/11/Favicon-mivaso_Mesa-de-trabajo-1-300x300.png">
<link rel="preconnect" href="https://mivaso.cl">
<link rel="stylesheet" href="{$pre}assets/css/estilos.css">
<script>document.documentElement.classList.add('js')</script>
$google$ld
</head>
<body>
{$S['gbody']}<a class="saltar" href="#contenido">Ir al contenido</a>
<header class="top">
  <div class="wrap top__in">
    <a class="logo" href="{$L($pre, '')}"><img src="{$U}2025/11/Logo-miVaso-300x93.png" srcset="{$U}2025/11/Logo-miVaso-300x93.png 300w, {$U}2025/11/Logo-miVaso.png 1221w" sizes="180px" width="180" height="56" alt="Mi Vaso"></a>
    $navHtml
  </div>
</header>
<main id="contenido">
$cuerpo
</main>
$footer
<a class="wa" href="$WA" target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp al +56 9 5043 5981"><svg viewBox="0 0 32 32" width="30" height="30" aria-hidden="true"><path fill="currentColor" d="M16 3.5C9.1 3.5 3.5 8.9 3.5 15.6c0 2.3.7 4.5 1.9 6.4L4 28.5l6.8-1.8c1.6.8 3.4 1.2 5.2 1.2 6.9 0 12.5-5.4 12.5-12.1S22.9 3.5 16 3.5zm0 22.1c-1.6 0-3.2-.4-4.5-1.1l-.4-.2-4 1.1 1.1-3.8-.3-.4c-1.1-1.6-1.6-3.5-1.6-5.4 0-5.4 4.4-9.8 9.7-9.8s9.7 4.4 9.7 9.8-4.3 9.8-9.7 9.8zm5.3-7.2c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.8.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.4-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.4.1-.6l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1.1 2.8 1.2 3c.1.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.7-.7 1.9-1.4.2-.7.2-1.3.2-1.4-.1-.1-.3-.2-.6-.3z"/></svg></a>
<script src="{$pre}assets/js/sitio.js" defer></script>
<script src="{$pre}assets/js/cotiza.js" defer></script>
$extraJs
</body>
</html>

HTML;
}


function form_contacto(string $pre, string $idp, string $tituloNombre = 'Nombre'): string {
    return <<<HTML
<form class="form" id="$idp" action="{$pre}enviar.php" method="post" data-endpoint="{$pre}enviar.php" novalidate>
  <input type="hidden" name="tipo" value="contacto">
  <input type="hidden" name="ts" value="">
  <div class="hp" aria-hidden="true"><label for="$idp-web">No completar</label><input type="text" id="$idp-web" name="web" tabindex="-1" autocomplete="off"></div>
  <div class="form__grid">
    <div class="campo"><label for="$idp-nombre">$tituloNombre</label><input id="$idp-nombre" name="nombre" type="text" autocomplete="name" required maxlength="120"></div>
    <div class="campo"><label for="$idp-telefono">Teléfono</label><input id="$idp-telefono" name="telefono" type="tel" autocomplete="tel" maxlength="40" placeholder="+56 9 …"></div>
    <div class="campo campo--full"><label for="$idp-correo">Correo</label><input id="$idp-correo" name="correo" type="email" autocomplete="email" required maxlength="160"></div>
    <div class="campo campo--full"><label for="$idp-mensaje">Mensaje</label><textarea id="$idp-mensaje" name="mensaje" rows="4" required maxlength="4000"></textarea></div>
  </div>
  <button class="btn btn--claro" type="submit">Enviar</button>
  <p class="form__estado" role="status" aria-live="polite"></p>
</form>
HTML;
}

function tarjeta_prod(string $pre, array $p, ?string $thumb = null, string $h = 'h3'): string {
    $t = $thumb ?: $p['miniatura'];
    return '<a class="prod" href="' . L($pre, $p['slug']) . '"><span class="prod__img">' . img($t, $p['corto'], 300, 300) . '</span><' . $h . ' class="prod__nombre">' . e($p['corto']) . '</' . $h . '></a>';
}

// ---------------------------------------------------------------- páginas del catálogo (Inicio, Nosotros, etc. están en paginas.php)
function mv_marcas(): array {
    return [['austral', 'MARCAS_austral-1.png'], ['Dreams', 'MARCAS_dreams-1.png'], ['Falabella', 'MARCAS_falabella-1.png'], ['Ford', 'MARCAS_ford.png'],
            ['Guayacán', 'MARCAS_guayacan-1.png'], ['Kunstmann', 'MARCAS_kunstmann-1.png'], ['Mercado Libre', 'MARCAS_mercado-libre-1.png'], ['Mistral', 'MARCAS_mistral-1.png'],
            ['Universidad de Antofagasta', 'MARCAS_UA-1.png'], ['Universidad Austral de Chile', 'MARCAS_uach-1.png'], ['PUCV', 'MARCAS_ucv.png'], ['UTFSM', 'MARCAS_utfsm-1.png'],
            ['WOM', 'MARCAS_WOM.png'], ['Monticello', 'MARCAS_monticello-1.png'], ['Brujas', 'MARCAS_brujas-1.png'], ['Alto del Carmen', 'MARCAS_alto-del-carmen-1.png']];
}
function p_productos(array $S): string {
    $pre = '../'; $cats = '';
    foreach ($S['datos']['categorias'] as $c) {
        if (!mv_publicados($S['datos'], $c['id'])) continue;
        $cats .= '<a class="prod prod--cat" href="' . L($pre, $c['slug']) . '"><span class="prod__img">' . img($c['img'], $c['titulo'], 300, 300) . '</span><h2 class="prod__nombre">' . e($c['titulo']) . '</h2></a>';
    }
    $cuerpo = <<<HTML

<section class="seccion">
  <div class="wrap">
    <h1 class="h2">Productos</h1>
    <p class="lead">Vasos y copas reutilizables personalizados desde 500 unidades, y accesorios fabricados con material reciclado en nuestra propia planta.</p>
    <div class="grilla grilla--3">$cats</div>
  </div>
</section>
HTML;
    return pagina($S, 'productos', 'Productos - MiVaso.cl', 'Vasos reutilizables, copas de acrílico y accesorios de economía circular, personalizados con tu marca.', $cuerpo, MV_U . '2025/12/1000cc.jpg', 'productos');
}

function p_categoria(array $S, array $c): string {
    $pre = '../'; $ps = mv_publicados($S['datos'], $c['id']);
    $t = '';
    foreach ($ps as $p) $t .= tarjeta_prod($pre, $p, null, 'h2');
    $cols = count($ps) >= 4 ? 'grilla--4' : 'grilla--3';
    $vacio = $ps ? '' : '<p class="lead">Pronto agregaremos productos a esta categoría.</p>';
    $tit = e($c['titulo']);
    $cuerpo = <<<HTML

<section class="seccion">
  <div class="wrap">
    <nav class="migas" aria-label="Ruta"><a href="{$pre}">Inicio</a> / <a href="{$pre}productos/">Productos</a> / <span>$tit</span></nav>
    <h1 class="h2">$tit</h1>
    $vacio<div class="grilla $cols">$t</div>
  </div>
</section>
HTML;
    $og = $ps ? ($ps[0]['imagenes'][0]['src'] ?? '') : '';
    return pagina($S, $c['slug'], $c['titulo'] . ' - MiVaso.cl', (string) $c['seo'], $cuerpo, $og, $c['slug']);
}

// ---------------------------------------------------------------- ficha de producto
function widget(array $p, array $c): string {
    $art = ['vasos' => 'del vaso', 'copas' => 'de la copa'][$c['id']] ?? 'del producto';
    $var = !empty($p['cotizador']['variantes']) ? '<div class="cz-campo"><label class="cz-label" for="cz-variante">Modelo</label><select id="cz-variante"></select></div>' : '';
    $slug = e($p['slug']); $WA = MV_WA;
    return <<<HTML
<section class="cotiza" id="cotiza" data-slug="$slug" data-carro="../cotizador/" aria-labelledby="cotiza-h" hidden>
  <h2 class="cotiza__h" id="cotiza-h">Cotiza este producto</h2>
  <p class="cotiza__intro">Elige color y cantidad, sube tu logo y mira cómo queda antes de enviarnos la solicitud.</p>
  <fieldset class="cz-campo"><legend class="cz-label">1. Color $art</legend><div class="muestras" id="cz-colores"></div></fieldset>
  $var
  <div class="cz-campo">
    <label class="cz-label" for="cz-cantidad">2. Cantidad</label>
    <div class="cantidad"><button type="button" id="cz-menos" aria-label="Restar 100 unidades">−</button><input type="number" id="cz-cantidad" inputmode="numeric" step="100" min="100"><button type="button" id="cz-mas" aria-label="Sumar 100 unidades">+</button></div>
    <p class="cz-ayuda" id="cz-cant-nota" aria-live="polite"></p>
  </div>
  <fieldset class="cz-campo">
    <legend class="cz-label">3. Impresión</legend>
    <div class="pastillas" id="cz-tintas"></div>
    <p class="cz-ayuda" id="cz-tintas-nota">Impresión en serigrafía. Elige cuántos colores tiene tu logo.</p>
    <div class="cz-sub" id="cz-tinta-color" hidden><span class="cz-sublabel">Color de la tinta</span><div class="muestras muestras--chicas" id="cz-tinta-colores"></div></div>
  </fieldset>
  <div class="cz-campo">
    <span class="cz-label" id="cz-logo-label">4. Tu logo</span>
    <label class="subir" for="cz-logo"><strong>Sube tu logo</strong> o arrástralo aquí<small>PNG, JPG, SVG, PDF, AI o EPS · máximo 5 MB</small></label>
    <input type="file" id="cz-logo" class="visualmente-oculto" accept=".png,.jpg,.jpeg,.svg,.pdf,.ai,.eps" aria-labelledby="cz-logo-label">
    <button type="button" class="enlace-boton" id="cz-ultimo" hidden></button>
    <p class="cz-ayuda" id="cz-logo-estado" aria-live="polite">Opcional: si aún no lo tienes, puedes enviarlo después.</p>
    <div class="cz-ajustes" id="cz-ajustes" hidden>
      <label for="cz-escala">Tamaño del logo</label><input type="range" id="cz-escala" min="20" max="100" value="75">
      <label for="cz-posy">Altura del logo</label><input type="range" id="cz-posy" min="0" max="100" value="50">
      <label class="check"><input type="checkbox" id="cz-fondo" checked> Quitar fondo blanco</label>
    </div>
  </div>
  <div class="cz-previa-movil" id="cz-previa-movil"></div>
  <div class="cz-acciones"><button type="button" class="btn" id="cz-agregar">Agregar a mi cotización</button><a class="cz-ver" href="../cotizador/">Ver mi cotización</a></div>
  <p class="form__estado" id="cz-estado" role="status" aria-live="polite"></p>
</section>
<p class="cotiza-sin-js aviso">Para cotizar en línea necesitas JavaScript activado. También puedes escribirnos a <a href="mailto:contacto@mivaso.cl">contacto@mivaso.cl</a> o por <a href="$WA">WhatsApp</a>.</p>
HTML;
}

/** HTML interno de un bloque (pestaña) de la ficha. */
function bloque_html(array $b, array $p): string {
    $t = e($b['titulo'] ?? '');
    switch ($b['tipo'] ?? '') {
        case 'specs':
            $f = '';
            foreach ($b['filas'] ?? [] as $r) if (trim((string) ($r[0] ?? '')) !== '') $f .= '<tr><th scope="row">' . e($r[0]) . '</th><td>' . e($r[1] ?? '') . '</td></tr>';
            return "<h2 class=\"panel__h\">$t</h2><table class=\"specs\"><thead><tr><th scope=\"col\">Característica</th><th scope=\"col\">Detalle</th></tr></thead><tbody>$f</tbody></table>";
        case 'diseno':
            $color = ($b['color'] ?? 'pantone') === 'cmyk' ? 'Usa colores en modo CMYK.' : 'Incluye el código Pantone Solid Coated o la composición en CMYK.';
            $plant = !empty($b['plantilla']) ? 'Elige el producto que quieres personalizar y descarga su plantilla en la pestaña «Adjuntos».' : 'Revisa la ficha técnica en la pestaña «Adjuntos» para conocer el área de impresión.';
            return '<h2 class="panel__h">Personalización de tu producto</h2>
        <p>Personaliza tus vasos reutilizables o copas acrílicas de forma rápida y sencilla: descarga la plantilla del producto, crea tu diseño y envíanos el archivo para comenzar con tu proyecto.</p>
        <ol class="pasos">
          <li><strong>Descarga tu plantilla.</strong> ' . $plant . '</li>
          <li><strong>Realiza tu diseño.</strong> Usa la plantilla en el programa que prefieras: Illustrator, Photoshop, Canva o similar.
            <ul><li>' . $color . '</li><li>Deja textos y logos a buena resolución.</li><li>Mantén los elementos importantes dentro del área segura de la plantilla.</li></ul></li>
          <li><strong>Envíanos tu diseño</strong> subiéndolo en el <a href="#cotiza">cotizador de esta página</a> o a <a href="mailto:contacto@mivaso.cl">contacto@mivaso.cl</a>.</li>
        </ol>
        <p class="aviso"><strong>¿Prefieres que nosotros hagamos el diseño?</strong> Si no tienes un diseño listo, nuestro equipo puede hacerlo por ti desde $15.000. Cuéntanos tu idea y crearemos un diseño único para tu evento o marca.</p>';
        case 'adjuntos':
            $l = '';
            foreach ($b['items'] ?? [] as $a) if (!empty($a['url'])) $l .= '<li><span>' . e($a['titulo'] ?? 'Archivo') . '</span><a href="' . e(mv_url_segura($a['url'])) . '" target="_blank" rel="noopener">Descargar PDF</a></li>';
            return "<h2 class=\"panel__h\">$t</h2><ul class=\"adjuntos\">$l</ul>";
        case 'usos':
            $ic = '';
            foreach ($b['iconos'] ?? [] as $i) $ic .= '<li>' . img($i['src'], $i['alt'] ?? '', 61, 70) . '</li>';
            return "<h2 class=\"panel__h\">$t</h2><p>" . e($b['texto'] ?? '') . "</p><ul class=\"usos\">$ic</ul>";
        case 'texto':
            return "<h2 class=\"panel__h\">$t</h2>" . md((string) ($b['contenido'] ?? ''));
        case 'imagen':
            if (empty($b['img']['src'])) return "<h2 class=\"panel__h\">$t</h2>";
            return "<h2 class=\"panel__h\">$t</h2><figure class=\"bloque-img\">" . img($b['img']['medio'] ?? $b['img']['src'], $b['img']['alt'] ?? '', null, null) . (!empty($b['pie']) ? '<figcaption>' . e($b['pie']) . '</figcaption>' : '') . '</figure>';
        case 'colores':
            $g = '';
            foreach ($p['cotizador']['colores'] ?? [] as $c) {
                $foto = $c['foto']['mini'] ?? '';
                $g .= '<li>' . ($foto ? img($foto, $c['nombre'], 300, 300) : '<span class="color-muestra" style="--c:' . e($c['hex']) . '"></span>') . '<span>' . e($c['nombre']) . '</span>' . (!empty($c['stock']) ? '<small>En stock</small>' : '') . '</li>';
            }
            return "<h2 class=\"panel__h\">$t</h2><ul class=\"colores-grilla\">$g</ul>";
        case 'video':
            if (!preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})~', (string) ($b['url'] ?? ''), $m)) return "<h2 class=\"panel__h\">$t</h2>";
            return "<h2 class=\"panel__h\">$t</h2><div class=\"video\"><iframe src=\"https://www.youtube-nocookie.com/embed/{$m[1]}\" title=\"" . ($t ?: 'Video') . "\" loading=\"lazy\" allow=\"accelerometer; encrypted-media; picture-in-picture\" allowfullscreen></iframe></div>";
        case 'faq':
            $f = '';
            foreach ($b['items'] ?? [] as $q) if (!empty($q['pregunta'])) $f .= '<details class="faq"><summary>' . e($q['pregunta']) . '</summary><div class="faq__r">' . md((string) ($q['respuesta'] ?? '')) . '</div></details>';
            return "<h2 class=\"panel__h\">$t</h2><div class=\"faqs\">$f</div>";
    }
    return '';
}

function ficha_html(array $S, array $p, bool $vistaPrevia = false): string {
    $pre = '../';
    $cat = mv_categoria($S['datos'], $p['categoria']) ?? ['id' => '', 'slug' => 'productos', 'titulo' => 'Productos'];
    $gal = $p['imagenes'] ?: [['src' => $p['miniatura'], 'medio' => $p['miniatura'], 'mini' => $p['miniatura'], 'alt' => '']];
    $principal = $gal[0];
    $n = count($gal); $thumbs = '';
    foreach ($gal as $i => $x) {
        $thumbs .= '<li><button type="button" class="miniatura' . ($i === 0 ? ' activa' : '') . '" data-src="' . e($x['medio'] ?: $x['src']) . '" data-full="' . e($x['src']) . '" aria-label="Ver imagen ' . ($i + 1) . " de $n\"" . ($i === 0 ? ' aria-current="true"' : '') . '>' . img($x['mini'] ?: $x['src'], '', 80, 80) . '</button></li>';
    }
    $desc = md((string) $p['descripcion']);
    $cot = !empty($p['cotizador']['activo']) && !empty($p['cotizador']['colores']);
    $bloques = array_values(array_filter($p['bloques'] ?? [], fn($b) => bloque_html($b, $p) !== ''));
    $tabbtn = $paneles = '';
    foreach ($bloques as $i => $b) {
        $k = e($b['id']);
        $tabbtn .= '<button type="button" role="tab" id="tab-' . $k . '" aria-controls="panel-' . $k . '" aria-selected="' . ($i === 0 ? 'true' : 'false') . '"' . ($i === 0 ? '' : ' tabindex="-1"') . '>' . e($b['titulo'] ?: 'Información') . '</button>';
        $paneles .= '<section class="panel" role="tabpanel" id="panel-' . $k . '" aria-labelledby="tab-' . $k . '">' . bloque_html($b, $p) . '</section>';
    }
    $tabs = $bloques ? "<div class=\"tabs\" data-tabs>\n      <div class=\"tabs__lista\" role=\"tablist\" aria-label=\"Información del producto\">$tabbtn</div>\n      $paneles\n    </div>" : '';
    $tituloFicha = $p['titulo_ficha'] ?: $p['nombre'];
    $sub = $p['titulo_ficha'] ? '<p class="ficha__sub">' . e($p['nombre']) . '</p>' : '';
    $previa = $cot ? '<div class="previa" id="previa" hidden>
          <canvas id="previa-canvas" width="900" height="1080" role="img" aria-label="Vista previa de ' . e($p['corto']) . ' con tu logo"></canvas>
          <div class="previa__pie"><span>Vista previa referencial. Antes de producir te enviamos el diseño final para aprobar.</span><button type="button" class="enlace-boton" id="cz-descargar">Descargar imagen</button></div>
        </div>' : '';
    $tabsVisor = $cot ? '<div class="visor__tabs" hidden><button type="button" data-vista="fotos" aria-pressed="true">Fotos</button><button type="button" data-vista="previa" aria-pressed="false">Tu diseño</button></div>' : '';
    $accion = $cot ? widget($p, $cat) : '<p><a class="btn" href="' . MV_WA . '" target="_blank" rel="noopener">Cotizar por WhatsApp</a> <a class="btn btn--borde" href="../contacto/">Escríbenos</a></p>';
    $e = 'e';
    $cuerpo = <<<HTML

<section class="seccion seccion--ficha">
  <div class="wrap">
    <nav class="migas" aria-label="Ruta"><a href="{$pre}">Inicio</a> / <a href="{$pre}productos/">Productos</a> / <a href="{$pre}{$e($cat['slug'])}/">{$e($cat['titulo'])}</a> / <span>{$e($p['corto'])}</span></nav>
    <div class="ficha">
      <div class="visor">
        $tabsVisor
        <div class="galeria">
          <a class="galeria__principal" href="{$e($principal['src'])}" target="_blank" rel="noopener"><img id="foto-principal" src="{$e($principal['medio'] ?: $principal['src'])}" alt="{$e($p['nombre'])}" width="1024" height="1024" fetchpriority="high"></a>
          <ul class="galeria__miniaturas">$thumbs</ul>
        </div>
        $previa
      </div>
      <div class="ficha__info">
        <h1 class="ficha__h">{$e($tituloFicha)}</h1>
        $sub
        $desc
        $accion
      </div>
    </div>
    $tabs
  </div>
</section>
HTML;
    $seo = trim((string) ($p['seo']['descripcion'] ?? '')) ?: trim(preg_replace('/\*\*|\[|\]\([^)]*\)/', '', preg_split("/\n\s*\n/", (string) $p['descripcion'])[0]));
    $ld = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['nombre'], 'image' => array_map(fn($x) => strpos($x['src'], '/') === 0 ? MV_DOM . $x['src'] : $x['src'], array_slice($gal, 0, 3)),
           'description' => $seo, 'brand' => ['@type' => 'Brand', 'name' => 'Mi Vaso'], 'category' => $cat['titulo'], 'url' => MV_DOM . '/' . $p['slug'] . '/'];
    $js = $cot ? implode('', array_map(fn($n) => "<script src=\"{$pre}assets/js/$n.js\" defer></script>", ['catalogo', 'vista-previa', 'cotiza-ficha'])) : '';
    if ($vistaPrevia && $cot) {
        // En la vista previa del panel, el catálogo viene incrustado con los datos del borrador
        $js = '<script>' . catalogo_js($S, [$p]) . '</script>' . implode('', array_map(fn($n) => "<script src=\"{$pre}assets/js/$n.js\" defer></script>", ['vista-previa', 'cotiza-ficha']));
    }
    $titulo = trim((string) ($p['seo']['titulo'] ?? '')) ?: $p['nombre'] . ' - MiVaso.cl';
    return pagina($S, $p['slug'], $titulo, mb_substr($seo, 0, 300), $cuerpo, $principal['src'], $cat['slug'], $js, $ld);
}

function p_cotizador(array $S): string {
    $pre = '../'; $WA = MV_WA;
    $cuerpo = <<<HTML

<section class="seccion">
  <div class="wrap">
    <h1 class="h2">Mi cotización</h1>
    <div class="carro" id="carro" hidden>
      <div id="carro-vacio" hidden>
        <p class="lead">Aún no agregas productos. Entra a un producto, elige color y cantidad, sube tu logo y agrégalo aquí. Puedes sumar varios productos y colores en una misma cotización.</p>
        <div class="grilla grilla--4" id="carro-catalogo"></div>
      </div>
      <div id="carro-con-items" hidden>
        <div class="carro__grid">
          <div class="carro__lista">
            <ul class="lineas" id="carro-lista"></ul>
            <p class="carro__total" id="carro-total"></p>
            <ul class="carro__problemas" id="carro-problemas" hidden></ul>
            <p><a class="btn btn--borde" href="{$pre}productos/">Agregar otro producto</a></p>
          </div>
          <form class="carro__form caja-form" id="carro-form" action="{$pre}enviar.php" method="post" data-endpoint="{$pre}enviar.php" novalidate>
            <input type="hidden" name="tipo" value="cotizacion">
            <input type="hidden" name="ts" value="">
            <div class="hp" aria-hidden="true"><label for="ct-web">No completar</label><input type="text" id="ct-web" name="web" tabindex="-1" autocomplete="off"></div>
            <h2 class="carro__h">¿Para cuándo lo necesitas?</h2>
            <div class="campo"><label for="ct-fecha">Fecha en que necesitas los productos</label><input type="date" id="ct-fecha" name="fecha" required>
              <p class="cz-ayuda" id="ct-fecha-nota">Pedidos de 500 a 5.000 unidades: hasta 5 días hábiles. Sobre 10.000 coordinamos fechas contigo.</p></div>
            <div class="form__grid">
              <div class="campo"><label for="ct-entrega">Entrega</label><select id="ct-entrega" name="entrega"><option>Retiro</option><option>Despacho</option></select></div>
              <div class="campo" id="ct-comuna-campo" hidden><label for="ct-comuna">Comuna / ciudad de despacho</label><input id="ct-comuna" name="comuna" maxlength="80" required></div>
            </div>
            <h2 class="carro__h">Tus datos</h2>
            <div class="form__grid">
              <div class="campo"><label for="ct-nombre">Nombre completo</label><input id="ct-nombre" name="nombre" autocomplete="name" required maxlength="120"></div>
              <div class="campo"><label for="ct-empresa">Empresa o evento <small>(opcional)</small></label><input id="ct-empresa" name="empresa" autocomplete="organization" maxlength="120"></div>
              <div class="campo"><label for="ct-correo">Correo</label><input id="ct-correo" name="correo" type="email" autocomplete="email" required maxlength="160"></div>
              <div class="campo"><label for="ct-telefono">Teléfono</label><input id="ct-telefono" name="telefono" type="tel" autocomplete="tel" required maxlength="40"></div>
              <div class="campo"><label for="ct-rut">RUT</label><input id="ct-rut" name="rut" required maxlength="14" placeholder="12.345.678-9"></div>
              <div class="campo campo--full"><label for="ct-mensaje">Comentarios <small>(opcional)</small></label><textarea id="ct-mensaje" name="mensaje" rows="3" maxlength="4000" placeholder="Servicio de diseño, detalles del evento, colores Pantone, etc."></textarea></div>
            </div>
            <button type="submit" class="btn">Enviar cotización</button>
            <p class="form__estado" id="carro-estado" role="status" aria-live="polite"></p>
          </form>
        </div>
      </div>
      <div class="carro__exito" id="carro-exito" tabindex="-1" hidden>
        <h2>¡Recibimos tu solicitud!</h2>
        <p>Número de cotización: <strong id="carro-exito-folio"></strong></p>
        <p>Te responderemos a la brevedad al correo que indicaste. Si tienes urgencia, escríbenos por <a href="$WA" target="_blank" rel="noopener">WhatsApp al +56 9 5043 5981</a>.</p>
        <p><a class="btn" href="{$pre}productos/">Volver a productos</a></p>
      </div>
    </div>
    <p class="cotiza-sin-js aviso">Para cotizar en línea necesitas JavaScript activado. También puedes escribirnos a <a href="mailto:contacto@mivaso.cl">contacto@mivaso.cl</a> o por <a href="$WA">WhatsApp</a>.</p>
  </div>
</section>
HTML;
    $js = "<script src=\"{$pre}assets/js/catalogo.js\" defer></script><script src=\"{$pre}assets/js/cotiza-carro.js\" defer></script>";
    return pagina($S, 'cotizador', 'Mi cotización - MiVaso.cl', 'Revisa los vasos y copas que agregaste, indica la fecha que los necesitas y envíanos tu solicitud de cotización.', $cuerpo, MV_U . '2025/12/tapa-380cc-2-B.jpg', 'cotizador', $js);
}

function p_404(array $S): string {
    $c = pagina($S, '', 'Página no encontrada - MiVaso.cl', 'La página que buscas no existe.',
        '<section class="seccion"><div class="wrap angosto centro"><h1 class="h2">No encontramos esta página</h1><p class="lead">Es posible que el enlace haya cambiado. Revisa nuestros productos o vuelve al inicio.</p><p><a class="btn" href="/">Ir al inicio</a> <a class="btn btn--borde" href="/productos/">Ver productos</a></p></div></section>');
    // Rutas absolutas: la 404 puede mostrarse desde cualquier URL
    $c = preg_replace('/(href|src)="(?!https?:|\/|#|mailto:|tel:)\.?\/?([^"]*)"/', '$1="/$2"', $c);
    return str_replace('<link rel="canonical" href="https://mivaso.cl/">', '<meta name="robots" content="noindex">', $c);
}

// ---------------------------------------------------------------- catálogo para el cotizador
function catalogo_js(array $S, ?array $soloEstos = null): string {
    $prods = [];
    foreach ($soloEstos ?? mv_publicados($S['datos']) as $p) {
        if (empty($p['cotizador']['activo']) || empty($p['cotizador']['colores'])) continue;
        $c = mv_categoria($S['datos'], $p['categoria']);
        $cols = [];
        foreach ($p['cotizador']['colores'] as $col) {
            $cols[] = ['id' => $col['id'], 'nombre' => $col['nombre'], 'hex' => $col['hex'], 'alfa' => (float) $col['alfa'], 'stock' => !empty($col['stock']),
                       'foto' => $col['foto']['medio'] ?? ($col['foto']['src'] ?? null), 'fotoGrande' => $col['foto']['src'] ?? null];
        }
        $forma = $p['cotizador']['forma'];
        if (($forma['tipo'] ?? '') === 'foto' && empty($forma['imagen'])) $forma['imagen'] = $p['imagenes'][0]['medio'] ?? ($p['imagenes'][0]['src'] ?? '');
        $prods[] = ['slug' => $p['slug'], 'nombre' => $p['corto'], 'cat' => $c['singular'] ?? ($c['titulo'] ?? ''), 'img' => $p['miniatura'],
                    'forma' => $forma, 'colores' => $cols, 'tintas' => $p['cotizador']['tintas'] ?: ['1'], 'variantes' => $p['cotizador']['variantes'] ?: null];
    }
    $todo = ['productos' => $prods,
             'tintas' => ['1' => '1 color', '2' => '2 colores', '3' => '3 colores', 'full' => '4 colores / Full color'],
             'tintaColores' => [['id' => 'blanco', 'nombre' => 'Blanco', 'hex' => '#FFFFFF'], ['id' => 'negro', 'nombre' => 'Negro', 'hex' => '#111111'],
                                ['id' => 'verde', 'nombre' => 'Verde Mi Vaso', 'hex' => '#4B873E'], ['id' => 'rojo', 'nombre' => 'Rojo', 'hex' => '#C8102E'],
                                ['id' => 'azul', 'nombre' => 'Azul', 'hex' => '#0033A0'], ['id' => 'dorado', 'nombre' => 'Dorado', 'hex' => '#B8933F']],
             'reglas' => ['minimo' => 500, 'minimoPedido' => 500, 'paso' => 100, 'maxLogoMB' => 5, 'maxEnvioMB' => 8, 'margenImpresionMM' => 15]];
    return "/* Catálogo del cotizador. Se genera desde el panel /admin/ (no editar a mano). */\nwindow.MIVASO_CATALOGO = "
        . json_encode($todo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . ";\n";
}

// ---------------------------------------------------------------- publicar
function mv_contexto(?array $datos = null, ?array $cfg = null, ?array $paginas = null): array {
    $cfg = $cfg ?? mv_config();
    return ['datos' => $datos ?? mv_datos(), 'cfg' => $cfg, 'gbody' => mv_google_body($cfg), 'paginas' => $paginas ?? mv_paginas()];
}

/** Escribe todas las páginas. Devuelve la lista de errores (vacía si todo salió bien). */
function mv_publicar_todo(?array $S = null, ?string $destino = null): array {
    $S = $S ?? mv_contexto();
    $raiz = $destino ?? realpath(MV_RAIZ);
    $err = [];
    $escribir = function (string $rel, string $html) use ($raiz, &$err) {
        $ruta = $raiz . '/' . $rel;
        if (!is_dir(dirname($ruta)) && !@mkdir(dirname($ruta), 0755, true)) { $err[] = "No se pudo crear la carpeta de $rel"; return; }
        $tmp = $ruta . '.tmp';
        if (@file_put_contents($tmp, $html, LOCK_EX) === false || !@rename($tmp, $ruta)) $err[] = "No se pudo escribir $rel";
    };
    $urls = ['productos', 'cotizador'];
    $paginas = [];
    // Páginas armadas por secciones (Inicio, Nosotros, ¿Para quién?, Contacto, nuevas y landings)
    foreach ($S['paginas']['paginas'] as $pg) {
        if ($pg['estado'] !== 'publicado') continue;
        $escribir(($pg['slug'] === '' ? '' : $pg['slug'] . '/') . 'index.html', p_dinamica($S, $pg));
        if (empty($pg['seo']['noindex'])) $urls[] = $pg['slug'];
        if (empty($pg['sistema'])) $paginas[] = $pg['slug'];
    }
    $escribir('productos/index.html', p_productos($S));
    $escribir('cotizador/index.html', p_cotizador($S));
    $escribir('404.html', p_404($S));
    $escribir('assets/js/catalogo.js', catalogo_js($S));
    foreach ($S['datos']['categorias'] as $c) { $escribir($c['slug'] . '/index.html', p_categoria($S, $c)); $urls[] = $c['slug']; $paginas[] = $c['slug']; }
    foreach (mv_publicados($S['datos']) as $p) { $escribir($p['slug'] . '/index.html', ficha_html($S, $p)); $urls[] = $p['slug']; $paginas[] = $p['slug']; }
    $map = '';
    usort($urls, fn($a, $b) => ($a !== '') <=> ($b !== ''));
    foreach ($urls as $u) $map .= '<url><loc>' . MV_DOM . '/' . ($u !== '' ? $u . '/' : '') . '</loc></url>';
    $escribir('sitemap.xml', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">$map</urlset>\n");

    // Quitar páginas de productos/categorías que ya no están publicadas (solo las que creó el panel)
    $reg = $destino ? "$raiz/.publicadas.json" : __DIR__ . '/datos/publicadas.json';
    $antes = json_decode((string) @file_get_contents($reg), true) ?: [];
    foreach (array_diff($antes, $paginas) as $viejo) {
        if (!preg_match('/^[a-z0-9-]+$/', $viejo) || in_array($viejo, MV_RESERVADOS, true)) continue;
        $f = "$raiz/$viejo/index.html";
        if (is_file($f)) { @unlink($f); @rmdir("$raiz/$viejo"); }
    }
    @file_put_contents($reg, json_encode(array_values($paginas)), LOCK_EX);
    return $err;
}

/** Valida un slug para producto o categoría. */
function mv_slug_valido(string $s): bool {
    return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?$/', $s) && !in_array($s, MV_RESERVADOS, true) && strpos($s, 'wp-') !== 0;
}
function mv_slugify(string $t): string {
    $t = strtolower(trim(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t));
    return trim(preg_replace('/[^a-z0-9]+/', '-', $t), '-');
}
