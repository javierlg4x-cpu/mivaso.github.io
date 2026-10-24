<?php
/** Panel: constructor de páginas por secciones (pantalla completa). Se incluye desde index.php con sesión iniciada. */
$datos = mv_datos();
$PG = mv_paginas();
$id = (string) ($_GET['id'] ?? '');
$pg = $id !== '' ? mv_pagina_por_id($PG, $id) : null;
$idOriginal = $pg ? $pg['id'] : '';
if ($id !== '' && !$pg) { http_response_code(404); echo 'Página no encontrada. <a href="./?p=paginas">Volver</a>'; return; }
if (!$pg && !empty($_GET['copiar']) && ($orig = mv_pagina_por_id($PG, (string) $_GET['copiar']))) {
    $pg = $orig; $pg['id'] = ''; $pg['sistema'] = false; $pg['estado'] = 'borrador'; $pg['titulo'] = 'Copia de ' . $orig['titulo']; $pg['slug'] = '';
    foreach ($pg['secciones'] as &$s) $s['id'] = 's-' . bin2hex(random_bytes(4));
    unset($s);
}
if (!$pg) {
    $cual = (string) ($_GET['nuevo'] ?? 'blanco');
    $pg = mv_plantilla_pagina(in_array($cual, ['pagina', 'evento'], true) ? $cual : 'blanco');
    $pg['titulo'] = $cual === 'evento' ? 'Landing evento' : '';
}
$enMenu = false;
foreach ($PG['menu'] as $m) if (($m['ref'] ?? '') === $idOriginal && $idOriginal !== '') $enMenu = true;
if ($idOriginal === '') $enMenu = $pg['tipo'] !== 'landing';

// Enlaces internos para elegir rápido en los campos de enlace
$enlaces = [['/', 'Inicio']];
foreach ($PG['paginas'] as $p) if ($p['slug'] !== '') $enlaces[] = ['/' . $p['slug'] . '/', 'Página: ' . $p['titulo']];
$enlaces[] = ['/productos/', 'Productos (todas las categorías)'];
$enlaces[] = ['/cotizador/', 'Mi cotización'];
foreach ($datos['categorias'] as $c) $enlaces[] = ['/' . $c['slug'] . '/', 'Categoría: ' . $c['titulo']];
foreach ($datos['productos'] as $p) if ($p['estado'] === 'publicado') $enlaces[] = ['/' . $p['slug'] . '/', 'Producto: ' . $p['corto']];
$enlaces[] = [MV_WA, 'WhatsApp'];
$enlaces[] = ['mailto:contacto@mivaso.cl', 'Correo contacto@mivaso.cl'];

$ED = ['pagina' => $pg, 'idOriginal' => $idOriginal, 'revision' => (int) ($PG['revision'] ?? 0), 'enMenu' => $enMenu,
       'esquema' => mv_esquema(), 'comunes' => mv_esquema_comun(), 'enlaces' => $enlaces,
       'productos' => array_map(fn($p) => ['slug' => $p['slug'], 'nombre' => $p['corto'], 'mini' => $p['miniatura'], 'publicado' => $p['estado'] === 'publicado'], $datos['productos']),
       'categorias' => array_map(fn($c) => ['id' => $c['id'], 'titulo' => $c['titulo']], $datos['categorias'])];
?><!doctype html>
<html lang="es-CL"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= $idOriginal ? 'Editar ' . mv_h($pg['titulo']) : 'Nueva página' ?> · Panel Mi Vaso</title>
<link rel="stylesheet" href="panel.css"><meta name="csrf" content="<?= mv_h($_SESSION['csrf']) ?>">
<script src="panel.js" defer></script><script src="constructor.js" defer></script>
</head>
<body class="editor">
<header class="ed-barra">
  <div class="ed-barra__t"><a class="btn borde chico" href="./?p=paginas">← Páginas</a><strong id="ed-titulo"></strong><span class="pill" id="ed-pill"></span></div>
  <div class="ed-estado" id="ed-estado" role="status" aria-live="polite"></div>
  <div class="acciones">
    <button type="button" class="btn borde chico" id="ed-deshacer" title="Deshacer (Ctrl+Z)" disabled>↶ Deshacer</button>
    <a class="btn borde chico" id="ed-ver" target="_blank" rel="noopener" hidden>Ver en el sitio</a>
    <button type="button" class="btn borde" id="ed-guardar">Guardar borrador</button>
    <button type="button" class="btn" id="ed-publicar">Publicar</button>
  </div>
</header>
<div class="ed">
  <div class="ed-panel" id="ed-panel">
    <div class="ed-tabs" role="tablist">
      <button type="button" role="tab" aria-selected="true" data-sec="secciones">Secciones</button>
      <button type="button" role="tab" aria-selected="false" data-sec="ajustes">Página</button>
    </div>
    <div class="ed-sec" id="sec-secciones"></div>
    <div class="ed-sec" id="sec-ajustes" hidden></div>
  </div>
  <div class="ed-vista" id="ed-vista">
    <div class="ed-vista__barra"><span id="ed-vista-estado">Vista previa en vivo · haz clic en una sección para editarla</span>
      <span class="seg"><button type="button" aria-pressed="true" data-ancho="escritorio">Escritorio</button><button type="button" aria-pressed="false" data-ancho="movil">Celular</button></span></div>
    <div class="ed-vista__marco"><iframe id="ed-iframe" title="Vista previa de la página"></iframe></div>
  </div>
</div>
<datalist id="ed-enlaces"><?php foreach ($enlaces as [$u, $t]): ?><option value="<?= mv_h($u) ?>"><?= mv_h($t) ?></option><?php endforeach; ?></datalist>
<script id="ed-datos" type="application/json"><?= json_encode($ED, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</body></html>
