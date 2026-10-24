<?php
/**
 * Mi Vaso — páginas armadas por secciones (Inicio, Nosotros, ¿Para quién?, Contacto, páginas nuevas y landings).
 * Las páginas se guardan como datos en privado/datos/paginas.json y se convierten en HTML estático al publicar.
 * El mismo esquema sirve para validar lo que llega del editor y para dibujar el formulario del panel.
 */
declare(strict_types=1);

const MV_PAGINAS = __DIR__ . '/datos/paginas.json';
const MV_PAGINAS_SISTEMA = ['inicio' => '', 'nosotros' => 'nosotros', 'para-quien' => 'econimia-circular', 'contacto' => 'contacto'];

// ---------------------------------------------------------------- esquema de secciones
function mv_esquema(): array {
    $boton = [['k' => 'boton_texto', 't' => 'texto', 'e' => 'Texto del botón', 'max' => 40, 'ayuda' => 'Déjalo vacío si no quieres botón.'],
              ['k' => 'boton_url', 't' => 'enlace', 'e' => 'Enlace del botón'],
              ['k' => 'boton_estilo', 't' => 'opcion', 'e' => 'Estilo del botón', 'op' => ['relleno' => 'Verde', 'borde' => 'Solo borde', 'claro' => 'Blanco'], 'def' => 'relleno']];
    $cab = [['k' => 'titulo', 't' => 'texto', 'e' => 'Título', 'max' => 160], ['k' => 'intro', 't' => 'parrafo', 'e' => 'Texto de introducción', 'max' => 1200, 'filas' => 3]];
    $cols = fn(int $def, int $min = 2, int $max = 6) => ['k' => 'columnas', 't' => 'numero', 'e' => 'Columnas (en computador)', 'min' => $min, 'max' => $max, 'def' => $def];
    return [
        'banner' => ['n' => 'Banner / carrusel', 'd' => 'Imágenes grandes con texto, botón y animación', 'i' => 'M3 5h18v14H3zM3 15l5-4 4 3 3-2 6 4M8 19v2M16 19v2', 'campos' => [
            ['k' => 'slides', 't' => 'lista', 'e' => 'Diapositivas', 'max' => 8, 'nuevo' => 'Agregar diapositiva', 'resumen' => 'titulo', 'sub' => [
                ['k' => 'img', 't' => 'img', 'e' => 'Imagen (horizontal, ideal 1920 × 800)'],
                ['k' => 'img_movil', 't' => 'img', 'e' => 'Imagen para celular (opcional, vertical)'],
                ['k' => 'titulo', 't' => 'texto', 'e' => 'Título', 'max' => 120],
                ['k' => 'texto', 't' => 'texto', 'e' => 'Texto', 'max' => 300],
                ['k' => 'boton_texto', 't' => 'texto', 'e' => 'Texto del botón', 'max' => 40],
                ['k' => 'boton_url', 't' => 'enlace', 'e' => 'Enlace del botón'],
                ['k' => 'posicion', 't' => 'opcion', 'e' => 'Posición del texto', 'op' => ['izq' => 'Izquierda', 'centro' => 'Centro', 'der' => 'Derecha'], 'def' => 'izq'],
                ['k' => 'alt', 't' => 'texto', 'e' => 'Descripción de la imagen (para Google y lectores de pantalla)', 'max' => 160]]],
            ['k' => 'alto', 't' => 'opcion', 'e' => 'Alto', 'op' => ['chico' => 'Bajo', 'medio' => 'Medio', 'grande' => 'Alto', 'pantalla' => 'Pantalla completa'], 'def' => 'grande'],
            ['k' => 'efecto', 't' => 'opcion', 'e' => 'Animación', 'op' => ['fundido' => 'Fundido', 'deslizar' => 'Deslizar', 'zoom' => 'Fundido con zoom lento'], 'def' => 'zoom'],
            ['k' => 'intervalo', 't' => 'numero', 'e' => 'Segundos por diapositiva', 'min' => 3, 'max' => 20, 'def' => 6],
            ['k' => 'oscurecer', 't' => 'opcion', 'e' => 'Oscurecer la imagen bajo el texto', 'op' => ['no' => 'No', 'suave' => 'Suave', 'fuerte' => 'Fuerte'], 'def' => 'suave']]],
        'texto_imagen' => ['n' => 'Imagen y texto', 'd' => 'Dos columnas: foto a un lado y texto al otro', 'i' => 'M3 5h8v14H3zM14 7h7M14 11h7M14 15h5', 'campos' => array_merge([
            ['k' => 'img', 't' => 'img', 'e' => 'Imagen'],
            ['k' => 'posicion', 't' => 'opcion', 'e' => 'Imagen a la', 'op' => ['izq' => 'Izquierda', 'der' => 'Derecha'], 'def' => 'izq'],
            ['k' => 'tam_img', 't' => 'opcion', 'e' => 'Tamaño de la imagen', 'op' => ['normal' => 'Normal', 'chica' => 'Más chica'], 'def' => 'normal']],
            $cab, [['k' => 'texto', 't' => 'parrafo', 'e' => 'Texto', 'max' => 6000, 'filas' => 6]], $boton)],
        'texto' => ['n' => 'Título y texto', 'd' => 'Párrafos, listas y enlaces', 'i' => 'M5 6h14M5 10h14M5 14h10M5 18h8', 'campos' => array_merge($cab, [
            ['k' => 'texto', 't' => 'parrafo', 'e' => 'Texto', 'max' => 12000, 'filas' => 8],
            ['k' => 'alinear', 't' => 'opcion', 'e' => 'Alineación', 'op' => ['izq' => 'Izquierda', 'centro' => 'Centrado'], 'def' => 'izq'],
            ['k' => 'ancho', 't' => 'opcion', 'e' => 'Ancho', 'op' => ['angosto' => 'Angosto (mejor para leer)', 'normal' => 'Normal'], 'def' => 'angosto']], $boton)],
        'iconos' => ['n' => 'Íconos con texto', 'd' => 'Beneficios, pasos o características', 'i' => 'M5 5h4v4H5zM15 5h4v4h-4zM5 15h4v4H5zM15 15h4v4h-4z', 'campos' => array_merge($cab, [
            ['k' => 'estilo', 't' => 'opcion', 'e' => 'Estilo', 'op' => ['tarjeta' => 'Ícono arriba, título y texto', 'pilar' => 'Ícono grande y título', 'fila' => 'Ícono a la izquierda (lista)'], 'def' => 'tarjeta'],
            $cols(3),
            ['k' => 'items', 't' => 'lista', 'e' => 'Elementos', 'max' => 24, 'nuevo' => 'Agregar elemento', 'resumen' => 'titulo', 'sub' => [
                ['k' => 'img', 't' => 'img', 'e' => 'Ícono o imagen'], ['k' => 'titulo', 't' => 'texto', 'e' => 'Título', 'max' => 120],
                ['k' => 'texto', 't' => 'parrafo', 'e' => 'Texto', 'max' => 800, 'filas' => 2], ['k' => 'url', 't' => 'enlace', 'e' => 'Enlace (opcional)']]]], $boton)],
        'tarjetas' => ['n' => 'Tarjetas', 'd' => 'Bloques con imagen, texto y «Saber más»', 'i' => 'M3 5h8v6H3zM13 5h8v6h-8zM3 13h8v6H3zM13 13h8v6h-8z', 'campos' => array_merge($cab, [
            $cols(2, 1, 3),
            ['k' => 'items', 't' => 'lista', 'e' => 'Tarjetas', 'max' => 12, 'nuevo' => 'Agregar tarjeta', 'resumen' => 'titulo', 'sub' => [
                ['k' => 'img', 't' => 'img', 'e' => 'Imagen'], ['k' => 'titulo', 't' => 'texto', 'e' => 'Título', 'max' => 120],
                ['k' => 'etiqueta', 't' => 'texto', 'e' => 'Subtítulo', 'max' => 80], ['k' => 'texto', 't' => 'parrafo', 'e' => 'Texto', 'max' => 2000, 'filas' => 4],
                ['k' => 'url', 't' => 'enlace', 'e' => 'Enlace'], ['k' => 'boton_texto', 't' => 'texto', 'e' => 'Texto del enlace', 'max' => 40, 'def' => 'Saber más']]]], $boton)],
        'desplegables' => ['n' => 'Columnas desplegables', 'd' => 'Ilustración, título y una barra que abre el texto (2 a 4 columnas)', 'i' => 'M3 4h5v5H3zM10 4h5v5h-5zM17 4h4v5h-4zM3 12h5v2H3zM10 12h5v2h-5zM17 12h4v2h-4zM3 17h5v3H3z', 'campos' => array_merge($cab, [
            $cols(4, 2, 4),
            ['k' => 'abrir', 't' => 'opcion', 'e' => 'Al cargar la página', 'op' => ['cerradas' => 'Todas cerradas', 'abiertas' => 'Todas abiertas', 'primera' => 'Solo la primera abierta'], 'def' => 'cerradas'],
            ['k' => 'una', 't' => 'si', 'e' => 'Al abrir una, cerrar las demás'],
            ['k' => 'items', 't' => 'lista', 'e' => 'Columnas', 'max' => 8, 'nuevo' => 'Agregar columna', 'resumen' => 'titulo', 'sub' => [
                ['k' => 'img', 't' => 'img', 'e' => 'Ilustración'], ['k' => 'titulo', 't' => 'texto', 'e' => 'Título', 'max' => 120],
                ['k' => 'etiqueta', 't' => 'texto', 'e' => 'Texto de la barra verde (se toca para abrir)', 'max' => 80],
                ['k' => 'texto', 't' => 'parrafo', 'e' => 'Texto que se despliega', 'max' => 3000, 'filas' => 5],
                ['k' => 'url', 't' => 'enlace', 'e' => 'Enlace «Saber más» (opcional)'], ['k' => 'boton_texto', 't' => 'texto', 'e' => 'Texto del enlace', 'max' => 40, 'def' => 'Saber más…']]]], $boton)],
        'productos' => ['n' => 'Productos', 'd' => 'Destacados, de una categoría o elegidos a mano', 'i' => 'M7 4h10l-1 16H8zM7 8h10', 'campos' => array_merge($cab, [
            ['k' => 'modo', 't' => 'opcion', 'e' => 'Qué mostrar', 'op' => ['destacados' => 'Productos destacados', 'categoria' => 'Productos de una categoría', 'elegidos' => 'Productos elegidos a mano', 'categorias' => 'Las categorías'], 'def' => 'destacados'],
            ['k' => 'categoria', 't' => 'categoria', 'e' => 'Categoría', 'si' => ['modo', 'categoria']],
            ['k' => 'elegidos', 't' => 'productos', 'e' => 'Productos', 'si' => ['modo', 'elegidos']],
            ['k' => 'maximo', 't' => 'numero', 'e' => 'Máximo de productos', 'min' => 1, 'max' => 24, 'def' => 8],
            $cols(4, 2, 4)], $boton)],
        'logos' => ['n' => 'Logos de clientes', 'd' => 'Marcas que confían, en carrusel o grilla', 'i' => 'M4 8h4v8H4zM10 8h4v8h-4zM16 8h4v8h-4z', 'campos' => array_merge($cab, [
            ['k' => 'estilo', 't' => 'opcion', 'e' => 'Estilo', 'op' => ['carrusel' => 'Carrusel en movimiento', 'grilla' => 'Grilla'], 'def' => 'carrusel'],
            ['k' => 'items', 't' => 'lista', 'e' => 'Logos', 'max' => 60, 'nuevo' => 'Agregar logo', 'resumen' => 'nombre', 'compacta' => true, 'sub' => [
                ['k' => 'img', 't' => 'img', 'e' => 'Logo'], ['k' => 'nombre', 't' => 'texto', 'e' => 'Nombre de la marca', 'max' => 80]]]], $boton)],
        'acordeon' => ['n' => 'Acordeón / preguntas', 'd' => 'Textos que se abren al hacer clic', 'i' => 'M4 6h16M4 12h16M4 18h16M17 4l2 2-2 2', 'campos' => array_merge($cab, [
            ['k' => 'items', 't' => 'lista', 'e' => 'Preguntas', 'max' => 40, 'nuevo' => 'Agregar pregunta', 'resumen' => 'pregunta', 'sub' => [
                ['k' => 'pregunta', 't' => 'texto', 'e' => 'Pregunta o título', 'max' => 200], ['k' => 'respuesta', 't' => 'parrafo', 'e' => 'Respuesta', 'max' => 4000, 'filas' => 3]]],
            ['k' => 'abrir_primero', 't' => 'si', 'e' => 'Mostrar la primera abierta'],
            ['k' => 'ancho', 't' => 'opcion', 'e' => 'Ancho', 'op' => ['angosto' => 'Angosto', 'normal' => 'Normal'], 'def' => 'angosto']], $boton)],
        'imagen' => ['n' => 'Imagen', 'd' => 'Foto, infografía o banner estático', 'i' => 'M4 5h16v14H4zM4 15l5-5 4 4 3-3 4 4', 'campos' => [
            ['k' => 'titulo', 't' => 'texto', 'e' => 'Título (opcional)', 'max' => 160], ['k' => 'img', 't' => 'img', 'e' => 'Imagen'],
            ['k' => 'estilo', 't' => 'opcion', 'e' => 'Presentación', 'op' => ['contenido' => 'Al ancho del contenido', 'caja' => 'En un recuadro blanco', 'completo' => 'De borde a borde'], 'def' => 'contenido'],
            ['k' => 'pie', 't' => 'texto', 'e' => 'Pie de foto', 'max' => 200], ['k' => 'url', 't' => 'enlace', 'e' => 'Al hacer clic, ir a (opcional)']]],
        'galeria' => ['n' => 'Galería de fotos', 'd' => 'Varias fotos que se amplían al tocarlas', 'i' => 'M3 3h8v8H3zM13 3h8v8h-8zM3 13h8v8H3zM13 13h8v8h-8z', 'campos' => array_merge($cab, [
            $cols(3, 2, 5),
            ['k' => 'items', 't' => 'lista', 'e' => 'Fotos', 'max' => 60, 'nuevo' => 'Agregar foto', 'resumen' => 'pie', 'compacta' => true, 'sub' => [
                ['k' => 'img', 't' => 'img', 'e' => 'Foto'], ['k' => 'pie', 't' => 'texto', 'e' => 'Descripción', 'max' => 160]]]])],
        'video' => ['n' => 'Video', 'd' => 'Video de YouTube', 'i' => 'M4 6h16v12H4zM10 9l5 3-5 3z', 'campos' => array_merge($cab, [
            ['k' => 'url', 't' => 'texto', 'e' => 'Enlace de YouTube', 'max' => 200, 'ayuda' => 'Ej.: https://www.youtube.com/watch?v=… (se ve en el sitio publicado).']])],
        'cta' => ['n' => 'Llamado a la acción', 'd' => 'Banda destacada con título y botones', 'i' => 'M3 8h18v8H3zM8 12h8', 'campos' => [
            ['k' => 'titulo', 't' => 'texto', 'e' => 'Título', 'max' => 160], ['k' => 'texto', 't' => 'parrafo', 'e' => 'Texto', 'max' => 1200, 'filas' => 3],
            ['k' => 'boton_texto', 't' => 'texto', 'e' => 'Botón principal', 'max' => 40], ['k' => 'boton_url', 't' => 'enlace', 'e' => 'Enlace del botón principal'],
            ['k' => 'boton2_texto', 't' => 'texto', 'e' => 'Segundo botón (opcional)', 'max' => 40], ['k' => 'boton2_url', 't' => 'enlace', 'e' => 'Enlace del segundo botón']]],
        'contador' => ['n' => 'Cuenta regresiva', 'd' => 'Días, horas y minutos para un evento', 'i' => 'M12 7v5l3 2M12 3a9 9 0 100 18 9 9 0 000-18z', 'campos' => array_merge($cab, [
            ['k' => 'fecha', 't' => 'fecha', 'e' => 'Fecha y hora del evento (hora de Chile)'],
            ['k' => 'texto_fin', 't' => 'texto', 'e' => 'Texto cuando la fecha ya pasó', 'max' => 160, 'def' => '¡El evento ya comenzó!']], $boton)],
        'numeros' => ['n' => 'Cifras', 'd' => 'Números que se animan al aparecer', 'i' => 'M5 18V9M10 18V5M15 18v-6M20 18V8', 'campos' => array_merge($cab, [
            $cols(4, 2, 5),
            ['k' => 'items', 't' => 'lista', 'e' => 'Cifras', 'max' => 8, 'nuevo' => 'Agregar cifra', 'resumen' => 'texto', 'sub' => [
                ['k' => 'numero', 't' => 'numero', 'e' => 'Número', 'min' => 0, 'max' => 999999999, 'def' => 100],
                ['k' => 'prefijo', 't' => 'texto', 'e' => 'Antes del número (ej.: +)', 'max' => 8],
                ['k' => 'sufijo', 't' => 'texto', 'e' => 'Después del número (ej.: %, mil)', 'max' => 12],
                ['k' => 'texto', 't' => 'texto', 'e' => 'Descripción', 'max' => 100]]]])],
        'contacto' => ['n' => 'Formulario de contacto', 'd' => 'Formulario que llega a tu correo', 'i' => 'M3 6h18v12H3zM3 7l9 6 9-6', 'campos' => [
            ['k' => 'estilo', 't' => 'opcion', 'e' => 'Estilo', 'op' => ['banda' => 'Banda verde', 'caja' => 'Fondo claro con recuadro'], 'def' => 'banda'],
            ['k' => 'titulo', 't' => 'texto', 'e' => 'Título', 'max' => 160], ['k' => 'intro', 't' => 'parrafo', 'e' => 'Texto destacado', 'max' => 600, 'filas' => 2],
            ['k' => 'texto', 't' => 'parrafo', 'e' => 'Texto', 'max' => 1200, 'filas' => 2],
            ['k' => 'datos', 't' => 'lista', 'e' => 'Datos de contacto', 'max' => 8, 'nuevo' => 'Agregar dato', 'resumen' => 'etiqueta', 'sub' => [
                ['k' => 'etiqueta', 't' => 'texto', 'e' => 'Etiqueta (ej.: WhatsApp)', 'max' => 60], ['k' => 'valor', 't' => 'texto', 'e' => 'Valor', 'max' => 120],
                ['k' => 'url', 't' => 'enlace', 'e' => 'Enlace (opcional: https://wa.me/…, tel:…, mailto:…)']]],
            ['k' => 'etiqueta_nombre', 't' => 'texto', 'e' => 'Etiqueta del campo nombre', 'max' => 40, 'def' => 'Nombre']]],
        'mapa' => ['n' => 'Mapa', 'd' => 'Mapa de Google Maps', 'i' => 'M12 21s-6-5.3-6-11a6 6 0 0112 0c0 5.7-6 11-6 11zM12 12a2 2 0 100-4 2 2 0 000 4z', 'campos' => [
            ['k' => 'titulo', 't' => 'texto', 'e' => 'Título (opcional)', 'max' => 160],
            ['k' => 'url', 't' => 'texto', 'e' => 'Enlace para insertar el mapa', 'max' => 1500, 'ayuda' => 'En Google Maps: Compartir → Insertar un mapa → copia solo lo que está dentro de src="…" (empieza con https://www.google.com/maps/embed).'],
            ['k' => 'alto', 't' => 'numero', 'e' => 'Alto en píxeles', 'min' => 200, 'max' => 700, 'def' => 380]]],
        'espacio' => ['n' => 'Espacio / separador', 'd' => 'Aire entre secciones o una línea', 'i' => 'M4 12h16M8 8l-4 4 4 4M16 8l4 4-4 4', 'campos' => [
            ['k' => 'alto', 't' => 'opcion', 'e' => 'Alto', 'op' => ['chico' => 'Chico', 'medio' => 'Medio', 'grande' => 'Grande'], 'def' => 'medio'],
            ['k' => 'linea', 't' => 'si', 'e' => 'Mostrar una línea']]],
    ];
}
/** Opciones de diseño que tienen todas las secciones. */
function mv_esquema_comun(): array {
    return [
        ['k' => 'fondo', 't' => 'opcion', 'e' => 'Fondo', 'op' => ['blanco' => 'Blanco', 'claro' => 'Verde muy claro', 'verde' => 'Verde Mi Vaso', 'imagen' => 'Imagen de fondo'], 'def' => 'blanco'],
        ['k' => 'fondo_img', 't' => 'img', 'e' => 'Imagen de fondo', 'si' => ['fondo', 'imagen']],
        ['k' => 'espacio', 't' => 'opcion', 'e' => 'Espacio arriba y abajo', 'op' => ['normal' => 'Normal', 'compacto' => 'Compacto', 'unir' => 'Unir con la sección anterior', 'ninguno' => 'Sin espacio'], 'def' => 'normal'],
        ['k' => 'animacion', 't' => 'opcion', 'e' => 'Animación al aparecer', 'op' => ['ninguna' => 'Ninguna', 'aparecer' => 'Aparecer', 'subir' => 'Subir', 'izquierda' => 'Entrar desde la izquierda', 'derecha' => 'Entrar desde la derecha', 'zoom' => 'Acercar'], 'def' => 'subir'],
        ['k' => 'ancla', 't' => 'texto', 'e' => 'Ancla (para enlazar directo a esta sección: /pagina/#ancla)', 'max' => 40],
    ];
}

// ---------------------------------------------------------------- validación
function mv_enlace_ok($u): string {
    $u = trim((string) $u);
    if ($u === '') return '';
    if (preg_match('#^https?://[A-Za-z0-9.-]+(?::\d+)?(/[^\s"\'<>]*)?$#', $u)) return $u;
    if (preg_match('#^/(?!/)[A-Za-z0-9/_.\#?=&%+-]*$#', $u)) return $u;
    if (preg_match('#^\#[A-Za-z0-9_-]+$#', $u)) return $u;
    if (preg_match('#^mailto:[^\s"\'<>]+$#', $u)) return $u;
    if (preg_match('#^tel:[+0-9 ()-]+$#', $u)) return $u;
    return '';
}
function mv_campo_valor(array $c, $v, array $ctx) {
    switch ($c['t']) {
        case 'texto':   return mv_texto($v ?? ($c['def'] ?? ''), (int) ($c['max'] ?? 200));
        case 'parrafo': return mv_texto($v ?? '', (int) ($c['max'] ?? 4000), true);
        case 'img':     return function_exists('img_ok') ? img_ok($v) : (is_array($v) ? $v : null);
        case 'enlace':  return mv_enlace_ok($v);
        case 'opcion':  return isset($c['op'][(string) $v]) ? (string) $v : (string) ($c['def'] ?? array_key_first($c['op']));
        case 'numero':  $x = is_numeric($v) ? (float) $v : (float) ($c['def'] ?? $c['min'] ?? 0); return (int) round(max($c['min'] ?? 0, min($c['max'] ?? 1e9, $x)));
        case 'si':      return !empty($v);
        case 'fecha':   return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v) ? $v : '';
        case 'categoria': return in_array((string) $v, $ctx['categorias'], true) ? (string) $v : ($ctx['categorias'][0] ?? '');
        case 'productos': return array_values(array_slice(array_intersect(array_map('strval', (array) $v), $ctx['productos']), 0, 24));
        case 'lista':
            $out = [];
            foreach (array_slice(is_array($v) ? $v : [], 0, (int) ($c['max'] ?? 20)) as $it) {
                if (!is_array($it)) continue;
                $o = [];
                foreach ($c['sub'] as $s) $o[$s['k']] = mv_campo_valor($s, $it[$s['k']] ?? null, $ctx);
                $out[] = $o;
            }
            return $out;
    }
    return null;
}
function mv_normalizar_seccion(array $in, array $ctx): ?array {
    $E = mv_esquema();
    $tipo = (string) ($in['tipo'] ?? '');
    if (!isset($E[$tipo])) return null;
    $id = preg_match('/^[a-z0-9-]{3,40}$/', (string) ($in['id'] ?? '')) ? $in['id'] : 's-' . bin2hex(random_bytes(4));
    $s = ['id' => $id, 'tipo' => $tipo, 'oculto' => !empty($in['oculto'])];
    foreach (array_merge($E[$tipo]['campos'], mv_esquema_comun()) as $c) $s[$c['k']] = mv_campo_valor($c, $in[$c['k']] ?? null, $ctx);
    $s['ancla'] = mv_slugify((string) $s['ancla']) === '' ? '' : preg_replace('/[^A-Za-z0-9_-]/', '', (string) $s['ancla']);
    return $s;
}
function mv_ctx_validacion(array $datos): array {
    return ['categorias' => array_map(fn($c) => $c['id'], $datos['categorias']), 'productos' => array_map(fn($p) => $p['slug'], $datos['productos'])];
}
/** Limpia una página que llega del editor. Devuelve [pagina, errores]. */
function mv_normalizar_pagina(array $in, array $PG, array $datos, string $idOriginal = ''): array {
    $err = [];
    $orig = null;
    foreach ($PG['paginas'] as $p) if ($p['id'] === $idOriginal && $idOriginal !== '') $orig = $p;
    $sistema = $orig && !empty($orig['sistema']);
    $titulo = mv_texto($in['titulo'] ?? '', 120);
    if ($titulo === '') $err[] = 'Ponle un nombre a la página.';
    $id = $orig ? $orig['id'] : 'p-' . bin2hex(random_bytes(4));
    if ($sistema) $slug = $orig['slug'];
    else {
        $slug = mv_slugify(mv_texto($in['slug'] ?? '', 80) ?: $titulo);
        if (!mv_slug_valido($slug)) $err[] = 'La dirección solo puede tener minúsculas, números y guiones, y no puede ser una palabra reservada.';
        foreach ($PG['paginas'] as $p) if ($p['slug'] === $slug && $p['id'] !== $id) $err[] = 'Otra página ya usa la dirección «' . $slug . '».';
        foreach ($datos['productos'] as $p) if ($p['slug'] === $slug) $err[] = 'Un producto ya usa la dirección «' . $slug . '».';
        foreach ($datos['categorias'] as $c) if ($c['slug'] === $slug) $err[] = 'Una categoría ya usa la dirección «' . $slug . '».';
    }
    $ctx = mv_ctx_validacion($datos);
    $secs = [];
    foreach (array_slice((array) ($in['secciones'] ?? []), 0, 60) as $s) if (is_array($s) && ($n = mv_normalizar_seccion($s, $ctx))) $secs[] = $n;
    $vistos = [];
    foreach ($secs as &$s) { while (isset($vistos[$s['id']])) $s['id'] = 's-' . bin2hex(random_bytes(4)); $vistos[$s['id']] = 1; }
    unset($s);
    $pl = (array) ($in['plantilla'] ?? []);
    $seo = (array) ($in['seo'] ?? []);
    $p = [
        'id' => $id, 'slug' => $slug, 'titulo' => $titulo,
        'tipo' => ($in['tipo'] ?? '') === 'landing' ? 'landing' : 'pagina',
        'sistema' => $sistema,
        'estado' => $sistema ? 'publicado' : (($in['estado'] ?? '') === 'publicado' ? 'publicado' : 'borrador'),
        'plantilla' => ['cabecera' => in_array($pl['cabecera'] ?? '', ['normal', 'simple'], true) ? $pl['cabecera'] : 'normal',
                        'pie' => in_array($pl['pie'] ?? '', ['normal', 'simple'], true) ? $pl['pie'] : 'normal'],
        'seo' => ['titulo' => mv_texto($seo['titulo'] ?? '', 90), 'descripcion' => mv_texto($seo['descripcion'] ?? '', 300),
                  'imagen' => function_exists('img_ok') ? img_ok($seo['imagen'] ?? null) : null, 'noindex' => !empty($seo['noindex'])],
        'secciones' => $secs,
        'actualizado' => date('c'),
    ];
    return [$p, $err];
}
function mv_normalizar_menu(array $in, array $PG): array {
    $ids = array_map(fn($p) => $p['id'], $PG['paginas']);
    $out = []; $hayProd = false;
    foreach (array_slice($in, 0, 40) as $m) {
        if (!is_array($m)) continue;
        $tipo = in_array($m['tipo'] ?? '', ['pagina', 'productos', 'enlace'], true) ? $m['tipo'] : '';
        if ($tipo === '' || ($tipo === 'productos' && $hayProd)) continue;
        $it = ['id' => preg_match('/^[a-z0-9-]{2,20}$/', (string) ($m['id'] ?? '')) ? $m['id'] : 'm-' . bin2hex(random_bytes(3)),
               'tipo' => $tipo, 'etiqueta' => mv_texto($m['etiqueta'] ?? '', 40), 'nivel' => (int) !empty($m['nivel']) && $out ? 1 : 0, 'visible' => !isset($m['visible']) || !empty($m['visible'])];
        if ($tipo === 'pagina') { if (!in_array($m['ref'] ?? '', $ids, true)) continue; $it['ref'] = $m['ref']; }
        if ($tipo === 'enlace') { $it['url'] = mv_enlace_ok($m['url'] ?? ''); if ($it['url'] === '') continue; $it['nueva'] = !empty($m['nueva']); }
        if ($tipo === 'productos') { $hayProd = true; $it['nivel'] = 0; }
        if ($it['etiqueta'] === '') $it['etiqueta'] = 'Enlace';
        $out[] = $it;
    }
    return $out;
}

// ---------------------------------------------------------------- datos
function mv_paginas(): array {
    $d = json_decode((string) @file_get_contents(MV_PAGINAS), true);
    if (!is_array($d) || empty($d['paginas'])) $d = mv_paginas_iniciales();
    return $d;
}
function mv_guardar_paginas(array $d): bool {
    if (!is_dir(dirname(MV_PAGINAS))) @mkdir(dirname(MV_PAGINAS), 0750, true);
    if (is_file(MV_PAGINAS)) {
        $bk = dirname(MV_PAGINAS) . '/respaldos';
        if (!is_dir($bk)) @mkdir($bk, 0750, true);
        @copy(MV_PAGINAS, $bk . '/paginas-' . date('Ymd-His') . '.json');
        $todos = glob($bk . '/paginas-*.json') ?: []; sort($todos);
        foreach (array_slice($todos, 0, max(0, count($todos) - 30)) as $v) @unlink($v);
    }
    $tmp = MV_PAGINAS . '.tmp';
    if (@file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
    return @rename($tmp, MV_PAGINAS);
}
function mv_pagina_por_id(array $PG, string $id): ?array { foreach ($PG['paginas'] as $p) if ($p['id'] === $id) return $p; return null; }

// ---------------------------------------------------------------- dibujo de secciones
/** Convierte enlaces e imágenes del propio sitio (/algo/) en relativos, para que funcionen en cualquier carpeta. */
function mv_rel(string $u, string $pre): string {
    if ($u === '/' ) return $pre !== '' ? $pre : './';
    if (strpos($u, '/') === 0 && strpos($u, '//') !== 0) return $pre . substr($u, 1);
    return $u;
}
function mv_a(string $u, string $pre): string {
    $u = mv_rel($u, $pre);
    $ext = preg_match('#^https?://#', $u) && !preg_match('#^https?://(www\.)?mivaso\.cl(/|$)#', $u);
    return 'href="' . e($u) . '"' . ($ext ? ' target="_blank" rel="noopener"' : '');
}
function mv_img_sec(?array $i, string $pre, string $tam = 'medio', array $op = []): string {
    if (!$i || empty($i['src'])) return '';
    $src = mv_rel((string) ($i[$tam] ?? '') ?: $i['src'], $pre);
    $a = '<img src="' . e($src) . '" alt="' . e($op['alt'] ?? ($i['alt'] ?? '')) . '"';
    if (!empty($op['clase'])) $a .= ' class="' . $op['clase'] . '"';
    if (!empty($op['prioridad'])) $a .= ' fetchpriority="high"'; else $a .= ' loading="lazy" decoding="async"';
    if (!empty($op['ancho'])) $a .= ' width="' . (int) $op['ancho'] . '" height="' . (int) $op['alto'] . '"';
    return $a . '>';
}
/** Primer título de la página = h1 (importante para Google), los demás h2. */
function mv_tit(array &$C, string $t, string $cls = 'h2', string $extra = ''): string {
    if (trim($t) === '') return '';
    $tag = $C['h1'] ? 'h2' : 'h1'; $C['h1'] = true;
    return "<$tag class=\"$cls\"$extra>" . e($t) . "</$tag>";
}
function mv_boton_sec(array $s, string $pre, string $pref = 'boton', string $envoltura = 'p'): string {
    $t = trim((string) ($s[$pref . '_texto'] ?? '')); $u = (string) ($s[$pref . '_url'] ?? '');
    if ($t === '' || $u === '') return '';
    $cls = ['relleno' => 'btn', 'borde' => 'btn btn--borde', 'claro' => 'btn btn--claro'][$s[$pref . '_estilo'] ?? 'relleno'] ?? 'btn';
    $a = '<a class="' . $cls . '" ' . mv_a($u, $pre) . '>' . e($t) . '</a>';
    return $envoltura ? "<$envoltura class=\"centro sec__boton\">$a</$envoltura>" : $a;
}
function mv_cabecera_sec(array &$C, array $s, string $clsIntro = 'lead'): string {
    return mv_tit($C, (string) ($s['titulo'] ?? '')) . (trim((string) ($s['intro'] ?? '')) !== '' ? '<div class="' . $clsIntro . '">' . md((string) $s['intro']) . '</div>' : '');
}

function mv_seccion_html(array $s, array &$C, int $idx): string {
    $pre = $C['pre']; $tipo = $s['tipo'];
    $anim = ($s['animacion'] ?? 'ninguna') !== 'ninguna' && $tipo !== 'banner' ? ' anima anima--' . $s['animacion'] : '';
    $fondo = $s['fondo'] ?? 'blanco';
    $clases = 'sec sec--' . str_replace('_', '-', $tipo) . ' fondo--' . $fondo . ' esp--' . ($s['espacio'] ?? 'normal') . $anim;
    $estilo = '';
    if ($fondo === 'imagen' && !empty($s['fondo_img']['src'])) $estilo = ' style="--fondo-img:url(\'' . e(mv_rel($s['fondo_img']['src'], $pre)) . '\')"';
    $ancla = !empty($s['ancla']) ? ' id="' . e($s['ancla']) . '"' : '';
    $data = ' data-sec="' . e($s['id']) . '"';
    $in = mv_seccion_interior($s, $C, $idx);
    if ($in === null) return '';
    if ($tipo === 'banner') return "<section class=\"sec sec--banner\"$ancla$data aria-label=\"" . e($s['slides'][0]['titulo'] ?? 'Destacado') . "\">$in</section>\n";
    return "<section class=\"$clases\"$ancla$data$estilo>$in</section>\n";
}

function mv_seccion_interior(array $s, array &$C, int $idx): ?string {
    $pre = $C['pre']; $S = $C['S'];
    switch ($s['tipo']) {
        case 'banner': {
            $slides = array_values(array_filter($s['slides'], fn($x) => !empty($x['img']['src'])));
            if (!$slides) return null;
            $n = count($slides); $h = '';
            foreach ($slides as $i => $x) {
                $img = mv_img_sec($x['img'], $pre, 'src', ['alt' => $x['alt'] ?: ($x['titulo'] ?: ''), 'clase' => 'bnr__img', 'prioridad' => $idx === 0 && $i === 0]);
                $pic = !empty($x['img_movil']['src']) ? '<picture><source media="(max-width: 640px)" srcset="' . e(mv_rel($x['img_movil']['src'], $pre)) . '">' . $img . '</picture>' : $img;
                $txt = '';
                if ($x['titulo'] !== '' || $x['texto'] !== '' || $x['boton_texto'] !== '') {
                    $hh = $x['titulo'] !== '' ? mv_tit($C, $x['titulo'], 'bnr__h') : '';
                    $bt = $x['boton_texto'] !== '' && $x['boton_url'] !== '' ? '<a class="btn" ' . mv_a($x['boton_url'], $pre) . '>' . e($x['boton_texto']) . '</a>' : '';
                    $txt = '<div class="bnr__capa bnr__capa--' . $x['posicion'] . ' oscuro--' . $s['oscurecer'] . '"><div class="wrap"><div class="bnr__txt">' . $hh . ($x['texto'] !== '' ? '<p>' . e($x['texto']) . '</p>' : '') . $bt . '</div></div></div>';
                }
                $h .= '<div class="bnr__slide' . ($i === 0 ? ' activa' : '') . '"' . ($n > 1 ? ' role="group" aria-roledescription="diapositiva" aria-label="' . ($i + 1) . ' de ' . $n . '"' : '') . ($i ? ' aria-hidden="true"' : '') . '>' . $pic . $txt . '</div>';
            }
            $ctrl = '';
            if ($n > 1) {
                $pts = '';
                for ($i = 0; $i < $n; $i++) $pts .= '<button type="button" class="bnr__punto"' . ($i === 0 ? ' aria-current="true"' : '') . ' aria-label="Ir a la diapositiva ' . ($i + 1) . '"></button>';
                $ctrl = '<button type="button" class="bnr__flecha bnr__flecha--ant" aria-label="Anterior"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.4"/></svg></button>'
                      . '<button type="button" class="bnr__flecha bnr__flecha--sig" aria-label="Siguiente"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4"/></svg></button>'
                      . '<div class="bnr__puntos">' . $pts . '<button type="button" class="bnr__pausa" aria-label="Pausar el carrusel" aria-pressed="false"><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M7 5h3v14H7zM14 5h3v14h-3z" fill="currentColor"/></svg></button></div>';
            }
            return '<div class="bnr bnr--' . $s['alto'] . ' bnr--' . $s['efecto'] . '"' . ($n > 1 ? ' data-banner data-intervalo="' . ((int) $s['intervalo'] * 1000) . '" aria-roledescription="carrusel"' : '') . '><div class="bnr__pista">' . $h . '</div>' . $ctrl . '</div>';
        }
        case 'texto_imagen': {
            $img = mv_img_sec($s['img'], $pre, 'src', ['alt' => $s['img']['alt'] ?? '']);
            $cls = 'dos' . ($s['posicion'] === 'der' ? ' dos--inv' : '');
            $txt = mv_tit($C, $s['titulo']) . ($s['intro'] !== '' ? '<div class="lead">' . md($s['intro']) . '</div>' : '') . md($s['texto']);
            $bt = mv_boton_sec($s, $pre, 'boton', '');
            return "<div class=\"wrap $cls\">" . ($img ? '<div class="dos__img' . ($s['tam_img'] === 'chica' ? ' dos__img--chica' : '') . '">' . $img . '</div>' : '') . '<div class="dos__txt">' . $txt . $bt . '</div></div>';
        }
        case 'texto': {
            $ancho = $s['ancho'] === 'angosto' ? ' angosto' : '';
            $al = $s['alinear'] === 'centro' ? ' centro' : '';
            return "<div class=\"wrap$ancho$al\">" . mv_cabecera_sec($C, $s) . '<div class="texto-rico">' . md($s['texto']) . '</div>' . mv_boton_sec($s, $pre) . '</div>';
        }
        case 'iconos': {
            $its = ''; $cols = (int) $s['columnas'];
            foreach ($s['items'] as $it) {
                $img = mv_img_sec($it['img'], $pre, 'mini');
                $t = $it['titulo'] !== '' ? e($it['titulo']) : '';
                if ($it['url'] !== '' && $t !== '') $t = '<a ' . mv_a($it['url'], $pre) . '>' . $t . '</a>';
                if ($s['estilo'] === 'pilar') $its .= '<li class="pilar">' . $img . '<span>' . $t . '</span>' . ($it['texto'] !== '' ? '<small>' . e($it['texto']) . '</small>' : '') . '</li>';
                elseif ($s['estilo'] === 'fila') $its .= '<li class="fila">' . $img . '<div>' . ($t !== '' ? "<h3>$t</h3>" : '') . md($it['texto']) . '</div></li>';
                else $its .= '<li class="icard">' . $img . ($t !== '' ? "<h3>$t</h3>" : '') . md($it['texto']) . '</li>';
            }
            $ul = ['pilar' => 'pilares', 'fila' => 'filas', 'tarjeta' => 'icards'][$s['estilo']];
            $ancho = $s['estilo'] === 'fila' && $cols <= 1 ? ' angosto' : '';
            $cab = mv_cabecera_sec($C, $s, $s['estilo'] === 'tarjeta' ? 'lead angosto-izq' : 'lead');
            return "<div class=\"wrap\">$cab<ul class=\"$ul cols-$cols\" data-escalonar>$its</ul>" . mv_boton_sec($s, $pre) . '</div>';
        }
        case 'tarjetas': {
            $its = '';
            foreach ($s['items'] as $it) {
                $mas = $it['url'] !== '' ? '<a class="mas" ' . mv_a($it['url'], $pre) . '>' . e($it['boton_texto'] ?: 'Saber más') . '</a>' : '';
                $its .= '<article class="bloque">' . mv_img_sec($it['img'], $pre, 'medio', ['clase' => 'bloque__img']) . '<div class="bloque__txt">' . ($it['titulo'] !== '' ? '<h3>' . e($it['titulo']) . '</h3>' : '')
                      . ($it['etiqueta'] !== '' ? '<p class="etiqueta">' . e($it['etiqueta']) . '</p>' : '') . md($it['texto']) . $mas . '</div></article>';
            }
            return '<div class="wrap">' . mv_cabecera_sec($C, $s) . '<div class="bloques cols-' . (int) $s['columnas'] . '" data-escalonar>' . $its . '</div>' . mv_boton_sec($s, $pre) . '</div>';
        }
        case 'desplegables': {
            $its = ''; $n = 0;
            foreach ($s['items'] as $i => $it) {
                if ($it['titulo'] === '' && $it['etiqueta'] === '' && empty($it['img']['src'])) continue;
                $abierta = $s['abrir'] === 'abiertas' || ($s['abrir'] === 'primera' && $n === 0); $n++;
                $mas = $it['url'] !== '' ? '<a class="desp__mas" ' . mv_a($it['url'], $pre) . '>' . e($it['boton_texto'] ?: 'Saber más…') . '</a>' : '';
                $its .= '<div class="desp__col">' . (!empty($it['img']['src']) ? '<div class="desp__img">' . mv_img_sec($it['img'], $pre, 'medio') . '</div>' : '')
                      . ($it['titulo'] !== '' ? '<h3 class="desp__h">' . e($it['titulo']) . '</h3>' : '')
                      . '<details class="desp__det"' . ($abierta ? ' open' : '') . '><summary>' . e($it['etiqueta'] ?: ($it['titulo'] ?: 'Ver más')) . '</summary>'
                      . '<div class="desp__txt">' . md($it['texto']) . $mas . '</div></details></div>';
            }
            if ($its === '') return null;
            return '<div class="wrap">' . mv_cabecera_sec($C, $s) . '<div class="desp cols-' . (int) $s['columnas'] . '"' . ($s['una'] ? ' data-uno' : '') . ' data-escalonar>' . $its . '</div>' . mv_boton_sec($s, $pre) . '</div>';
        }
        case 'productos': {
            $d = $S['datos']; $t = ''; $max = (int) $s['maximo'];
            if ($s['modo'] === 'categorias') {
                foreach ($d['categorias'] as $c) if (mv_publicados($d, $c['id']))
                    $t .= '<a class="prod prod--cat" href="' . L($pre, $c['slug']) . '"><span class="prod__img">' . img($c['img'], $c['titulo'], 300, 300) . '</span><h3 class="prod__nombre">' . e($c['titulo']) . '</h3></a>';
            } else {
                $ps = mv_publicados($d);
                if ($s['modo'] === 'destacados') $ps = array_values(array_filter($ps, fn($p) => !empty($p['destacado']['activo'])));
                if ($s['modo'] === 'categoria') $ps = array_values(array_filter($ps, fn($p) => $p['categoria'] === $s['categoria']));
                if ($s['modo'] === 'elegidos') { $por = []; foreach ($ps as $p) $por[$p['slug']] = $p; $ps = array_values(array_filter(array_map(fn($x) => $por[$x] ?? null, $s['elegidos']))); }
                foreach (array_slice($ps, 0, $max) as $p) $t .= tarjeta_prod($pre, $p, $s['modo'] === 'destacados' ? (($p['destacado']['img'] ?? '') ?: null) : null);
            }
            if ($t === '') { if (empty($C['vista'])) return null; $t = '<p class="ayuda-vacia">Aún no hay productos para mostrar aquí.</p>'; }
            return '<div class="wrap">' . mv_cabecera_sec($C, $s) . '<div class="grilla grilla--' . (int) $s['columnas'] . '" data-escalonar>' . $t . '</div>' . mv_boton_sec($s, $pre) . '</div>';
        }
        case 'logos': {
            $its = array_values(array_filter($s['items'], fn($x) => !empty($x['img']['src'])));
            if (!$its) return null;
            $cab = '<div class="wrap">' . mv_cabecera_sec($C, $s) . '</div>';
            if ($s['estilo'] === 'grilla') {
                $li = ''; foreach ($its as $x) $li .= '<li>' . mv_img_sec($x['img'], $pre, 'medio', ['alt' => $x['nombre']]) . '</li>';
                return '<div class="wrap">' . mv_cabecera_sec($C, $s) . '<ul class="marcas-grilla">' . $li . '</ul>' . mv_boton_sec($s, $pre) . '</div>';
            }
            $a = $b = '';
            foreach ($its as $x) { $a .= mv_img_sec($x['img'], $pre, 'medio', ['alt' => $x['nombre'], 'ancho' => 160, 'alto' => 80]); $b .= mv_img_sec($x['img'], $pre, 'medio', ['alt' => '', 'ancho' => 160, 'alto' => 80]); }
            $dur = max(20, count($its) * 3);
            return $cab . '<div class="marcas"><div class="marcas__pista" style="animation-duration:' . $dur . 's">' . $a . '<span class="marcas__dup" aria-hidden="true">' . $b . '</span></div></div>' . (mv_boton_sec($s, $pre) ? '<div class="wrap">' . mv_boton_sec($s, $pre) . '</div>' : '');
        }
        case 'acordeon': {
            $f = '';
            foreach ($s['items'] as $i => $q) if ($q['pregunta'] !== '')
                $f .= '<details class="faq"' . ($i === 0 && $s['abrir_primero'] ? ' open' : '') . '><summary>' . e($q['pregunta']) . '</summary><div class="faq__r">' . md($q['respuesta']) . '</div></details>';
            return '<div class="wrap' . ($s['ancho'] === 'angosto' ? ' angosto' : '') . '">' . mv_cabecera_sec($C, $s) . '<div class="faqs">' . $f . '</div>' . mv_boton_sec($s, $pre) . '</div>';
        }
        case 'imagen': {
            if (empty($s['img']['src'])) return null;
            $img = mv_img_sec($s['img'], $pre, $s['estilo'] === 'completo' ? 'src' : 'src');
            if ($s['url'] !== '') $img = '<a ' . mv_a($s['url'], $pre) . '>' . $img . '</a>';
            $fig = '<figure class="figura figura--' . $s['estilo'] . '">' . $img . ($s['pie'] !== '' ? '<figcaption>' . e($s['pie']) . '</figcaption>' : '') . '</figure>';
            $t = $s['titulo'] !== '' ? '<div class="wrap">' . mv_tit($C, $s['titulo']) . '</div>' : '';
            return $s['estilo'] === 'completo' ? $t . $fig : '<div class="wrap">' . ($s['titulo'] !== '' ? mv_tit($C, $s['titulo']) : '') . $fig . '</div>';
        }
        case 'galeria': {
            $li = '';
            foreach ($s['items'] as $x) if (!empty($x['img']['src']))
                $li .= '<li><a class="galeria-foto" href="' . e(mv_rel($x['img']['src'], $pre)) . '" data-ampliar>' . mv_img_sec($x['img'], $pre, 'medio', ['alt' => $x['pie']]) . '</a>' . ($x['pie'] !== '' ? '<span>' . e($x['pie']) . '</span>' : '') . '</li>';
            if ($li === '') return null;
            return '<div class="wrap">' . mv_cabecera_sec($C, $s) . '<ul class="galeria-grilla cols-' . (int) $s['columnas'] . '" data-escalonar>' . $li . '</ul></div>';
        }
        case 'video': {
            $vid = preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})~', $s['url'], $m) ? $m[1] : '';
            $v = $vid ? '<div class="video"><iframe src="https://www.youtube-nocookie.com/embed/' . $vid . '" title="' . e($s['titulo'] ?: 'Video') . '" loading="lazy" allow="accelerometer; encrypted-media; picture-in-picture" allowfullscreen></iframe></div>' : '<p class="ayuda-vacia">Pega el enlace de YouTube en el panel.</p>';
            if (!$vid && empty($C['vista'])) return null;
            return '<div class="wrap angosto">' . mv_cabecera_sec($C, $s) . $v . '</div>';
        }
        case 'cta': {
            $b = mv_boton_sec(['boton_texto' => $s['boton_texto'], 'boton_url' => $s['boton_url'], 'boton_estilo' => in_array($s['fondo'], ['verde', 'imagen'], true) ? 'claro' : 'relleno'], $pre, 'boton', '')
               . ' ' . mv_boton_sec(['boton2_texto' => $s['boton2_texto'], 'boton2_url' => $s['boton2_url'], 'boton2_estilo' => 'borde'], $pre, 'boton2', '');
            return '<div class="wrap angosto centro">' . mv_tit($C, $s['titulo'], 'cta__h') . ($s['texto'] !== '' ? '<div class="cta__txt">' . md($s['texto']) . '</div>' : '') . (trim($b) !== '' ? '<p class="cta__botones">' . $b . '</p>' : '') . '</div>';
        }
        case 'contador': {
            $ts = 0;
            if ($s['fecha'] !== '') { try { $ts = (new DateTimeImmutable($s['fecha'], new DateTimeZone('America/Santiago')))->getTimestamp(); } catch (Exception $e) { $ts = 0; } }
            $fechaTxt = $ts ? mv_fecha_larga($ts) : '';
            $u = fn($k, $t) => '<div class="cuenta__u"><span class="cuenta__n" data-' . $k . '>00</span><span class="cuenta__l">' . $t . '</span></div>';
            $caja = $ts ? '<div class="cuenta" data-cuenta="' . ($ts * 1000) . '" data-fin="' . e($s['texto_fin']) . '">' . $u('d', 'días') . $u('h', 'horas') . $u('m', 'minutos') . $u('s', 'segundos') . '</div><p class="cuenta__fecha">' . e($fechaTxt) . '</p>' : '<p class="ayuda-vacia">Elige la fecha del evento en el panel.</p>';
            if (!$ts && empty($C['vista'])) return null;
            return '<div class="wrap centro">' . mv_cabecera_sec($C, $s, 'lead lead--centro') . $caja . mv_boton_sec($s, $pre) . '</div>';
        }
        case 'numeros': {
            $li = '';
            foreach ($s['items'] as $x) {
                $pf = $x['prefijo'] . (preg_match('/\p{L}$/u', $x['prefijo']) ? ' ' : ''); $sf = (preg_match('/^\p{L}/u', $x['sufijo']) ? ' ' : '') . $x['sufijo'];
                $li .= '<li class="cifra"><span class="cifra__n">' . e($pf) . '<span data-contar="' . (int) $x['numero'] . '">' . number_format((int) $x['numero'], 0, ',', '.') . '</span>' . e($sf) . '</span><span class="cifra__t">' . e($x['texto']) . '</span></li>';
            }
            return '<div class="wrap">' . mv_cabecera_sec($C, $s) . '<ul class="cifras cols-' . (int) $s['columnas'] . '" data-escalonar>' . $li . '</ul></div>';
        }
        case 'contacto': {
            $idp = 'fc-' . preg_replace('/[^a-z0-9-]/', '', $s['id']);
            $form = form_contacto($pre, $idp, e($s['etiqueta_nombre'] ?: 'Nombre'));
            $dl = '';
            foreach ($s['datos'] as $x) if ($x['etiqueta'] !== '' || $x['valor'] !== '')
                $dl .= '<li><span>' . e($x['etiqueta']) . '</span>' . ($x['url'] !== '' ? '<a ' . mv_a($x['url'], $pre) . '>' . e($x['valor']) . '</a>' : e($x['valor'])) . '</li>';
            if ($s['estilo'] === 'banda') {
                return '<div class="contacto-banda"><div class="wrap dos dos--contacto"><div>' . mv_tit($C, $s['titulo'], 'contacto-banda__h') . ($s['intro'] !== '' ? md($s['intro']) : '') . md($s['texto'])
                     . ($dl ? '<ul class="datos">' . $dl . '</ul>' : '') . '</div>' . $form . '</div></div>';
            }
            return '<div class="wrap dos dos--contacto-pag"><div>' . mv_tit($C, $s['titulo']) . ($s['intro'] !== '' ? '<div class="lead">' . md($s['intro']) . '</div>' : '') . md($s['texto'])
                 . ($dl ? '<ul class="datos datos--claro">' . $dl . '</ul>' : '') . '</div><div class="caja-form">' . $form . '</div></div>';
        }
        case 'mapa': {
            $ok = preg_match('#^https://(www\.)?google\.[a-z.]+/maps/embed\?[A-Za-z0-9!%._~:/?=&+,;@*()-]+$#', $s['url']);
            $m = $ok ? '<div class="mapa" style="height:' . (int) $s['alto'] . 'px"><iframe src="' . e($s['url']) . '" title="' . e($s['titulo'] ?: 'Mapa') . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>' : '<p class="ayuda-vacia">Pega el enlace para insertar de Google Maps en el panel.</p>';
            if (!$ok && empty($C['vista'])) return null;
            return '<div class="wrap">' . ($s['titulo'] !== '' ? mv_tit($C, $s['titulo']) : '') . $m . '</div>';
        }
        case 'espacio':
            return '<div class="wrap"><div class="espacio espacio--' . $s['alto'] . '">' . ($s['linea'] ? '<hr>' : '') . '</div></div>';
    }
    return null;
}
function mv_fecha_larga(int $ts): string {
    $d = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('America/Santiago'));
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return ucfirst($dias[(int) $d->format('w')]) . ' ' . $d->format('j') . ' de ' . $meses[(int) $d->format('n') - 1] . ' de ' . $d->format('Y') . ', ' . $d->format('H:i') . ' hrs';
}

/** HTML completo de una página armada por secciones. */
function p_dinamica(array $S, array $pg, bool $vista = false): string {
    $pre = $pg['slug'] === '' ? '' : '../';
    $C = ['pre' => $pre, 'h1' => false, 'S' => $S, 'vista' => $vista];
    $cuerpo = "\n"; $i = 0;
    foreach ($pg['secciones'] as $s) {
        if (!empty($s['oculto']) && !$vista) continue;
        $html = mv_seccion_html($s, $C, $i);
        if ($html === '' && $vista) $html = '<section class="sec sec--vacia" data-sec="' . e($s['id']) . '"><div class="wrap"><p class="ayuda-vacia">Sección «' . e(mv_esquema()[$s['tipo']]['n']) . '» sin contenido todavía.</p></div></section>' . "\n";
        if (!empty($s['oculto']) && $vista) $html = preg_replace('/^<section class="/', '<section class="sec--oculta ', $html);
        $cuerpo .= $html; $i++;
    }
    if (!$pg['secciones']) $cuerpo .= '<section class="seccion"><div class="wrap centro"><p class="ayuda-vacia">Página vacía. Agrega secciones desde el panel.</p></div></section>';
    $desc = $pg['seo']['descripcion'] ?: mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($cuerpo))), 0, 155);
    $titulo = $pg['seo']['titulo'] ?: ($pg['slug'] === '' ? 'Mi Vaso - Fábrica de vasos y copas personalizadas reutilizables' : $pg['titulo'] . ' - MiVaso.cl');
    $og = $pg['seo']['imagen']['src'] ?? '';
    if ($og === '') foreach ($pg['secciones'] as $s) { if (!empty($s['slides'][0]['img']['src'])) { $og = $s['slides'][0]['img']['src']; break; } if (!empty($s['img']['src'])) { $og = $s['img']['src']; break; } }
    $ld = null;
    if ($pg['slug'] === '') $ld = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Mi Vaso', 'url' => MV_DOM . '/', 'logo' => MV_U . '2025/11/Logo-miVaso.png',
           'email' => 'contacto@mivaso.cl', 'telephone' => '+56225831902', 'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'CL'],
           'sameAs' => ['https://www.facebook.com/mivasoecologico', 'https://www.instagram.com/mivasoecologico/']];
    return pagina($S, $pg['slug'], $titulo, $desc, $cuerpo, $og, $pg['slug'], '', $ld,
        ['cabecera' => $pg['plantilla']['cabecera'], 'pie' => $pg['plantilla']['pie'], 'noindex' => !empty($pg['seo']['noindex'])]);
}

// ---------------------------------------------------------------- menú
/** Lista de elementos del menú listos para dibujar: [etiqueta, href, activo-slug, hijos[], nueva]. */
function mv_menu_items(array $S, string $pre): array {
    $PG = $S['paginas']; $out = [];
    foreach ($PG['menu'] as $m) {
        if (empty($m['visible'])) continue;
        $it = null;
        if ($m['tipo'] === 'pagina') {
            $p = mv_pagina_por_id($PG, $m['ref']);
            if (!$p || $p['estado'] !== 'publicado') continue;
            $it = ['t' => $m['etiqueta'], 'href' => L($pre, $p['slug']), 'slug' => $p['slug'], 'hijos' => [], 'nueva' => false];
        } elseif ($m['tipo'] === 'productos') {
            $hijos = [];
            foreach ($S['datos']['categorias'] as $c) $hijos[] = ['t' => $c['menu'], 'href' => L($pre, $c['slug']), 'slug' => $c['slug'], 'hijos' => [], 'nueva' => false];
            $it = ['t' => $m['etiqueta'], 'href' => L($pre, 'productos'), 'slug' => 'productos', 'hijos' => $hijos, 'nueva' => false, 'productos' => true];
        } else {
            $it = ['t' => $m['etiqueta'], 'href' => mv_rel($m['url'], $pre), 'slug' => null, 'hijos' => [], 'nueva' => !empty($m['nueva'])];
        }
        if ($m['nivel'] && $out) $out[count($out) - 1]['hijos'][] = $it; else $out[] = $it;
    }
    return $out;
}
function mv_etiqueta_pie(string $t): string {
    if ($t !== mb_strtoupper($t)) return $t;
    $l = mb_strtolower($t);
    return preg_replace_callback('/^(\W*)(\w)/u', fn($m) => $m[1] . mb_strtoupper($m[2]), $l);
}

// ---------------------------------------------------------------- contenido inicial (lo que tenía el sitio)
function mv_paginas_iniciales(): array {
    $U = MV_U;
    $im = fn(string $f, string $alt = '') => ['src' => $U . $f, 'medio' => $U . $f, 'mini' => $U . $f, 'alt' => $alt];
    $base = fn(string $tipo, string $id, array $d) => array_merge(['id' => $id, 'tipo' => $tipo, 'oculto' => false, 'fondo' => 'blanco', 'fondo_img' => null, 'espacio' => 'normal', 'animacion' => 'subir', 'ancla' => ''], $d);
    $sl = fn(array $img, string $alt) => ['img' => $img, 'img_movil' => null, 'titulo' => '', 'texto' => '', 'boton_texto' => '', 'boton_url' => '', 'posicion' => 'izq', 'alt' => $alt];
    $bt = fn(string $t = '', string $u = '', string $e = 'relleno') => ['boton_texto' => $t, 'boton_url' => $u, 'boton_estilo' => $e];
    $marcas = array_map(fn($m) => ['img' => $im('2025/12/' . $m[1], $m[0]), 'nombre' => $m[0]], mv_marcas());
    $faq = array_map(fn($q) => ['pregunta' => $q[0], 'respuesta' => $q[1]], [
        ['¿Cuánto es el mínimo de vasos?', 'Fabricamos vasos personalizados desde 500 unidades en adelante.'],
        ['¿Cuántos diseños puedo hacer en un mismo pedido?', 'El diseño debe ser único para la totalidad de los vasos. Por ejemplo: si haces mil vasos, todos deben tener el mismo diseño.'],
        ['¿Se pueden mezclar los vasos en un mismo pedido?', 'Sí, puedes mezclar colores en un mismo pedido. Por ejemplo, en un pedido de mil vasos puedes escoger doscientos vasos rojos, doscientos verdes, y así hasta completar el pedido. Recuerda consultar por disponibilidad de nuestro stock.'],
        ['¿Se pueden usar los vasos para bebidas calientes?', 'Sí, nuestros vasos son de Polipropileno (PP5) y su punto de fusión es de 160°, por tanto, pueden soportar temperaturas elevadas sin sufrir deformaciones ni alterar sabores en bebidas calientes.'],
        ['¿Se puede imprimir el vaso completo?', 'Sí, se puede imprimir el vaso casi completo, ya que hay un margen de 1,5 cm en la parte superior e inferior del vaso que no es imprimible, además de un margen de 1 cm desde el inicio del diseño hasta el final. **[Ver guía de impresión](' . MV_GUIA . ')**'],
        ['¿Cuánto tardan en hacer los vasos?', 'Para pedidos entre quinientos y cinco mil unidades, el plazo máximo es de cinco días hábiles. Si los necesitas antes, avísanos. En el caso de pedidos desde diez mil vasos en adelante, contáctanos para coordinar fechas de producción.'],
        ['¿Se va la tinta de los vasos?', 'No se va la tinta. Usamos tintas adecuadas y de calidad para que el recuerdo sea inolvidable. Somos humanos: si se va la tinta de algunos vasos por algún error de nuestro taller, avísanos y lo resolvemos.'],
        ['Si me sobraron vasos del evento, ¿me los pueden reciclar ustedes?', 'Encantados de hacerlo. Contáctanos y coordinemos la entrega en nuestras plantas para el reciclaje.']]);
    $datosBanda = [['etiqueta' => 'WhatsApp', 'valor' => '+56 9 5043 5981', 'url' => MV_WA], ['etiqueta' => 'Teléfono', 'valor' => '+56 2 2583 1902', 'url' => 'tel:+56225831902'], ['etiqueta' => 'Correo', 'valor' => 'contacto@mivaso.cl', 'url' => 'mailto:contacto@mivaso.cl']];
    $ic = fn(string $f, string $t, string $d = '', string $u = '') => ['img' => $im($f), 'titulo' => $t, 'texto' => $d, 'url' => $u];
    $tar = fn(string $f, string $t, string $et, string $tx, string $u) => ['img' => $im($f), 'titulo' => $t, 'etiqueta' => mb_strtoupper($et), 'texto' => $tx, 'url' => $u, 'boton_texto' => 'Saber más…'];
    $pag = fn(string $id, string $slug, string $titulo, array $secs, string $seoT, string $seoD, ?array $og = null) => ['id' => $id, 'slug' => $slug, 'titulo' => $titulo, 'tipo' => 'pagina', 'sistema' => true, 'estado' => 'publicado',
        'plantilla' => ['cabecera' => 'normal', 'pie' => 'normal'], 'seo' => ['titulo' => $seoT, 'descripcion' => $seoD, 'imagen' => $og, 'noindex' => false], 'secciones' => $secs, 'actualizado' => date('c')];

    $inicio = $pag('inicio', '', 'Inicio', [
        $base('banner', 's-ini-banner', ['slides' => [$sl($im('2025/11/Color-scaled.jpg'), 'Vasos reutilizables Mi Vaso de distintos colores')], 'alto' => 'grande', 'efecto' => 'zoom', 'intervalo' => 6, 'oscurecer' => 'suave', 'animacion' => 'ninguna']),
        $base('texto_imagen', 's-ini-circular', array_merge(['img' => $im('2025/11/MOSAICO-30-1024x1001.png', 'Mosaico de vasos personalizados Mi Vaso'), 'posicion' => 'izq', 'tam_img' => 'normal', 'titulo' => 'En Mi Vaso somos circulares',
            'intro' => 'Somos pioneros en economía circular para eventos masivos en Chile. Desde 2015 impulsamos la reutilización de vasos y transformamos materiales recuperados en nuevos productos. Ofrecemos soluciones sostenibles que cierran completamente el ciclo de vida del plástico.', 'texto' => ''], $bt('Cotiza aquí', '/cotizador/'))),
        $base('iconos', 's-ini-pilares', array_merge(['titulo' => '', 'intro' => '', 'estilo' => 'pilar', 'columnas' => 4, 'espacio' => 'unir', 'items' => [
            $ic('2025/11/MOSAICO_Asesoria-y-diseno-copia-300x298.png', 'Economía circular aplicada a eventos masivos'), $ic('2025/11/MOSAICO_Asesoria-y-diseno-copia-2.png', 'Reutilización de vasos como alternativa real'),
            $ic('2025/12/ICONO-PROCESO-PROPIO.png', 'Proceso propio de transformación de materiales'), $ic('2025/11/MOSAICO_Asesoria-y-diseno-copia-4.png', 'Compromiso con reducir el impacto ambiental')]], $bt())),
        $base('productos', 's-ini-destacados', array_merge(['titulo' => 'Productos destacados', 'intro' => '', 'modo' => 'destacados', 'categoria' => 'vasos', 'elegidos' => [], 'maximo' => 8, 'columnas' => 4, 'fondo' => 'claro'], $bt('Ver todos los productos', '/productos/', 'borde'))),
        $base('desplegables', 's-ini-porque', array_merge(['titulo' => '¿Por qué surge Mi Vaso?', 'intro' => '', 'columnas' => 4, 'abrir' => 'cerradas', 'una' => false, 'items' => [
            $tar('2025/12/MOSAICO_Problema.png', '¿Cuál es el problema?', 'Vasos desechables', "En un evento masivo, casi el 80% de los residuos son vasos desechables.\n\n1. Residuos plásticos\n2. Reciclaje ineficiente\n3. Desperdicio de recursos", '/nosotros/#elproblema'),
            $tar('2025/12/MOSAICO_La-solucion.png', '¿Cuál es la solución?', 'Copas o vasos reutilizables', "Nuestros vasos reutilizables pueden reemplazar cientos de vasos desechables.\n\n1. Ahorro económico: materiales sostenibles y resistentes.\n2. Conciencia ecológica y marketing sustentable.\n3. Fabricación nacional.\n4. Cumplimos con la Ley N.º 21.368, que regula los plásticos de un solo uso.", '/nosotros/#Nuestra_solucion'),
            $tar('2025/12/MOSAICO_Nuestros-Clientes.png', '¿Para quién?', 'Marcas / personas', "1. Festivales, eventos masivos y corporativos (gastronómicos o culturales).\n2. Empresas, casinos, restaurantes, fast food, cines.\n3. Discotecas y bares nocturnos.\n4. Marcas de distribución de bebidas y licores.\n5. Agencias de publicidad (BTL).", '/econimia-circular/#quienes_usan_nuestro_producto'),
            $tar('2025/12/MOSAICO_Economia-Circular.png', '¿Cómo funciona?', 'Economía circular', "En Mi Vaso te acompañamos en todo el proceso: elegimos, diseñamos, fabricamos y reciclamos tus vasos reutilizables, creando impacto real gracias a la economía circular.\n\nDescubre cómo transformamos cada evento en una acción sustentable.", '/nosotros/#economia_circuilar')]], $bt())),
        $base('logos', 's-ini-marcas', array_merge(['titulo' => 'Ellos confían en nosotros', 'intro' => '', 'estilo' => 'carrusel', 'items' => $marcas, 'fondo' => 'claro', 'animacion' => 'aparecer'], $bt())),
        $base('acordeon', 's-ini-faq', array_merge(['titulo' => 'Todo lo que necesitas saber', 'intro' => '', 'items' => $faq, 'abrir_primero' => false, 'ancho' => 'angosto'], $bt())),
        $base('contacto', 's-ini-contacto', ['estilo' => 'banda', 'titulo' => 'Contáctanos', 'intro' => '', 'texto' => 'Completa el formulario y nos pondremos en contacto contigo a la brevedad.', 'datos' => $datosBanda, 'etiqueta_nombre' => 'Nombre', 'ancla' => 'contacto', 'espacio' => 'ninguno', 'animacion' => 'ninguna']),
    ], '', 'Pioneros en economía circular para eventos masivos en Chile desde 2015. Vasos y copas reutilizables personalizados desde 500 unidades.');

    $nosotros = $pag('nosotros', 'nosotros', 'Nosotros', [
        $base('banner', 's-nos-banner', ['slides' => [$sl($im('2025/12/banner3.png'), 'Equipo y producción de Mi Vaso')], 'alto' => 'medio', 'efecto' => 'zoom', 'intervalo' => 6, 'oscurecer' => 'suave', 'animacion' => 'ninguna']),
        $base('texto_imagen', 's-nos-intro', array_merge(['img' => $im('2025/12/somos-circulares.png', 'Somos circulares'), 'posicion' => 'izq', 'tam_img' => 'chica', 'titulo' => 'Nosotros',
            'intro' => 'Somos un equipo que se formó con la inquietud de tener una solución de consumo en eventos masivos. Desde 2015, gracias a un Capital Semilla CORFO, hemos innovado en el concepto de economía circular en el mundo de los eventos masivos en Chile, promoviendo la reutilización de vasos plásticos como una alternativa efectiva y real a los vasos de un solo uso.',
            'texto' => 'Nuestra filosofía está basada en la economía circular: damos una nueva vida a los materiales recuperados en nuestra propia planta de producción, creando productos nuevos como el portavaso, fabricado a partir de los vasos que ya no pueden ser reutilizados, cerrando así el ciclo de vida de nuestros productos.'], $bt())),
        $base('imagen', 's-nos-ciclo', ['titulo' => 'Economía circular', 'img' => $im('2025/11/infografia-economia-circular.png', 'Infografía del ciclo de economía circular de Mi Vaso: elegir, diseñar, fabricar, usar, recuperar y reciclar'), 'estilo' => 'caja', 'pie' => '', 'url' => '', 'fondo' => 'claro', 'ancla' => 'economia_circuilar']),
        $base('banner', 's-nos-foto', ['slides' => [$sl($im('2025/11/foto-2-nosotros-scaled.png'), 'Vasos reutilizables en un evento')], 'alto' => 'chico', 'efecto' => 'zoom', 'intervalo' => 6, 'oscurecer' => 'suave', 'animacion' => 'ninguna']),
        $base('iconos', 's-nos-problema', array_merge(['titulo' => '¿Cuál es el problema? ¡Los vasos desechables!', 'intro' => 'Los vasos desechables, o de plástico de un solo uso, pueden parecer inofensivos, pero su impacto ambiental es enorme. Solo en un evento masivo casi el 80% de los residuos son vasos desechables.', 'estilo' => 'tarjeta', 'columnas' => 3, 'ancla' => 'elproblema', 'items' => [
            $ic('2025/11/icono_Residuos-palsticos.png', 'Residuos plásticos', 'La mayoría terminan en vertederos o en el océano, donde tardan cientos de años en degradarse. En el año 2050 habrá más plástico que peces en los océanos.'),
            $ic('2025/11/icono_reciclaje-ineficiente.png', 'Reciclaje ineficiente', 'Muchos vasos, por ejemplo los de polipapel (vaso de café), tienen una capa de plástico que dificulta su reciclaje, lo que significa que la mayoría acaba como residuo.'),
            $ic('2025/12/MOSAICO_reciclaje-ineficiente-copia.png', 'Desperdicio de recursos', 'Se consumen grandes cantidades de agua y energía para producir vasos que se usan solo unos minutos.')]], $bt())),
        $base('iconos', 's-nos-solucion', array_merge(['titulo' => 'Nuestra solución: copas o vasos reutilizables', 'intro' => '', 'estilo' => 'tarjeta', 'columnas' => 3, 'fondo' => 'claro', 'ancla' => 'Nuestra_solucion', 'items' => [
            $ic('2025/11/iconos_REDUCCION-DE-RESIDUOS.png', 'Reducción de residuos plásticos', 'Un solo vaso reutilizable puede reemplazar cientos de vasos desechables.'),
            $ic('2025/11/iconos_AHORRO-ECONOMICO.png', 'Ahorro económico', 'Aunque la inversión inicial es mayor, a la larga ahorras dinero al no comprar vasos desechables constantemente.'),
            $ic('2025/11/iconos_MATERIALES-SOSTENIBLES.png', 'Materiales sostenibles y resistentes', 'Nuestros vasos, fabricados en Chile en Polipropileno (PP5), son seguros, duraderos y más resistentes que los vasos desechables.'),
            $ic('2025/11/iconos_CONCIENCIA-ECOLOGICA.png', 'Conciencia ecológica', 'El uso de vasos reutilizables demuestra compromiso con el medio ambiente y fomenta hábitos responsables.'),
            $ic('2025/11/iconos_MARKETING-SUSTENTABLE.png', 'Marketing sustentable', 'Los vasos reutilizables también se pueden personalizar, lo que es una oportunidad para aumentar el reconocimiento y la visibilidad de las marcas.'),
            $ic('2025/11/iconos_FABRICACION-NACIONAL.png', 'Fabricación nacional', 'Todos nuestros productos son fabricados en Chile, apoyando la producción local y reduciendo la huella de carbono en transporte. Además, estamos alineados con la Ley N.º 21.368, que regula y disminuye el uso de plásticos de un solo uso.')]], $bt('Cotiza aquí', '/cotizador/'))),
    ], 'Nosotros - MiVaso.cl', 'Desde 2015 innovamos en economía circular para eventos masivos en Chile, promoviendo la reutilización de vasos como alternativa real a los desechables.', $im('2025/12/banner3.png'));

    $ban = 'elementor/thumbs/IMG_8741-scaled-rfhkks1a5d6n8axxqaeie48ifmy1za8jiiblgngn0o.jpg';
    $paraQuien = $pag('para-quien', 'econimia-circular', '¿Para quién?', [
        $base('banner', 's-pq-banner', ['slides' => [$sl($im($ban), 'Barra de evento sirviendo en vasos reutilizables')], 'alto' => 'medio', 'efecto' => 'zoom', 'intervalo' => 6, 'oscurecer' => 'suave', 'animacion' => 'ninguna']),
        $base('iconos', 's-pq-quienes', array_merge(['titulo' => '¿Quiénes usan nuestros productos?', 'intro' => 'Los vasos reutilizables de Mi Vaso son la solución perfecta para todo tipo de eventos que buscan reducir su impacto ambiental, alinearse con la Ley REP (Ley 20.920) y disminuir su huella de carbono.', 'estilo' => 'fila', 'columnas' => 1, 'ancla' => 'quienes_usan_nuestro_producto', 'items' => [
            $ic('2025/11/ico_Festivales-o.png', 'Festivales o eventos masivos', 'Eventos gastronómicos o culturales, pequeños o grandes, de uno o varios días de duración, en una ciudad o en medio de las montañas. Hacemos que todos los eventos sean 100% libres de vasos descartables.'),
            $ic('2025/11/ico_Empresas-casinos.png', 'Empresas, casinos, restaurantes, etc.', 'Son los lugares que más residuos generan. Incentivamos una nueva cultura de consumo reutilizable dentro de la organización y se generan importantes ahorros en compras.'),
            $ic('2025/11/ico_Discotecas-o-bares.png', 'Discotecas o bares nocturnos', 'Son grandes generadores de residuos de plástico, sobre todo en terrazas. Con nuestros vasos o copas reutilizables puedes reemplazar el cristal de vasos y copas que se quiebran con tanta facilidad.'),
            $ic('2025/11/ico_Marcas-de-distribucion.png', 'Marcas de distribución de bebidas', 'Trabajamos con marcas que buscan fortalecer su presencia en eventos y puntos de venta mediante vasos reutilizables personalizados, reduciendo residuos y reforzando su identidad de marca de forma sostenible.'),
            $ic('2025/11/ico_Agencias-de-publicidad.png', 'Agencias de publicidad (material POP)', 'Colaboramos con agencias que necesitan piezas reutilizables y personalizables para activaciones, eventos y campañas, ofreciendo vasos y portavasos que funcionan como soporte publicitario duradero, visible y ecoamigable.')]], $bt())),
        $base('logos', 's-pq-marcas', array_merge(['titulo' => 'Ellos confían en nosotros', 'intro' => '', 'estilo' => 'grilla', 'items' => $marcas, 'fondo' => 'claro'], $bt('Cotiza aquí', '/cotizador/'))),
    ], '¿Para quién? Economía circular - MiVaso.cl', 'Vasos reutilizables para festivales, empresas, casinos, bares, marcas de bebidas y agencias. Reduce residuos y cumple la Ley REP.', $im($ban));

    $contacto = $pag('contacto', 'contacto', 'Contacto', [
        $base('contacto', 's-con-form', ['estilo' => 'caja', 'titulo' => 'Contáctanos', 'intro' => 'Te ayudaremos a elegir la mejor alternativa según tu tipo de evento o negocio.', 'texto' => 'Completa el formulario y nos pondremos en contacto contigo a la brevedad.',
            'datos' => [['etiqueta' => 'Correo electrónico', 'valor' => 'contacto@mivaso.cl', 'url' => 'mailto:contacto@mivaso.cl'], ['etiqueta' => 'WhatsApp', 'valor' => '+56 9 5043 5981', 'url' => MV_WA],
                        ['etiqueta' => 'Teléfono (solo llamadas)', 'valor' => '+56 2 2583 1902', 'url' => 'tel:+56225831902'], ['etiqueta' => 'Horario', 'valor' => 'Lunes a viernes de 8:00 a 17:00 horas', 'url' => '']],
            'etiqueta_nombre' => 'Nombre y apellido', 'animacion' => 'ninguna']),
    ], 'Contacto - MiVaso.cl', 'Escríbenos y te ayudamos a elegir la mejor alternativa de vasos reutilizables para tu evento o negocio. contacto@mivaso.cl · +56 9 5043 5981');

    return ['version' => 1, 'revision' => 0, 'paginas' => [$inicio, $nosotros, $paraQuien, $contacto], 'menu' => [
        ['id' => 'm-inicio', 'tipo' => 'pagina', 'ref' => 'inicio', 'etiqueta' => 'INICIO', 'nivel' => 0, 'visible' => true],
        ['id' => 'm-nosotros', 'tipo' => 'pagina', 'ref' => 'nosotros', 'etiqueta' => 'NOSOTROS', 'nivel' => 0, 'visible' => true],
        ['id' => 'm-para-quien', 'tipo' => 'pagina', 'ref' => 'para-quien', 'etiqueta' => '¿PARA QUIÉN?', 'nivel' => 0, 'visible' => true],
        ['id' => 'm-productos', 'tipo' => 'productos', 'etiqueta' => 'PRODUCTOS', 'nivel' => 0, 'visible' => true],
        ['id' => 'm-contacto', 'tipo' => 'pagina', 'ref' => 'contacto', 'etiqueta' => 'CONTACTO', 'nivel' => 0, 'visible' => true]]];
}

/** Plantillas para páginas nuevas. */
function mv_plantilla_pagina(string $cual): array {
    $base = fn(string $tipo, array $d) => array_merge(['id' => 's-' . bin2hex(random_bytes(4)), 'tipo' => $tipo, 'oculto' => false, 'fondo' => 'blanco', 'fondo_img' => null, 'espacio' => 'normal', 'animacion' => 'subir', 'ancla' => ''], $d);
    $U = MV_U; $im = fn(string $f, string $alt = '') => ['src' => $U . $f, 'medio' => $U . $f, 'mini' => $U . $f, 'alt' => $alt];
    $p = ['id' => '', 'slug' => '', 'titulo' => '', 'tipo' => 'pagina', 'sistema' => false, 'estado' => 'borrador', 'plantilla' => ['cabecera' => 'normal', 'pie' => 'normal'],
          'seo' => ['titulo' => '', 'descripcion' => '', 'imagen' => null, 'noindex' => false], 'secciones' => []];
    if ($cual === 'pagina') {
        $p['secciones'] = [
            $base('banner', ['slides' => [['img' => $im('2025/12/banner3.png'), 'img_movil' => null, 'titulo' => 'Título de la página', 'texto' => 'Una frase corta que explique de qué se trata.', 'boton_texto' => '', 'boton_url' => '', 'posicion' => 'izq', 'alt' => '']], 'alto' => 'medio', 'efecto' => 'zoom', 'intervalo' => 6, 'oscurecer' => 'suave', 'animacion' => 'ninguna']),
            $base('texto', ['titulo' => 'Subtítulo', 'intro' => '', 'texto' => "Escribe aquí el contenido de la página.\n\nDeja una línea en blanco para empezar un párrafo nuevo.", 'alinear' => 'izq', 'ancho' => 'angosto', 'boton_texto' => '', 'boton_url' => '', 'boton_estilo' => 'relleno']),
            $base('cta', ['titulo' => '¿Listo para tu evento?', 'texto' => 'Cotiza tus vasos personalizados desde 500 unidades.', 'boton_texto' => 'Cotiza aquí', 'boton_url' => '/cotizador/', 'boton2_texto' => 'Escríbenos', 'boton2_url' => '/contacto/', 'boton2_estilo' => 'borde', 'fondo' => 'verde']),
        ];
    }
    if ($cual === 'evento') {
        $p['tipo'] = 'landing'; $p['plantilla'] = ['cabecera' => 'simple', 'pie' => 'simple'];
        $p['secciones'] = [
            $base('banner', ['slides' => [
                ['img' => $im('2025/11/Color-scaled.jpg'), 'img_movil' => null, 'titulo' => 'Vasos personalizados para tu evento', 'texto' => 'Festivales, ferias y fiestas sin vasos desechables. Pide los tuyos desde 500 unidades.', 'boton_texto' => 'Cotiza ahora', 'boton_url' => '#cotizar', 'posicion' => 'izq', 'alt' => 'Vasos reutilizables de colores'],
                ['img' => $im('2025/12/banner3.png'), 'img_movil' => null, 'titulo' => 'Con tu logo, en el color que quieras', 'texto' => 'Impresión en serigrafía de 1 a 4 colores o full color.', 'boton_texto' => 'Ver productos', 'boton_url' => '/productos/', 'posicion' => 'izq', 'alt' => 'Producción de Mi Vaso']],
                'alto' => 'pantalla', 'efecto' => 'zoom', 'intervalo' => 6, 'oscurecer' => 'fuerte', 'animacion' => 'ninguna']),
            $base('contador', ['titulo' => 'Faltan', 'intro' => 'Reserva tus vasos con tiempo: producimos en hasta 5 días hábiles.', 'fecha' => date('Y-m-d', strtotime('+30 days')) . 'T20:00', 'texto_fin' => '¡El evento ya comenzó!', 'boton_texto' => '', 'boton_url' => '', 'boton_estilo' => 'relleno', 'fondo' => 'claro']),
            $base('iconos', ['titulo' => '¿Por qué vasos reutilizables?', 'intro' => '', 'estilo' => 'tarjeta', 'columnas' => 3, 'items' => [
                ['img' => $im('2025/11/iconos_REDUCCION-DE-RESIDUOS.png'), 'titulo' => 'Menos basura', 'texto' => 'Un vaso reutilizable reemplaza cientos de desechables.', 'url' => ''],
                ['img' => $im('2025/11/iconos_MARKETING-SUSTENTABLE.png'), 'titulo' => 'Tu marca en cada mano', 'texto' => 'Un recuerdo que tus asistentes se llevan a casa.', 'url' => ''],
                ['img' => $im('2025/11/iconos_FABRICACION-NACIONAL.png'), 'titulo' => 'Hecho en Chile', 'texto' => 'Fabricación nacional y entrega rápida.', 'url' => '']], 'boton_texto' => '', 'boton_url' => '', 'boton_estilo' => 'relleno']),
            $base('productos', ['titulo' => 'Elige tu vaso', 'intro' => '', 'modo' => 'destacados', 'categoria' => 'vasos', 'elegidos' => [], 'maximo' => 4, 'columnas' => 4, 'boton_texto' => 'Ver todos', 'boton_url' => '/productos/', 'boton_estilo' => 'borde', 'fondo' => 'claro']),
            $base('numeros', ['titulo' => '', 'intro' => '', 'columnas' => 3, 'items' => [['numero' => 10, 'prefijo' => '+', 'sufijo' => 'años', 'texto' => 'de experiencia'], ['numero' => 500, 'prefijo' => '', 'sufijo' => '', 'texto' => 'unidades mínimas'], ['numero' => 5, 'prefijo' => '', 'sufijo' => 'días', 'texto' => 'hábiles de producción']], 'fondo' => 'verde']),
            $base('acordeon', ['titulo' => 'Preguntas frecuentes', 'intro' => '', 'items' => [['pregunta' => '¿Cuánto es el mínimo?', 'respuesta' => 'Fabricamos vasos personalizados desde 500 unidades.'], ['pregunta' => '¿Cuánto se demoran?', 'respuesta' => 'Hasta 5 días hábiles para pedidos de 500 a 5.000 unidades.']], 'abrir_primero' => true, 'ancho' => 'angosto', 'boton_texto' => '', 'boton_url' => '', 'boton_estilo' => 'relleno']),
            $base('contacto', ['estilo' => 'banda', 'titulo' => 'Cotiza para tu evento', 'intro' => '', 'texto' => 'Cuéntanos la fecha, cuántas personas esperas y qué vaso te gusta. Te respondemos a la brevedad.', 'datos' => [['etiqueta' => 'WhatsApp', 'valor' => '+56 9 5043 5981', 'url' => MV_WA]], 'etiqueta_nombre' => 'Nombre', 'ancla' => 'cotizar', 'espacio' => 'ninguno', 'animacion' => 'ninguna']),
        ];
        $p['seo']['noindex'] = false;
    }
    return $p;
}
