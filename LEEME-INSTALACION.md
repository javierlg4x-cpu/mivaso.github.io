# Sitio Mi Vaso sin WordPress — guía de instalación

Este paquete es una copia completa de mivaso.cl en HTML: mismas páginas, mismas direcciones
(URLs), textos, imágenes y PDF. Incluye un **cotizador en cada producto** con vista previa del
vaso con el logo del cliente, y un **panel de administración** para definir a qué correo llegan
las cotizaciones. No usa WordPress, plugins ni base de datos.

## Qué contiene

| Archivo / carpeta | Para qué sirve |
|---|---|
| `index.html` y una carpeta por página | Las 26 páginas del sitio |
| `assets/` | Estilos y programas del sitio (incluye el cotizador y la vista previa) |
| `enviar.php` | Recibe el formulario de contacto y las cotizaciones y las envía por correo |
| `admin/` | Panel: productos, categorías, Google, correo de destino, historial de cotizaciones |
| `privado/` | Catálogo, configuración e historial. No es accesible desde la web |
| `privado/datos/catalogo.json` | Todos los productos y categorías (lo edita el panel) |
| `medios/` | Imágenes y PDF que subes desde el panel |
| `.htaccess` | HTTPS, seguridad, caché y redirecciones de URLs viejas de WordPress |
| `wp-content/uploads/.htaccess` | Impide que se ejecute código dentro de la carpeta de imágenes |

**Las imágenes y PDF no vienen en el paquete:** el sitio usa los que ya están en tu servidor,
en `wp-content/uploads/`. Por eso esa carpeta se conserva.

## Cómo funciona el cotizador

1. En cada producto el cliente elige **color** (los que hay en bodega se marcan «En stock»), **cantidad**,
   **impresión** (1, 2, 3 tintas o full color; con 1 tinta elige el color de la tinta) y **sube su logo**
   (PNG, JPG, SVG, PDF, AI o EPS, hasta 5 MB).
2. A la izquierda ve la **vista previa**: el vaso dibujado a escala con sus medidas reales,
   en el color elegido, con el logo envuelto en la superficie. Puede cambiar tamaño y altura del logo,
   quitar el fondo blanco y descargar la imagen.
3. «Agregar a mi cotización» guarda la línea. Puede sumar otros colores del mismo vaso u otros productos.
   El botón del menú pasa a decir «MI COTIZACIÓN (n)».
4. En **Mi cotización** revisa todo, indica la **fecha en que lo necesita**, retiro o despacho y sus datos, y envía.

Reglas (las del cotizador original): mínimo **500 unidades por producto** (se pueden repartir entre
colores). Todos los colores se pueden pedir desde ese mínimo.

**Llega un correo** con número de folio, datos del solicitante, fecha requerida, entrega, y por cada
línea: producto, color, cantidad, tintas y la **imagen de la vista previa**. Los logos originales van
adjuntos. Responder el correo le contesta directo al cliente.

## Instalación (unos 30 minutos)

1. **Respaldo completo.** En el panel del hosting descarga una copia de todos los archivos y de la base de datos.
2. **Revisa la carpeta de imágenes.** En `public_html/wp-content/uploads/` busca archivos `.php`
   (no debería haber ninguno). Si aparecen, bórralos: suelen ser restos del hackeo.
3. **Mueve el WordPress fuera de la vista pública.** Crea una carpeta fuera de `public_html`
   (por ejemplo `respaldo-wordpress`) y mueve ahí todo lo de `public_html` **excepto** `wp-content/uploads`.
4. **Sube el sitio nuevo** a `public_html`, incluidos los archivos que empiezan con punto (`.htaccess`).
   Combina la carpeta `wp-content` con la existente.
5. **Permisos:** el panel publica páginas, así que PHP debe poder escribir en `public_html`, `privado` y `medios`
   (en hosting compartido casi siempre ya es así). Se recomienda tener activa la extensión **GD** de PHP
   (crea las miniaturas de las fotos; sin ella las fotos se usan tal cual).
6. **Activa el panel:**
   - Abre `https://mivaso.cl/admin/`. Se crea el archivo `privado/codigo-instalacion.php`.
   - Ábrelo con el administrador de archivos del hosting y copia el código de la línea `CODIGO:`.
   - Pégalo en el panel, indica el correo donde quieres recibir las cotizaciones y crea tu clave.
7. En **Ajustes** revisa el correo de destino (puedes poner varios y una copia) y pulsa
   **Enviar correo de prueba**.
8. Prueba el sitio: recorre el menú, cotiza un producto con un logo y envía. Debe llegar el correo
   y aparecer la cotización en el panel.

### Si los correos no llegan o caen en spam

- El **remitente** debe ser una casilla de tu dominio que exista (por defecto `web@mivaso.cl`; créala en el hosting o cámbiala en Ajustes).
- Mejor aún: en Ajustes activa **SMTP** con los datos de esa casilla (servidor, puerto 465 SSL, usuario y clave).
- Aunque un correo falle, la cotización **queda guardada en el panel** (si el historial está activo).

## Google Analytics y Google Ads

El sitio ya trae las etiquetas que usaba WordPress (Site Kit):
**Analytics G-200QXRBRFL** y **Google Ads AW-846443580 y AW-7587623391**. Las estadísticas y campañas siguen
funcionando sin cortes. Site Kit solo mostraba esos datos dentro de WordPress; ahora se revisan directamente en
[analytics.google.com](https://analytics.google.com) y [Search Console](https://search.google.com/search-console) con la misma cuenta.

En el panel, sección **Google**:
- Cambiar o agregar los ID (Analytics, Ads, Tag Manager) y la verificación de Search Console.
- Pegar las **etiquetas de conversión** de Google Ads (forma `AW-123456789/AbCdEf`) para *Cotización enviada*,
  *Contacto enviado* y *Clic en WhatsApp*. Así las campañas cuentan cotizaciones reales, no solo visitas.
- Al guardar se actualizan todas las páginas.

Eventos que llegan a Analytics: `generate_lead` (cotización o contacto enviados; márcalo como *evento clave*),
`agregar_cotizacion`, `contacto_whatsapp`, `contacto_telefono` y `contacto_correo`.

**Importante:** si Search Console estaba verificado con la etiqueta que ponía Site Kit, al quitar WordPress
puede pedir verificar de nuevo. Copia el código de verificación (meta tag) en el panel o verifica por DNS.

## Agregar productos nuevos (constructor)

En el panel, **Productos → + Nuevo producto** (o **Duplicar** uno parecido, que es lo más rápido). El editor
tiene la vista previa real de la página a la derecha, que se actualiza mientras escribes.

- **Contenido:** nombre, descripción (con negrita y enlaces), categoría y fotos (arrastra para ordenarlas; la primera es la principal).
- **Cotizador:** forma para la vista previa —vaso, vaso con tapa y bombilla o tipo café, copa (flauta, tulipa o balón),
  portavaso, u **«Otro producto»** para cualquier cosa nueva—, medidas, colores y tintas.
  - Cada color tiene nombre, tono, transparencia, *en stock* (se marca en el sitio) o no y **foto propia**: al elegir ese color en el
    sitio, la galería muestra su foto.
  - Con «Otro producto» marcas sobre la foto el **área de impresión** (arrastras un rectángulo) y la vista previa
    monta el logo del cliente sobre la foto del color elegido, recto o curvo.
- **Bloques:** lo que va bajo la descripción, como pestañas. Arrástralos desde la paleta y ordénalos:
  Características, Diseño, Adjuntos (PDF), Usos (íconos), Texto, Imagen, Colores, Video de YouTube y Preguntas.
- **Ajustes:** dirección de la página, destacado en la portada y textos para Google.
- **Publicar** crea la página con el mismo diseño del resto del sitio y la agrega a su categoría, al menú, al
  cotizador, a la portada (si es destacado) y al sitemap de Google.

**Categorías nuevas** (por ejemplo «Bombillas»): panel → **Categorías**. Aparecen solas en el menú «Productos» de todas las páginas.

El panel guarda automáticamente las últimas 20 versiones del catálogo en `privado/datos/respaldos/`.

## Cómo editar

- **Productos, colores, fotos, tintas y medidas:** en el panel, sección Productos. Los colores en stock de los
  productos actuales los tomé de los textos y fotos del sitio original; **revísalos** y ajústalos según tu bodega.
- **Correo de destino, remitente, SMTP:** panel → Correo y clave.
- **Páginas** (Inicio, Nosotros, ¿Para quién?, Contacto y las nuevas): panel → **Páginas y menú** (ver abajo).
  No edites los `index.html` a mano: el panel los vuelve a generar al publicar.

## Páginas, landings y menú (constructor de páginas)

Panel → **Páginas y menú**. Cada página es una lista de **secciones** que se arrastran desde la paleta,
se ordenan con ⋮⋮ y se editan a la izquierda mientras se ven en vivo a la derecha (haz clic en una
sección de la vista previa para abrirla). Secciones disponibles: banner/carrusel animado, imagen y texto,
título y texto (con listas y enlaces), íconos con texto, tarjetas, productos (destacados, de una categoría
o elegidos), logos de clientes (carrusel o grilla), acordeón/preguntas, imagen, galería con zoom, video de
YouTube, llamado a la acción, cuenta regresiva, cifras animadas, formulario de contacto, mapa y espacio.
Cada sección tiene además fondo (blanco, verde claro, verde o imagen), espacio y animación al aparecer.

- **Nueva página:** queda en el menú automáticamente (puedes desmarcarlo en la pestaña «Página»).
- **Landing para evento:** viene armada (banner, cuenta regresiva, beneficios, productos, cifras,
  preguntas y formulario), con cabecera y pie simples y fuera del menú. Ideal para campañas de Google Ads.
- **Menú:** en la misma pantalla. Arrastra para ordenar, «→ Submenú» para colgar un elemento del de
  arriba, cambia el texto, oculta elementos o agrega enlaces externos. «Productos» siempre muestra las
  categorías. Al guardar se actualizan todas las páginas.
- **Ocultar una sección** sin borrarla: botón «Visible/Oculta». **Deshacer:** botón ↶ o Ctrl+Z.
- Enlaces internos: escribe `/pagina/` (ej.: `/contacto/`, `/productos/`, `/#contacto`) o elígelos de la lista.
- Las páginas principales (Inicio, Nosotros, ¿Para quién?, Contacto) no se pueden borrar ni cambiar de
  dirección, para no romper enlaces y anuncios; todo su contenido sí se puede editar.
- Se guardan en `privado/datos/paginas.json` (con las últimas 30 versiones en `respaldos/`). Si ese
  archivo no existe, el sitio usa el contenido original.

## Datos del sitio original que conviene revisar

- *Vaso Patagonia 500 cc*: el peso (30 gr) y las medidas (43/54 x 52 mm) son los del vaso de 60 cc.
  Para la vista previa usé un alto aproximado de 120 mm calculado por su volumen (se marca como referencial).
- *Vaso Patagonia 500 cc con tapa y bombilla*: falta el alto en las medidas.
- *Copa Torres del Paine 580 cc*: el peso dice 15 gr, menos que la copa de 120 cc (59 gr).
- *Portavaso*: asumí 72 mm para vasos 330/380 cc y 81 mm para el de 500 cc.
