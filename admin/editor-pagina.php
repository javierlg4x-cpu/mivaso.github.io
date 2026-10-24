<?php
/** Panel: constructor de productos (pantalla completa). Se incluye desde index.php con sesión iniciada. */
$datos = mv_datos();
$U = MV_U;
$iconos = [['2025/12/ICONO-COPA-TENEDOR.png', 'Apto para alimentos y bebidas'], ['2025/12/MICROHONDA.png', 'Microondas'], ['2025/12/REFRI.png', 'Refrigerador'],
           ['2025/12/LAVAVAJILLA.png', 'Lavavajillas'], ['2025/12/NO-APTO-HORNO.png', 'No apto para horno'], ['2025/12/NO-APTO-REFRI.png', 'Ícono de uso: refrigeración no apta'],
           ['2025/12/REUTILIZABLE.png', 'Reutilizable'], ['2025/12/RECICLABLE.png', 'Reciclable']];
$iconos = array_map(fn($i) => ['src' => $U . $i[0], 'alt' => $i[1]], $iconos);
$presets = [['Natural (transparente)', '#DCE9EE', 0.3], ['Empavonado', '#EEF2F3', 0.78], ['Verde flúor', '#5FE03A', 0.8], ['Amarillo flúor', '#F2EE1D', 0.82],
            ['Naranjo flúor', '#FF7A1C', 0.82], ['Fucsia flúor', '#FF2E93', 0.8], ['Rojo', '#D3262B', 0.82], ['Azul', '#1E6CD0', 0.8],
            ['Morado (lila)', '#8E47C9', 0.8], ['Negro', '#1E1E1E', 0.93], ['Blanco', '#F7F7F5', 0.95], ['Reciclado (tono variable)', '#3E4540', 1]];

$slug = (string) ($_GET['slug'] ?? '');
$prod = null; $slugOriginal = '';
foreach ($datos['productos'] as $p) if ($p['slug'] === $slug) { $prod = $p; $slugOriginal = $slug; }
if (!$prod && !empty($_GET['copiar'])) {
    foreach ($datos['productos'] as $p) if ($p['slug'] === $_GET['copiar']) $prod = $p;
    if ($prod) { $prod['nombre'] = 'Copia de ' . $prod['nombre']; $prod['corto'] = 'Copia de ' . $prod['corto']; $prod['slug'] = ''; $prod['estado'] = 'borrador'; $prod['destacado']['activo'] = false; }
}
if (!$prod) {
    if ($slug !== '') { http_response_code(404); echo 'Producto no encontrado. <a href="./?p=productos">Volver</a>'; return; }
    $cat = (string) ($_GET['categoria'] ?? ($datos['categorias'][0]['id'] ?? ''));
    $prod = [
        'slug' => '', 'nombre' => '', 'corto' => '', 'categoria' => $cat, 'estado' => 'borrador', 'titulo_ficha' => '', 'descripcion' => '',
        'imagenes' => [], 'miniatura' => '', 'destacado' => ['activo' => false, 'img' => ''],
        'cotizador' => ['activo' => true, 'forma' => ['tipo' => 'vaso', 'fondo' => 53, 'boca' => 74, 'alto' => 138],
            'colores' => [['id' => 'natural', 'nombre' => 'Natural (transparente)', 'hex' => '#DCE9EE', 'alfa' => 0.3, 'stock' => true, 'foto' => null]],
            'tintas' => ['1', '2', '3', 'full'], 'variantes' => []],
        'bloques' => [
            ['id' => 'detalles', 'tipo' => 'specs', 'titulo' => 'Detalles del producto', 'filas' => [['Material', ''], ['Capacidad', ''], ['Peso', ''], ['Medidas', '']]],
            ['id' => 'diseno', 'tipo' => 'diseno', 'titulo' => 'Diseño', 'color' => 'pantone', 'plantilla' => true],
            ['id' => 'adjuntos', 'tipo' => 'adjuntos', 'titulo' => 'Adjuntos', 'items' => [['titulo' => 'Guía de impresión', 'url' => MV_GUIA]]],
            ['id' => 'usos', 'tipo' => 'usos', 'titulo' => 'Usos del producto', 'texto' => 'Válido para contacto directo con alimentos o bebidas frías y calientes.', 'iconos' => $iconos],
        ],
        'seo' => ['titulo' => '', 'descripcion' => ''],
    ];
}
$ED = ['producto' => $prod, 'slugOriginal' => $slugOriginal, 'revision' => (int) ($datos['revision'] ?? 0),
       'categorias' => array_map(fn($c) => ['id' => $c['id'], 'titulo' => $c['titulo']], $datos['categorias']),
       'iconos' => $iconos, 'presets' => $presets];
?><!doctype html>
<html lang="es-CL"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= $slugOriginal ? 'Editar ' . mv_h($prod['corto']) : 'Nuevo producto' ?> · Panel Mi Vaso</title>
<link rel="stylesheet" href="panel.css"><meta name="csrf" content="<?= mv_h($_SESSION['csrf']) ?>">
<script src="panel.js" defer></script><script src="editor.js" defer></script>
</head>
<body class="editor">
<header class="ed-barra">
  <div class="ed-barra__t"><a class="btn borde chico" href="./?p=productos">← Productos</a><strong id="ed-titulo"></strong><span class="pill" id="ed-pill"></span></div>
  <div class="ed-estado" id="ed-estado" role="status" aria-live="polite"></div>
  <div class="acciones">
    <a class="btn borde chico" id="ed-ver" target="_blank" rel="noopener" hidden>Ver en el sitio</a>
    <button type="button" class="btn borde" id="ed-guardar">Guardar borrador</button>
    <button type="button" class="btn" id="ed-publicar">Publicar</button>
  </div>
</header>
<div class="ed">
  <div class="ed-panel" id="ed-panel">
    <div class="ed-tabs" role="tablist">
      <button type="button" role="tab" aria-selected="true" data-sec="contenido">Contenido</button>
      <button type="button" role="tab" aria-selected="false" data-sec="cotizador">Cotizador</button>
      <button type="button" role="tab" aria-selected="false" data-sec="bloques">Bloques</button>
      <button type="button" role="tab" aria-selected="false" data-sec="ajustes">Ajustes</button>
    </div>
    <div class="ed-sec" id="sec-contenido"></div>
    <div class="ed-sec" id="sec-cotizador" hidden></div>
    <div class="ed-sec" id="sec-bloques" hidden></div>
    <div class="ed-sec" id="sec-ajustes" hidden></div>
  </div>
  <div class="ed-vista" id="ed-vista">
    <div class="ed-vista__barra"><span id="ed-vista-estado">Vista previa en vivo</span>
      <span class="seg"><button type="button" aria-pressed="true" data-ancho="escritorio">Escritorio</button><button type="button" aria-pressed="false" data-ancho="movil">Celular</button></span></div>
    <div class="ed-vista__marco"><iframe id="ed-iframe" title="Vista previa de la página del producto"></iframe></div>
  </div>
</div>
<script id="ed-datos" type="application/json"><?= json_encode($ED, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</body></html>
