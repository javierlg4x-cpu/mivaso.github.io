<?php
/** Panel: listado de páginas y editor del menú. Se incluye desde index.php (ya hay sesión). */
$PG = mv_paginas();
$enMenu = [];
foreach ($PG['menu'] as $m) if ($m['tipo'] === 'pagina') $enMenu[$m['ref']] = true;
$opcPaginas = array_map(fn($p) => ['id' => $p['id'], 'titulo' => $p['titulo'], 'slug' => $p['slug'], 'estado' => $p['estado']], $PG['paginas']);
?>
  <div class="acciones" style="justify-content:space-between"><h1>Páginas y menú</h1>
    <div class="acciones">
      <a class="btn" href="./?p=pagina&amp;nuevo=pagina">+ Nueva página</a>
      <a class="btn borde" href="./?p=pagina&amp;nuevo=evento">+ Landing para evento</a>
      <a class="btn borde" href="./?p=pagina&amp;nuevo=blanco">+ Página en blanco</a>
    </div></div>
  <p class="ayuda">Cada página se arma con secciones (banners, textos, íconos, preguntas, formularios…) que se arrastran y ordenan. Las <strong>landings</strong> son páginas para campañas o eventos: pueden ir sin menú y con cabecera simple.</p>

  <ul class="items" id="paginas">
  <?php foreach ($PG['paginas'] as $p): $url = '../' . ($p['slug'] !== '' ? rawurlencode($p['slug']) . '/' : ''); $n = count($p['secciones']); ?>
    <li class="item" style="grid-template-columns:minmax(0,1fr) auto">
      <div class="item__n"><?= mv_h($p['titulo']) ?>
        <span class="pill <?= $p['estado'] === 'publicado' ? '' : 'borrador' ?>"><?= $p['estado'] === 'publicado' ? 'Publicada' : 'Borrador' ?></span>
        <?php if ($p['tipo'] === 'landing'): ?><span class="pill" style="background:#E6EEF9;color:#1F4F8F">Landing</span><?php endif; ?>
        <?php if (!empty($enMenu[$p['id']])): ?><span class="pill" style="background:#EEF4EB;color:#3D7132">En el menú</span><?php endif; ?>
        <?php if (!empty($p['seo']['noindex'])): ?><span class="pill" style="background:#F1F1F1;color:#666">Oculta en Google</span><?php endif; ?>
        <small>mivaso.cl/<?= mv_h($p['slug'] !== '' ? $p['slug'] . '/' : '') ?> · <?= $n ?> sección(es)<?= !empty($p['sistema']) ? ' · página principal del sitio' : '' ?></small></div>
      <div class="acciones">
        <a class="btn chico" href="./?p=pagina&amp;id=<?= rawurlencode($p['id']) ?>">Editar</a>
        <a class="btn borde chico" href="./?p=pagina&amp;copiar=<?= rawurlencode($p['id']) ?>">Duplicar</a>
        <?php if ($p['estado'] === 'publicado'): ?><a class="btn borde chico" href="<?= mv_h($url) ?>" target="_blank" rel="noopener">Ver</a><?php endif; ?>
        <?php if (empty($p['sistema'])): ?><button type="button" class="btn peligro chico" data-eliminar="<?= mv_h($p['id']) ?>" data-nombre="<?= mv_h($p['titulo']) ?>">Eliminar</button><?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
  </ul>

  <section class="caja" style="margin-top:28px">
    <div class="acciones" style="justify-content:space-between"><h2 style="margin:0">Menú del sitio</h2><button type="button" class="btn" id="menu-guardar">Guardar menú</button></div>
    <p class="ayuda">Arrastra con <strong>⋮⋮</strong> para ordenar. Usa <strong>→</strong> para que un elemento quede como submenú del de arriba. «Productos» muestra solo las categorías. El botón «Cotiza aquí» siempre va al final.</p>
    <ul class="items" id="menu"></ul>
    <div class="acciones" style="margin-top:14px">
      <select id="menu-pagina" aria-label="Página para agregar al menú" style="width:auto"></select>
      <button type="button" class="btn borde chico" id="menu-agregar">+ Agregar página</button>
      <button type="button" class="btn borde chico" id="menu-enlace">+ Agregar enlace</button>
      <button type="button" class="btn borde chico" id="menu-productos" hidden>+ Agregar «Productos»</button>
    </div>
  </section>
  <script id="menu-datos" type="application/json"><?= json_encode(['menu' => $PG['menu'], 'paginas' => $opcPaginas], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <script>
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-eliminar]").forEach(function (b) {
      b.onclick = function () {
        if (!confirm("¿Eliminar la página «" + b.dataset.nombre + "»? Se quitará del sitio y del menú. Esta acción no se puede deshacer.")) return;
        Panel.api("pagina_eliminar", { id: b.dataset.eliminar }).then(function (r) { if (r.ok) b.closest("li").remove(); Panel.aviso(r.ok ? "Página eliminada." : r.error, !r.ok); });
      };
    });

    // ---------- editor del menú ----------
    var D = JSON.parse(document.getElementById("menu-datos").textContent), M = D.menu, ul = document.getElementById("menu");
    var porId = {}; D.paginas.forEach(function (p) { porId[p.id] = p; });
    var asa = '<button type="button" class="asa" aria-label="Arrastrar para ordenar"><svg width="14" height="20" viewBox="0 0 14 20" aria-hidden="true"><g fill="currentColor"><circle cx="4" cy="4" r="1.6"/><circle cx="10" cy="4" r="1.6"/><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="4" cy="16" r="1.6"/><circle cx="10" cy="16" r="1.6"/></g></svg></button>';
    var sucio = false;
    function marcar() { sucio = true; document.getElementById("menu-guardar").textContent = "Guardar menú •"; }
    window.addEventListener("beforeunload", function (e) { if (sucio) { e.preventDefault(); e.returnValue = ""; } });
    function destino(m) {
      if (m.tipo === "productos") return "Categorías de productos (automático)";
      if (m.tipo === "enlace") return m.url;
      var p = porId[m.ref]; if (!p) return "(página eliminada)";
      return "/" + (p.slug ? p.slug + "/" : "") + (p.estado !== "publicado" ? " · borrador: no se ve hasta publicarla" : "");
    }
    function pintar() {
      ul.innerHTML = "";
      M.forEach(function (m, i) {
        if (i === 0) m.nivel = 0;
        var li = document.createElement("li"); li.className = "item menu-item" + (m.nivel ? " sub" : ""); li._m = m;
        li.innerHTML = asa + '<div class="menu-item__c"><input aria-label="Texto del menú" maxlength="40"><small></small>' + (m.tipo === "enlace" ? '<input class="menu-url" aria-label="Dirección" placeholder="https://… o /pagina/">' : "") + '</div>' +
          '<div class="acciones"><button type="button" class="btn borde chico" data-niv title="Submenú">' + (m.nivel ? "← Nivel principal" : "→ Submenú") + '</button>' +
          '<label class="check" style="margin:0"><input type="checkbox" data-vis' + (m.visible ? " checked" : "") + '> Visible</label>' +
          '<button type="button" class="x" aria-label="Quitar del menú" title="Quitar del menú">×</button></div>';
        var inp = li.querySelector("input"); inp.value = m.etiqueta; inp.oninput = function () { m.etiqueta = inp.value; marcar(); };
        li.querySelector("small").textContent = destino(m);
        var u = li.querySelector(".menu-url"); if (u) { u.value = m.url || ""; u.oninput = function () { m.url = u.value; marcar(); }; }
        var nv = li.querySelector("[data-niv]"); if (i === 0 || m.tipo === "productos") nv.hidden = true;
        nv.onclick = function () { m.nivel = m.nivel ? 0 : 1; marcar(); pintar(); };
        li.querySelector("[data-vis]").onchange = function () { m.visible = this.checked; marcar(); };
        li.querySelector(".x").onclick = function () { M.splice(M.indexOf(m), 1); marcar(); pintar(); };
        ul.appendChild(li);
      });
      var sel = document.getElementById("menu-pagina"); sel.innerHTML = "";
      D.paginas.filter(function (p) { return !M.some(function (m) { return m.ref === p.id; }); }).forEach(function (p) {
        var o = document.createElement("option"); o.value = p.id; o.textContent = p.titulo + (p.estado !== "publicado" ? " (borrador)" : ""); sel.appendChild(o);
      });
      sel.hidden = document.getElementById("menu-agregar").hidden = !sel.options.length;
      document.getElementById("menu-productos").hidden = M.some(function (m) { return m.tipo === "productos"; });
    }
    Panel.ordenable(ul, { item: "li", alSoltar: function () { M = Array.prototype.map.call(ul.children, function (li) { return li._m; }); marcar(); pintar(); } });
    document.getElementById("menu-agregar").onclick = function () {
      var p = porId[document.getElementById("menu-pagina").value]; if (!p) return;
      M.push({ tipo: "pagina", ref: p.id, etiqueta: p.titulo.toUpperCase(), nivel: 0, visible: true }); marcar(); pintar();
    };
    document.getElementById("menu-enlace").onclick = function () { M.push({ tipo: "enlace", etiqueta: "ENLACE", url: "https://", nivel: 0, visible: true, nueva: false }); marcar(); pintar(); };
    document.getElementById("menu-productos").onclick = function () { M.push({ tipo: "productos", etiqueta: "PRODUCTOS", nivel: 0, visible: true }); marcar(); pintar(); };
    document.getElementById("menu-guardar").onclick = function () {
      var b = this; b.disabled = true;
      Panel.api("menu_guardar", { menu: M }).then(function (r) {
        b.disabled = false;
        if (r.ok) { M = r.menu; sucio = false; b.textContent = "Guardar menú"; pintar(); }
        Panel.aviso(r.ok ? "Menú guardado y publicado en todas las páginas." : r.error, !r.ok);
      });
    };
    pintar();
  });
  </script>
