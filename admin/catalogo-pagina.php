<?php
/** Panel: listado de productos y de categorías. Se incluye desde index.php (ya hay sesión). */
$datos = mv_datos();
if ($pag === 'productos'): ?>
  <div class="acciones" style="justify-content:space-between"><h1>Productos</h1>
    <div class="acciones"><a class="btn" href="./?p=producto&amp;nuevo=1">+ Nuevo producto</a><button type="button" class="btn borde" id="regenerar">Actualizar todas las páginas</button></div></div>
  <p class="ayuda">Arrastra con <strong>⋮⋮</strong> para cambiar el orden en que aparecen en su categoría. Los borradores no se ven en el sitio.</p>
  <?php foreach ($datos['categorias'] as $c): $ps = array_values(array_filter($datos['productos'], fn($p) => $p['categoria'] === $c['id'])); ?>
    <section class="cat-grupo">
      <h2><span><?= mv_h($c['titulo']) ?> <small>(<?= count($ps) ?>)</small></span> <a class="btn borde chico" href="./?p=producto&amp;nuevo=1&amp;categoria=<?= mv_h($c['id']) ?>">+ Agregar aquí</a></h2>
      <ul class="items" data-cat="<?= mv_h($c['id']) ?>">
      <?php foreach ($ps as $p): ?>
        <li class="item" data-slug="<?= mv_h($p['slug']) ?>">
          <button type="button" class="asa" aria-label="Arrastrar para ordenar <?= mv_h($p['corto']) ?>"><svg width="14" height="20" viewBox="0 0 14 20" aria-hidden="true"><g fill="currentColor"><circle cx="4" cy="4" r="1.6"/><circle cx="10" cy="4" r="1.6"/><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="4" cy="16" r="1.6"/><circle cx="10" cy="16" r="1.6"/></g></svg></button>
          <img src="<?= mv_h($p['miniatura']) ?>" alt="" loading="lazy">
          <div class="item__n"><?= mv_h($p['nombre']) ?> <span class="pill <?= $p['estado'] === 'publicado' ? '' : 'borrador' ?>"><?= $p['estado'] === 'publicado' ? 'Publicado' : 'Borrador' ?></span><small>/<?= mv_h($p['slug']) ?>/</small></div>
          <div class="acciones">
            <a class="btn chico" href="./?p=producto&amp;slug=<?= rawurlencode($p['slug']) ?>">Editar</a>
            <a class="btn borde chico" href="./?p=producto&amp;nuevo=1&amp;copiar=<?= rawurlencode($p['slug']) ?>">Duplicar</a>
            <?php if ($p['estado'] === 'publicado'): ?><a class="btn borde chico" href="../<?= rawurlencode($p['slug']) ?>/" target="_blank" rel="noopener">Ver</a><?php endif; ?>
            <button type="button" class="btn peligro chico" data-eliminar="<?= mv_h($p['slug']) ?>" data-nombre="<?= mv_h($p['nombre']) ?>">Eliminar</button>
          </div>
        </li>
      <?php endforeach; ?>
      </ul>
      <?php if (!$ps): ?><p class="ayuda">Sin productos todavía.</p><?php endif; ?>
    </section>
  <?php endforeach; ?>
  <script>
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".items").forEach(function (ul) {
      Panel.ordenable(ul, { item: "li", alSoltar: function () {
        var slugs = Array.prototype.map.call(ul.querySelectorAll("li[data-slug]"), function (li) { return li.dataset.slug; });
        Panel.api("orden", { slugs: slugs }).then(function (r) { Panel.aviso(r.ok ? "Orden guardado y publicado." : r.error, !r.ok); });
      }});
    });
    document.querySelectorAll("[data-eliminar]").forEach(function (b) {
      b.onclick = function () {
        if (!confirm("¿Eliminar «" + b.dataset.nombre + "»? Su página se quitará del sitio. Esta acción no se puede deshacer.")) return;
        Panel.api("eliminar", { slug: b.dataset.eliminar }).then(function (r) { if (r.ok) b.closest("li").remove(); Panel.aviso(r.ok ? "Producto eliminado." : r.error, !r.ok); });
      };
    });
    document.getElementById("regenerar").onclick = function () {
      var b = this; b.disabled = true;
      Panel.api("regenerar", {}).then(function (r) { b.disabled = false; Panel.aviso(r.ok ? "Todas las páginas están actualizadas." : r.error, !r.ok); });
    };
  });
  </script>

<?php else: /* categorías */ ?>
  <h1>Categorías</h1>
  <p class="ayuda">Las categorías forman el menú «Productos» del sitio y sus páginas de listado. Arrastra con <strong>⋮⋮</strong> para cambiar el orden del menú.</p>
  <ul class="items" id="cats">
  <?php foreach ($datos['categorias'] as $c): $n = count(array_filter($datos['productos'], fn($p) => $p['categoria'] === $c['id'])); ?>
    <li class="item" data-id="<?= mv_h($c['id']) ?>" style="grid-template-columns:28px 56px minmax(0,1fr) auto">
      <button type="button" class="asa" aria-label="Arrastrar para ordenar"><svg width="14" height="20" viewBox="0 0 14 20" aria-hidden="true"><g fill="currentColor"><circle cx="4" cy="4" r="1.6"/><circle cx="10" cy="4" r="1.6"/><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="4" cy="16" r="1.6"/><circle cx="10" cy="16" r="1.6"/></g></svg></button>
      <img src="<?= mv_h($c['img']) ?>" alt="">
      <div class="item__n"><?= mv_h($c['titulo']) ?> <small>Menú: <?= mv_h($c['menu']) ?> · /<?= mv_h($c['slug']) ?>/ · <?= $n ?> producto(s)</small></div>
      <div class="acciones"><button type="button" class="btn chico" data-editar='<?= mv_h(json_encode($c, JSON_UNESCAPED_UNICODE)) ?>'>Editar</button>
        <?php if (!$n): ?><button type="button" class="btn peligro chico" data-borrar="<?= mv_h($c['id']) ?>">Eliminar</button><?php endif; ?></div>
    </li>
  <?php endforeach; ?>
  </ul>
  <form class="caja" id="cat-form" style="margin-top:18px">
    <h2 id="cat-form-h">Nueva categoría</h2>
    <p class="ayuda">Por ejemplo «Bombillas reutilizables»: aparecerá en el menú y podrás asignarle productos.</p>
    <input type="hidden" id="cat-id">
    <div class="grid2">
      <div><label for="cat-titulo">Nombre de la categoría</label><input id="cat-titulo" required maxlength="80" placeholder="Bombillas Reutilizables"></div>
      <div><label for="cat-menu">Texto en el menú</label><input id="cat-menu" maxlength="30" placeholder="BOMBILLAS"></div>
      <div><label for="cat-singular">Nombre en singular <small>(para la cotización)</small></label><input id="cat-singular" maxlength="40" placeholder="Bombilla"></div>
      <div><label for="cat-slug">Dirección <small>(mivaso.cl/<strong>…</strong>/)</small></label><input id="cat-slug" maxlength="80" placeholder="se crea sola"></div>
    </div>
    <label for="cat-seo">Descripción para Google <small>(1–2 frases)</small></label><input id="cat-seo" maxlength="300">
    <label>Imagen de la categoría</label>
    <div class="acciones"><img id="cat-img-ver" alt="" style="width:80px;height:80px;object-fit:contain;background:#F5F8F3;border-radius:6px"><input type="hidden" id="cat-img"><button type="button" class="btn borde chico" id="cat-img-btn">Elegir imagen</button></div>
    <p class="acciones" style="margin-top:16px"><button class="btn">Guardar categoría</button><button type="button" class="btn borde" id="cat-cancelar" hidden>Cancelar</button></p>
  </form>
  <script>
  document.addEventListener("DOMContentLoaded", function () {
    var f = document.getElementById("cat-form"), $ = function (i) { return document.getElementById(i); };
    function cargar(c) {
      $("cat-id").value = c ? c.id : ""; $("cat-titulo").value = c ? c.titulo : ""; $("cat-menu").value = c ? c.menu : "";
      $("cat-singular").value = c ? c.singular : ""; $("cat-slug").value = c ? c.slug : ""; $("cat-seo").value = c ? c.seo : "";
      $("cat-img").value = c ? c.img : ""; $("cat-img-ver").hidden = !(c && c.img); if (c && c.img) $("cat-img-ver").src = c.img;
      $("cat-form-h").textContent = c ? "Editar «" + c.titulo + "»" : "Nueva categoría"; $("cat-cancelar").hidden = !c;
    }
    cargar(null);
    document.querySelectorAll("[data-editar]").forEach(function (b) { b.onclick = function () { cargar(JSON.parse(b.dataset.editar)); f.scrollIntoView({ behavior: "smooth" }); $("cat-titulo").focus(); }; });
    $("cat-cancelar").onclick = function () { cargar(null); };
    $("cat-img-btn").onclick = function () { Panel.elegirMedio().then(function (m) { if (m) { $("cat-img").value = m.mini; $("cat-img-ver").src = m.mini; $("cat-img-ver").hidden = false; } }); };
    f.onsubmit = function (e) {
      e.preventDefault();
      var c = { titulo: $("cat-titulo").value, menu: $("cat-menu").value, singular: $("cat-singular").value, slug: $("cat-slug").value, seo: $("cat-seo").value, img: $("cat-img").value };
      Panel.api("categoria", { categoria: c, idOriginal: $("cat-id").value }).then(function (r) { if (r.ok) location.reload(); else Panel.aviso(r.error, true); });
    };
    document.querySelectorAll("[data-borrar]").forEach(function (b) {
      b.onclick = function () { if (confirm("¿Eliminar esta categoría vacía?")) Panel.api("categoria_eliminar", { id: b.dataset.borrar }).then(function (r) { if (r.ok) location.reload(); else Panel.aviso(r.error, true); }); };
    });
    Panel.ordenable($("cats"), { item: "li", alSoltar: function () {
      var ids = Array.prototype.map.call(document.querySelectorAll("#cats li"), function (li) { return li.dataset.id; });
      Panel.api("categorias_orden", { ids: ids }).then(function (r) { Panel.aviso(r.ok ? "Orden del menú guardado y publicado." : r.error, !r.ok); });
    }});
  });
  </script>
<?php endif;
