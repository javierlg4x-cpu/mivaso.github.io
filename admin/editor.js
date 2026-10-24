/* Mi Vaso — constructor de productos del panel.
   Edita un producto como datos (JSON), muestra la página real en vivo y la publica con el mismo diseño del sitio. */
(function () {
  "use strict";
  var ED = JSON.parse(document.getElementById("ed-datos").textContent);
  var P = ED.producto, slugOriginal = ED.slugOriginal, revision = ED.revision;
  var slugTocado = !!slugOriginal, sucio = false;
  var CSRF = document.querySelector('meta[name="csrf"]').content;
  var $ = function (id) { return document.getElementById(id); };

  // ---------- creación de elementos ----------
  function h(tag, props) {
    var el = document.createElement(tag);
    props = props || {};
    Object.keys(props).forEach(function (k) {
      var v = props[k];
      if (v == null || v === false) return;
      if (k === "texto") el.textContent = v;
      else if (k === "clase") el.className = v;
      else if (k.slice(0, 2) === "on") el.addEventListener(k.slice(2), v);
      else if (k === "valor") el.value = v;
      else if (k === "marcado") el.checked = true;
      else el.setAttribute(k, v === true ? "" : v);
    });
    for (var i = 2; i < arguments.length; i++) {
      var c = arguments[i];
      if (c == null || c === false) continue;
      (Array.isArray(c) ? c : [c]).forEach(function (x) { if (x != null && x !== false) el.appendChild(typeof x === "string" ? document.createTextNode(x) : x); });
    }
    return el;
  }
  var uid = 0;
  function nid(p) { return (p || "c") + "-" + (++uid) + "-" + Math.random().toString(36).slice(2, 6); }
  function campo(etiqueta, control, ayuda) {
    if (!control.id) control.id = nid("f");
    return h("div", { clase: "campo-ed" }, h("label", { for: control.id }, etiqueta), control, ayuda ? h("p", { clase: "ayuda", style: "margin:4px 0 0" }, ayuda) : null);
  }
  function entrada(obj, clave, props, alCambiar) {
    var i = h(props && props.multilinea ? "textarea" : "input", Object.assign({ type: props && props.tipo || "text", valor: obj[clave] == null ? "" : obj[clave], rows: props && props.filas || 3 }, props && props.attrs));
    i.addEventListener("input", function () {
      obj[clave] = props && props.tipo === "number" ? (i.value === "" ? "" : Number(i.value)) : i.value;
      if (alCambiar) alCambiar(i.value); cambio();
    });
    return i;
  }
  function asa(etiqueta) {
    return h("button", { type: "button", clase: "asa", "aria-label": etiqueta || "Arrastrar para ordenar" },
      (function () { var s = document.createElementNS("http://www.w3.org/2000/svg", "svg"); s.setAttribute("width", "14"); s.setAttribute("height", "20"); s.setAttribute("viewBox", "0 0 14 20");
        s.innerHTML = '<g fill="currentColor"><circle cx="4" cy="4" r="1.6"/><circle cx="10" cy="4" r="1.6"/><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="4" cy="16" r="1.6"/><circle cx="10" cy="16" r="1.6"/></g>'; return s; })());
  }
  function quitar(etiqueta, fn) { return h("button", { type: "button", clase: "x", "aria-label": etiqueta, title: etiqueta, onclick: fn }, "×"); }
  function caja(titulo, ayuda) { var c = h("div", { clase: "caja" }, h("h2", { texto: titulo }), ayuda ? h("p", { clase: "ayuda", texto: ayuda }) : null); return c; }
  // Herramientas de texto: **negrita** y [enlace](url)
  function herramientas(ta) {
    function envolver(a, b, defecto) {
      var s = ta.selectionStart, e = ta.selectionEnd, sel = ta.value.slice(s, e) || defecto;
      ta.setRangeText(a + sel + b, s, e, "end"); ta.dispatchEvent(new Event("input")); ta.focus();
    }
    return h("div", { clase: "herr" },
      h("button", { type: "button", onclick: function () { envolver("**", "**", "texto"); } }, h("strong", { texto: "N" }), " Negrita"),
      h("button", { type: "button", onclick: function () { var u = prompt("Dirección del enlace (https://…)", "https://"); if (u) envolver("[", "](" + u + ")", "texto del enlace"); } }, "Enlace"));
  }

  // ---------- estado y guardado ----------
  function cambio() {
    sucio = true; $("ed-estado").className = "ed-estado"; $("ed-estado").textContent = "Cambios sin guardar";
    programarVista(); pintarBarra();
  }
  window.addEventListener("beforeunload", function (e) { if (sucio) { e.preventDefault(); e.returnValue = ""; } });
  function pintarBarra() {
    $("ed-titulo").textContent = P.nombre || "Nuevo producto";
    var pub = P.estado === "publicado";
    $("ed-pill").textContent = pub ? "Publicado" : "Borrador"; $("ed-pill").className = "pill" + (pub ? "" : " borrador");
    $("ed-guardar").textContent = pub ? "Pasar a borrador" : "Guardar borrador";
    $("ed-publicar").textContent = pub ? "Guardar y publicar cambios" : "Publicar";
    $("ed-ver").hidden = !(pub && slugOriginal); if (slugOriginal) $("ed-ver").href = "../" + slugOriginal + "/";
  }
  function guardar(estado) {
    var antes = P.estado; P.estado = estado;
    var botones = [$("ed-guardar"), $("ed-publicar")]; botones.forEach(function (b) { b.disabled = true; });
    $("ed-estado").className = "ed-estado"; $("ed-estado").textContent = estado === "publicado" ? "Publicando…" : "Guardando…";
    Panel.api("guardar", { producto: P, slugOriginal: slugOriginal, revision: revision }).then(function (r) {
      botones.forEach(function (b) { b.disabled = false; });
      if (!r.ok && !r.producto) { P.estado = antes; $("ed-estado").className = "ed-estado mal"; $("ed-estado").textContent = r.error; Panel.aviso(r.error, true); pintarBarra(); return; }
      P = r.producto; slugOriginal = P.slug; revision = r.revision; sucio = false; slugTocado = true;
      history.replaceState(null, "", "./?p=producto&slug=" + encodeURIComponent(P.slug));
      $("ed-estado").textContent = r.ok ? (P.estado === "publicado" ? "Publicado. La página ya está en el sitio." : "Guardado como borrador.") : r.error;
      if (!r.ok) $("ed-estado").className = "ed-estado mal";
      Panel.aviso(r.ok ? (P.estado === "publicado" ? "Publicado en el sitio" : "Borrador guardado") : r.error, !r.ok);
      pintarBarra(); pintarAjustes();
    });
  }
  $("ed-guardar").onclick = function () { if (P.estado === "publicado" && !confirm("La página dejará de verse en el sitio. ¿Continuar?")) return; guardar("borrador"); };
  $("ed-publicar").onclick = function () { guardar("publicado"); };

  // ---------- vista previa en vivo ----------
  var iframe = $("ed-iframe"), temporizador = null, pedido = 0;
  function programarVista() { clearTimeout(temporizador); temporizador = setTimeout(actualizarVista, 650); }
  function actualizarVista() {
    var n = ++pedido; $("ed-vista-estado").textContent = "Actualizando…";
    fetch("api.php?accion=vista", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json", "X-CSRF": CSRF }, body: JSON.stringify({ producto: P, slugOriginal: slugOriginal }) })
      .then(function (r) { return r.text(); }).then(function (html) {
        if (n !== pedido) return;
        var y = 0; try { y = iframe.contentWindow.scrollY; } catch (e) {}
        iframe.onload = function () { try { iframe.contentWindow.scrollTo(0, y); } catch (e) {} $("ed-vista-estado").textContent = "Vista previa en vivo"; };
        iframe.srcdoc = html;
      }).catch(function () { $("ed-vista-estado").textContent = "No se pudo actualizar la vista previa"; });
  }
  document.querySelectorAll(".ed-vista__barra [data-ancho]").forEach(function (b) {
    b.onclick = function () {
      document.querySelectorAll(".ed-vista__barra [data-ancho]").forEach(function (x) { x.setAttribute("aria-pressed", x === b ? "true" : "false"); });
      $("ed-vista").classList.toggle("movil", b.dataset.ancho === "movil");
    };
  });

  // ---------- pestañas del editor ----------
  document.querySelectorAll(".ed-tabs [data-sec]").forEach(function (b) {
    b.onclick = function () {
      document.querySelectorAll(".ed-tabs [data-sec]").forEach(function (x) { x.setAttribute("aria-selected", x === b ? "true" : "false"); $("sec-" + x.dataset.sec).hidden = x !== b; });
      $("ed-panel").scrollTop = 0;
    };
  });

  // =====================================================================
  // CONTENIDO
  // =====================================================================
  function pintarContenido() {
    var s = $("sec-contenido"); s.innerHTML = "";
    var c1 = caja("Datos del producto");
    c1.appendChild(campo("Nombre del producto", entrada(P, "nombre", { attrs: { maxlength: 120, placeholder: "Ej.: Vaso Bío-Bío 400 cc" } }, function (v) {
      if (!slugTocado) { P.slug = slugify(v); var sl = $("ed-slug"); if (sl) sl.value = P.slug; }
      if (!P._cortoTocado) { P.corto = v; var ci = $("ed-corto"); if (ci) ci.value = v; }
    })));
    var corto = entrada(P, "corto", { attrs: { maxlength: 80, id: "ed-corto" } }, function () { P._cortoTocado = true; });
    c1.appendChild(campo("Nombre corto", corto, "Se usa en tarjetas, menú de cotización y correos."));
    var sel = h("select", { onchange: function () { P.categoria = sel.value; cambio(); } },
      ED.categorias.map(function (c) { return h("option", { value: c.id, selected: c.id === P.categoria }, c.titulo); }));
    c1.appendChild(campo("Categoría", sel, h("span", null, "¿Es un tipo de producto nuevo? ", h("a", { href: "./?p=categorias", target: "_blank" }, "Crea una categoría"), " y vuelve a cargar esta página.")));
    var desc = entrada(P, "descripcion", { multilinea: true, filas: 7, attrs: { maxlength: 6000 } });
    c1.appendChild(h("div", null, h("label", { for: desc.id = nid("d") }, "Descripción"), herramientas(desc), desc,
      h("p", { clase: "ayuda", style: "margin:4px 0 0" }, "Deja una línea en blanco para separar párrafos. **texto** se ve en negrita.")));
    s.appendChild(c1);

    // Galería
    var c2 = caja("Fotos del producto", "La primera es la principal. Arrastra para ordenar.");
    var lista = h("ul", { clase: "fotos" });
    function pintarFotos() {
      lista.innerHTML = "";
      P.imagenes.forEach(function (im) {
        var li = h("li", { clase: "foto" }, h("img", { src: im.mini || im.src, alt: "", clase: "asa-foto" }),
          entrada(im, "alt", { attrs: { placeholder: "Descripción", maxlength: 160, "aria-label": "Descripción de la foto" } }),
          quitar("Quitar foto", function () { P.imagenes.splice(P.imagenes.indexOf(im), 1); pintarFotos(); cambio(); }));
        li._dato = im; lista.appendChild(li);
      });
    }
    Panel.ordenable(lista, { asa: ".asa-foto", item: "li", grilla: true, scroll: $("ed-panel"), alSoltar: function () {
      P.imagenes = Array.prototype.map.call(lista.children, function (li) { return li._dato; }); cambio(); pintarFotos();
    }});
    pintarFotos();
    c2.appendChild(h("label", { clase: "subida", tabindex: "0", role: "button", onclick: function (e) { e.preventDefault(); Panel.elegirMedio({ varios: true }).then(function (ms) {
      if (!ms) return; ms.forEach(function (m) { P.imagenes.push({ src: m.src, medio: m.medio, mini: m.mini, alt: "" }); });
      if (!P.miniatura && P.imagenes[0]) P.miniatura = P.imagenes[0].mini;
      pintarFotos(); pintarContenidoMini(); cambio();
    }); } }, h("strong", { texto: "Agregar fotos" }), " · desde tu equipo o la biblioteca"));
    c2.appendChild(lista);
    s.appendChild(c2);

    var c3 = caja("Imagen para listados", "Se muestra en la página de la categoría y en «Mi cotización». Ideal: cuadrada, fondo claro.");
    c3.appendChild(h("div", { clase: "acciones", id: "ed-mini" }));
    s.appendChild(c3);
    pintarContenidoMini();
  }
  function pintarContenidoMini() {
    var c = $("ed-mini"); if (!c) return; c.innerHTML = "";
    c.appendChild(h("img", { src: P.miniatura || null, hidden: !P.miniatura, alt: "", style: "width:90px;height:90px;object-fit:contain;background:#F5F8F3;border-radius:6px" }));
    c.appendChild(h("button", { type: "button", clase: "btn borde chico", onclick: function () { Panel.elegirMedio().then(function (m) { if (m) { P.miniatura = m.mini; pintarContenidoMini(); cambio(); } }); } }, "Elegir otra"));
    if (P.imagenes[0]) c.appendChild(h("button", { type: "button", clase: "btn borde chico", onclick: function () { P.miniatura = P.imagenes[0].mini; pintarContenidoMini(); cambio(); } }, "Usar la foto principal"));
  }
  function slugify(t) { return (t || "").normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 80); }

  // =====================================================================
  // COTIZADOR
  // =====================================================================
  var TIPOS = [
    ["vaso", "Vaso (recto o cónico)"], ["vaso-bombilla", "Vaso con tapa y bombilla"], ["vaso-cafe", "Vaso con tapa tipo café"],
    ["copa-flauta", "Copa flauta"], ["copa-tulipa", "Copa tulipa / goblet"], ["copa-balon", "Copa balón"],
    ["portavaso", "Portavaso"], ["foto", "Otro producto (usar foto)"]
  ];
  function tipoActual() {
    var f = P.cotizador.forma;
    if (f.tipo === "vaso") return f.tapa === "bombilla" ? "vaso-bombilla" : f.tapa === "cafe" ? "vaso-cafe" : "vaso";
    if (f.tipo === "copa") return "copa-" + (f.estilo || "tulipa");
    return f.tipo;
  }
  function fijarTipo(t) {
    var f = P.cotizador.forma, nueva;
    if (t.indexOf("vaso") === 0) { nueva = { tipo: "vaso", fondo: f.fondo || 53, boca: f.boca || 74, alto: f.alto || 138 }; if (t === "vaso-bombilla") nueva.tapa = "bombilla"; if (t === "vaso-cafe") nueva.tapa = "cafe"; }
    else if (t.indexOf("copa") === 0) nueva = { tipo: "copa", estilo: t.split("-")[1], pie: f.pie || 75, boca: f.boca || 85, alto: f.alto && f.alto > 120 ? f.alto : 200 };
    else if (t === "portavaso") nueva = { tipo: "portavaso", diametro: 81, alto: 32 };
    else nueva = { tipo: "foto", area: f.area || { x: 0.3, y: 0.35, w: 0.4, h: 0.3 }, curva: true, anchoMm: 0, medidas: "", imagen: "" };
    nueva.referencial = !!f.referencial;
    P.cotizador.forma = nueva;
  }
  function pintarCotizador() {
    var s = $("sec-cotizador"), C = P.cotizador; s.innerHTML = "";
    var c0 = caja("Cotizador y vista previa");
    var act = h("input", { type: "checkbox", marcado: C.activo, onchange: function () { C.activo = act.checked; cambio(); } });
    c0.appendChild(h("label", { clase: "check" }, act, "Mostrar el cotizador en esta página"));
    c0.appendChild(h("p", { clase: "ayuda" }, "Si lo desactivas, la página muestra botones de WhatsApp y contacto en su lugar."));
    var sel = h("select", { onchange: function () { fijarTipo(sel.value); pintarCotizador(); cambio(); } },
      TIPOS.map(function (t) { return h("option", { value: t[0], selected: t[0] === tipoActual() }, t[1]); }));
    c0.appendChild(campo("Forma para la vista previa", sel, "Para vasos y copas se dibuja a escala con las medidas. Para cualquier otro producto, usa «Otro producto»: el logo se monta sobre la foto de cada color."));
    var f = C.forma, med = h("div", { clase: "grid3" });
    function num(clave, etiqueta, min, max) { return campo(etiqueta, entrada(f, clave, { tipo: "number", attrs: { min: min, max: max, step: "1" } })); }
    if (f.tipo === "vaso") med.append(num("fondo", "Ø fondo (mm)", 10, 400), num("boca", "Ø boca (mm)", 10, 400), num("alto", "Alto (mm)", 10, 500));
    if (f.tipo === "copa") med.append(num("pie", "Ø pie (mm)", 10, 300), num("boca", "Ø boca / máx. (mm)", 10, 400), num("alto", "Alto total (mm)", 30, 500));
    if (f.tipo === "portavaso") med.append(num("diametro", "Ø (mm)", 10, 300), num("alto", "Alto (mm)", 5, 200));
    c0.appendChild(med);
    if (f.tipo !== "foto") {
      var ref = h("input", { type: "checkbox", marcado: f.referencial, onchange: function () { f.referencial = ref.checked; cambio(); } });
      c0.appendChild(h("label", { clase: "check" }, ref, "Medidas aproximadas (se indica en la vista previa)"));
    } else {
      c0.appendChild(editorArea(f));
    }
    s.appendChild(c0);

    // Colores
    var c1 = caja("Colores", "Arrastra para ordenar. Toca el cuadro de la derecha para subir la foto del producto en ese color: aparece en la galería al elegirlo y se usa en la vista previa por foto.");
    var ul = h("ul", { clase: "colores" });
    function pintarColores() {
      ul.innerHTML = "";
      C.colores.forEach(function (col) {
        var hex = h("input", { type: "color", valor: col.hex, "aria-label": "Color", oninput: function () { col.hex = hex.value.toUpperCase(); cambio(); } });
        var st = h("select", { "aria-label": "Disponibilidad", onchange: function () { col.stock = st.value === "1"; cambio(); } },
          h("option", { value: "1", selected: col.stock }, "Mostrar «En stock»"), h("option", { value: "0", selected: !col.stock }, "Sin etiqueta"));
        var alfa = h("input", { type: "range", min: "10", max: "100", valor: Math.round(col.alfa * 100), "aria-label": "Opacidad", oninput: function () { col.alfa = Number(alfa.value) / 100; cambio(); } });
        var foto = h("button", { type: "button", clase: "color__foto", title: "Foto de este color", style: col.foto ? "background-image:url('" + (col.foto.mini || col.foto.src).replace(/'/g, "%27") + "')" : null,
          onclick: function () { Panel.elegirMedio().then(function (m) { if (m) { col.foto = { src: m.src, medio: m.medio, mini: m.mini, alt: col.nombre }; pintarColores(); cambio(); } }); },
          oncontextmenu: function (e) { if (col.foto && confirm("¿Quitar la foto de este color?")) { e.preventDefault(); col.foto = null; pintarColores(); cambio(); } } }, col.foto ? "" : "+ foto");
        var li = h("li", { clase: "color" }, asa(), hex,
          h("div", { clase: "color__det" }, entrada(col, "nombre", { attrs: { maxlength: 60, "aria-label": "Nombre del color" } }), st,
            h("div", { clase: "alfa" }, "Transparencia", alfa, "Opaco")),
          foto, quitar("Quitar color", function () { C.colores.splice(C.colores.indexOf(col), 1); pintarColores(); cambio(); }));
        li._dato = col; ul.appendChild(li);
      });
    }
    Panel.ordenable(ul, { item: "li", scroll: $("ed-panel"), alSoltar: function () { C.colores = Array.prototype.map.call(ul.children, function (li) { return li._dato; }); cambio(); } });
    pintarColores(); c1.appendChild(ul);
    c1.appendChild(h("p", { clase: "ayuda", style: "margin:12px 0 0" }, "Agregar un color:"));
    c1.appendChild(h("div", { clase: "presets" }, ED.presets.map(function (p) {
      return h("button", { type: "button", onclick: function () { C.colores.push({ id: slugify(p[0]), nombre: p[0], hex: p[1], alfa: p[2], stock: true, foto: null }); pintarColores(); cambio(); } },
        h("i", { style: "background:" + p[1] }), p[0]);
    }), h("button", { type: "button", onclick: function () { C.colores.push({ id: nid("color"), nombre: "Nuevo color", hex: "#4B873E", alfa: 0.85, stock: true, foto: null }); pintarColores(); cambio(); } }, "+ Personalizado")));
    s.appendChild(c1);

    // Tintas y variantes
    var c2 = caja("Impresión");
    [["1", "1 color"], ["2", "2 colores"], ["3", "3 colores"], ["full", "4 colores / Full color"]].forEach(function (t) {
      var cb = h("input", { type: "checkbox", marcado: C.tintas.indexOf(t[0]) >= 0, onchange: function () {
        C.tintas = ["1", "2", "3", "full"].filter(function (k) { return k === t[0] ? cb.checked : C.tintas.indexOf(k) >= 0; });
        if (!C.tintas.length) { C.tintas = ["1"]; pintarCotizador(); } cambio();
      } });
      c2.appendChild(h("label", { clase: "check" }, cb, t[1]));
    });
    s.appendChild(c2);
    var c3 = caja("Modelos o variantes (opcional)", "Por ejemplo «Para vaso 500 cc». El cliente elige uno al cotizar.");
    var vs = h("div", { clase: "filas" });
    function pintarVar() {
      vs.innerHTML = "";
      C.variantes.forEach(function (v, i) {
        var inp = h("input", { valor: v, maxlength: "80", "aria-label": "Variante " + (i + 1), oninput: function () { C.variantes[i] = inp.value; cambio(); } });
        vs.appendChild(h("div", { clase: "fila fila--2", style: "grid-template-columns:1fr 24px" }, inp, quitar("Quitar", function () { C.variantes.splice(i, 1); pintarVar(); cambio(); })));
      });
    }
    pintarVar(); c3.append(vs, h("button", { type: "button", clase: "btn borde chico", onclick: function () { C.variantes.push(""); pintarVar(); } }, "+ Agregar modelo"));
    s.appendChild(c3);
  }

  // Área de impresión sobre la foto (productos «Otro producto»)
  function editorArea(f) {
    var cont = h("div", { style: "margin-top:12px" });
    var conFoto = P.cotizador.colores.filter(function (c) { return c.foto; })[0];
    var fotoBase = f.imagen || (conFoto && (conFoto.foto.medio || conFoto.foto.src)) || (P.imagenes[0] && (P.imagenes[0].medio || P.imagenes[0].src));
    cont.appendChild(h("label", null, "Área de impresión"));
    cont.appendChild(h("p", { clase: "ayuda", style: "margin-top:0" }, "Arrastra el rectángulo hasta donde va el logo y ajusta su tamaño con el punto verde. Se aplica a las fotos de todos los colores."));
    if (!fotoBase) { cont.appendChild(h("p", { clase: "mal", texto: "Sube primero una foto del producto (pestaña Contenido) o la foto de un color." })); return cont; }
    var img = h("img", { src: fotoBase, alt: "", draggable: "false" });
    var rect = h("div", { clase: "area__caja" }, h("div", { clase: "area__tirador" }));
    var zona = h("div", { clase: "area" }, img, rect);
    function pintar() { var a = f.area; rect.style.left = a.x * 100 + "%"; rect.style.top = a.y * 100 + "%"; rect.style.width = a.w * 100 + "%"; rect.style.height = a.h * 100 + "%"; }
    pintar();
    rect.addEventListener("pointerdown", function (ev) {
      ev.preventDefault();
      var redim = ev.target.classList.contains("area__tirador"), zr = zona.getBoundingClientRect(), a0 = Object.assign({}, f.area), x0 = ev.clientX, y0 = ev.clientY;
      function mov(e) {
        var dx = (e.clientX - x0) / zr.width, dy = (e.clientY - y0) / zr.height, a = f.area;
        if (redim) { a.w = Math.max(0.03, Math.min(1 - a0.x, a0.w + dx)); a.h = Math.max(0.03, Math.min(1 - a0.y, a0.h + dy)); }
        else { a.x = Math.max(0, Math.min(1 - a0.w, a0.x + dx)); a.y = Math.max(0, Math.min(1 - a0.h, a0.y + dy)); }
        ["x", "y", "w", "h"].forEach(function (k) { a[k] = Math.round(a[k] * 1000) / 1000; });
        pintar();
      }
      function fin() { document.removeEventListener("pointermove", mov); document.removeEventListener("pointerup", fin); cambio(); }
      document.addEventListener("pointermove", mov); document.addEventListener("pointerup", fin);
    });
    cont.appendChild(zona);
    var cur = h("input", { type: "checkbox", marcado: f.curva, onchange: function () { f.curva = cur.checked; cambio(); } });
    cont.appendChild(h("label", { clase: "check" }, cur, "La superficie es curva (el logo se envuelve como en un vaso)"));
    cont.appendChild(h("div", { clase: "grid2" },
      campo("Ancho real del área (mm)", entrada(f, "anchoMm", { tipo: "number", attrs: { min: 0, max: 2000 } }), "Opcional: muestra el tamaño del logo en mm."),
      campo("Medidas a mostrar", entrada(f, "medidas", { attrs: { maxlength: 80, placeholder: "Ej.: Largo 21 cm" } }))));
    return cont;
  }

  // =====================================================================
  // BLOQUES
  // =====================================================================
  var TB = {
    specs: ["Características", "Tabla de datos técnicos", "M4 6h16M4 12h16M4 18h16"],
    diseno: ["Diseño", "Pasos para personalizar", "M4 20l4-1 10-10-3-3L5 16l-1 4zM14 6l3 3"],
    adjuntos: ["Adjuntos", "Plantillas y fichas PDF", "M8 12l6-6a3 3 0 014 4l-8 8a5 5 0 01-7-7l7-7"],
    usos: ["Usos", "Íconos de uso", "M12 3l2.5 5 5.5.8-4 3.9.9 5.5-4.9-2.6L7.1 18.2 8 12.7 4 8.8 9.5 8z"],
    texto: ["Texto", "Párrafos libres", "M5 6h14M5 10h14M5 14h10M5 18h8"],
    imagen: ["Imagen", "Foto con pie", "M4 5h16v14H4zM4 15l5-5 4 4 3-3 4 4"],
    colores: ["Colores", "Muestra los colores", "M12 4a8 8 0 100 16c1 0 1.5-.8 1.2-1.6-.4-1 .3-2 1.4-2H17a3 3 0 003-3c0-5-3.6-9.4-8-9.4zM7.5 11h.01M10 7.5h.01M14.5 7.5h.01"],
    video: ["Video", "YouTube", "M4 6h16v12H4zM10 9l5 3-5 3z"],
    faq: ["Preguntas", "Preguntas frecuentes", "M9 9a3 3 0 115 2c-1 .8-2 1.2-2 3M12 17h.01"]
  };
  function nuevoBloque(tipo) {
    var b = { id: tipo + "-" + Math.random().toString(36).slice(2, 7), tipo: tipo, titulo: { specs: "Detalles del producto", diseno: "Diseño", adjuntos: "Adjuntos", usos: "Usos del producto", texto: "Información", imagen: "Imagen", colores: "Colores", video: "Video", faq: "Preguntas frecuentes" }[tipo] };
    if (tipo === "specs") b.filas = [["Material", ""], ["Capacidad", ""], ["Medidas", ""]];
    if (tipo === "diseno") { b.color = "pantone"; b.plantilla = true; }
    if (tipo === "adjuntos") b.items = [];
    if (tipo === "usos") { b.texto = "Válido para contacto directo con alimentos o bebidas frías y calientes."; b.iconos = ED.iconos.slice(); }
    if (tipo === "texto") b.contenido = "";
    if (tipo === "imagen") { b.img = null; b.pie = ""; }
    if (tipo === "video") b.url = "";
    if (tipo === "faq") b.items = [{ pregunta: "", respuesta: "" }];
    return b;
  }
  function icono(d) { var s = document.createElementNS("http://www.w3.org/2000/svg", "svg"); s.setAttribute("viewBox", "0 0 24 24"); s.setAttribute("aria-hidden", "true"); s.innerHTML = '<path d="' + d + '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'; return s; }

  function pintarBloques() {
    var s = $("sec-bloques"); s.innerHTML = "";
    var c0 = caja("Agregar bloque", "Arrastra un bloque a la lista o tócalo para agregarlo al final. Cada bloque es una pestaña bajo la descripción del producto.");
    var paleta = h("div", { clase: "paleta" });
    var lista = h("ul", { clase: "bloques-ed" });
    Object.keys(TB).forEach(function (t) {
      var b = h("button", { type: "button", title: TB[t][1], onclick: function () { if (this._arrastrado) { this._arrastrado = false; return; } P.bloques.push(nuevoBloque(t)); pintarLista(true); cambio(); } }, icono(TB[t][2]), TB[t][0]);
      Panel.desdePaleta(b, lista, function () {
        b._arrastrado = true;
        var nb = nuevoBloque(t), li = tarjetaBloque(nb, true);
        setTimeout(function () { sincronizar(); cambio(); }, 0);
        return li;
      }, { scroll: $("ed-panel") });
      paleta.appendChild(b);
    });
    c0.appendChild(paleta); s.appendChild(c0);
    var c1 = caja("Bloques de esta página"); c1.appendChild(lista); s.appendChild(c1);
    function sincronizar() { Array.prototype.slice.call(lista.childNodes).forEach(function (n) { if (n.nodeType === 3) n.remove(); }); P.bloques = Array.prototype.map.call(lista.children, function (li) { return li._dato; }).filter(Boolean); lista.classList.toggle("vacio", !P.bloques.length); }
    Panel.ordenable(lista, { item: "li", scroll: $("ed-panel"), alSoltar: function () { sincronizar(); cambio(); } });
    function pintarLista(abrirUltimo) {
      lista.innerHTML = "";
      P.bloques.forEach(function (b, i) { lista.appendChild(tarjetaBloque(b, abrirUltimo && i === P.bloques.length - 1)); });
      lista.classList.toggle("vacio", !P.bloques.length);
      if (!P.bloques.length) lista.textContent = "Arrastra aquí un bloque";
    }
    function tarjetaBloque(b, abierto) {
      var cuerpo = h("div", { clase: "bloque-ed__cuerpo" });
      var li = h("li", { clase: "bloque-ed" + (abierto ? "" : " cerrado") },
        h("div", { clase: "bloque-ed__cab" }, asa("Arrastrar bloque"), h("span", { clase: "bloque-ed__tipo", texto: TB[b.tipo][0] }),
          entrada(b, "titulo", { attrs: { maxlength: 60, "aria-label": "Título de la pestaña" } }),
          h("button", { type: "button", clase: "plegar", "aria-label": "Mostrar u ocultar opciones", onclick: function () { li.classList.toggle("cerrado"); } }, "▾"),
          quitar("Quitar bloque", function () { if (!confirm("¿Quitar el bloque «" + b.titulo + "»?")) return; P.bloques.splice(P.bloques.indexOf(b), 1); li.remove(); sincronizar(); cambio(); })),
        cuerpo);
      li._dato = b;
      cuerpoBloque(b, cuerpo);
      return li;
    }
    pintarLista(false);
  }

  function filasOrdenables(arr, crearFila, nuevo, textoAgregar) {
    var cont = h("div", { clase: "filas" });
    function pintar() {
      cont.innerHTML = "";
      arr.forEach(function (it, i) {
        var f = crearFila(it, i, function () { arr.splice(arr.indexOf(it), 1); pintar(); cambio(); });
        f._dato = it; cont.appendChild(f);
      });
    }
    Panel.ordenable(cont, { item: ".fila", scroll: $("ed-panel"), alSoltar: function () {
      var orden = Array.prototype.map.call(cont.children, function (f) { return f._dato; }); arr.length = 0; orden.forEach(function (x) { arr.push(x); }); cambio();
    }});
    pintar();
    return h("div", null, cont, h("button", { type: "button", clase: "btn borde chico", onclick: function () { arr.push(nuevo()); pintar(); cambio(); } }, textoAgregar));
  }

  function cuerpoBloque(b, c) {
    switch (b.tipo) {
      case "specs":
        c.appendChild(filasOrdenables(b.filas, function (r, i, x) {
          var k = h("input", { valor: r[0], maxlength: "60", placeholder: "Característica", "aria-label": "Característica", oninput: function () { r[0] = k.value; cambio(); } });
          var v = h("input", { valor: r[1], maxlength: "160", placeholder: "Detalle", "aria-label": "Detalle", oninput: function () { r[1] = v.value; cambio(); } });
          return h("div", { clase: "fila" }, asa(), k, v, quitar("Quitar fila", x));
        }, function () { return ["", ""]; }, "+ Agregar fila"));
        break;
      case "diseno":
        var sc = h("select", { onchange: function () { b.color = sc.value; cambio(); } }, h("option", { value: "pantone", selected: b.color !== "cmyk" }, "Pedir Pantone o CMYK (vasos y copas)"), h("option", { value: "cmyk", selected: b.color === "cmyk" }, "Pedir colores en CMYK"));
        var pl = h("input", { type: "checkbox", marcado: b.plantilla, onchange: function () { b.plantilla = pl.checked; cambio(); } });
        c.append(campo("Indicación de color", sc), h("label", { clase: "check" }, pl, "Este producto tiene plantilla descargable en «Adjuntos»"));
        break;
      case "adjuntos":
        c.appendChild(filasOrdenables(b.items, function (a, i, x) {
          var t = h("input", { valor: a.titulo, maxlength: "120", placeholder: "Nombre (ej.: Ficha técnica)", "aria-label": "Nombre del archivo", oninput: function () { a.titulo = t.value; cambio(); } });
          var u = h("input", { valor: a.url, placeholder: "https://… o sube un PDF", "aria-label": "Dirección del archivo", oninput: function () { a.url = u.value; cambio(); } });
          var sub = h("button", { type: "button", clase: "btn borde chico", onclick: function () { Panel.elegirMedio({ pdf: true }).then(function (m) { if (m) { a.url = m.src; u.value = m.src; if (!a.titulo) { a.titulo = (m.nombre || "Archivo").replace(/\.pdf$/i, ""); t.value = a.titulo; } cambio(); } }); } }, "Subir PDF");
          return h("div", { clase: "fila", style: "grid-template-columns:20px 1fr" }, asa(), h("div", { style: "display:grid;gap:4px" }, t, h("div", { clase: "acciones" }, u, sub, quitar("Quitar archivo", x))));
        }, function () { return { titulo: "", url: "" }; }, "+ Agregar archivo"));
        break;
      case "usos":
        c.appendChild(campo("Texto", entrada(b, "texto", { attrs: { maxlength: 300 } })));
        var grid = h("div", { clase: "iconos-sel" });
        var todos = ED.iconos.concat(b.iconos.filter(function (i) { return !ED.iconos.some(function (e) { return e.src === i.src; }); }));
        todos.forEach(function (ic) {
          var cb = h("input", { type: "checkbox", marcado: b.iconos.some(function (x) { return x.src === ic.src; }), onchange: function () {
            b.iconos = todos.filter(function (t) { return t === ic ? cb.checked : b.iconos.some(function (x) { return x.src === t.src; }); }); cambio();
          } });
          grid.appendChild(h("label", null, cb, h("img", { src: ic.src, alt: "" }), ic.alt));
        });
        c.append(h("label", null, "Íconos que se muestran"), grid,
          h("button", { type: "button", clase: "btn borde chico", style: "margin-top:8px", onclick: function () { Panel.elegirMedio().then(function (m) { if (m) { var nom = prompt("¿Qué indica este ícono?", "") || ""; b.iconos.push({ src: m.mini, alt: nom }); c.innerHTML = ""; cuerpoBloque(b, c); cambio(); } }); } }, "+ Subir ícono propio"));
        break;
      case "texto":
        var ta = entrada(b, "contenido", { multilinea: true, filas: 6, attrs: { maxlength: 8000 } });
        c.append(herramientas(ta), ta, h("p", { clase: "ayuda", style: "margin:4px 0 0" }, "Línea en blanco = nuevo párrafo. **texto** = negrita."));
        break;
      case "imagen":
        var ver = h("img", { src: b.img ? b.img.mini : null, hidden: !b.img, alt: "", style: "width:120px;height:120px;object-fit:contain;background:#F5F8F3;border-radius:6px" });
        c.append(h("div", { clase: "acciones", style: "margin-top:10px" }, ver, h("button", { type: "button", clase: "btn borde chico", onclick: function () { Panel.elegirMedio().then(function (m) { if (m) { b.img = { src: m.src, medio: m.medio, mini: m.mini, alt: "" }; ver.src = m.mini; ver.hidden = false; cambio(); } }); } }, "Elegir imagen")),
          campo("Pie de foto", entrada(b, "pie", { attrs: { maxlength: 200 } })));
        break;
      case "colores":
        c.appendChild(h("p", { clase: "ayuda" }, "Muestra los colores configurados en la pestaña «Cotizador», con sus fotos si las tienen."));
        break;
      case "video":
        c.append(campo("Enlace de YouTube", entrada(b, "url", { attrs: { placeholder: "https://www.youtube.com/watch?v=…" } }), "El video se ve en el sitio publicado (no en esta vista previa)."));
        break;
      case "faq":
        c.appendChild(filasOrdenables(b.items, function (q, i, x) {
          var p = h("input", { valor: q.pregunta, maxlength: "200", placeholder: "Pregunta", "aria-label": "Pregunta", oninput: function () { q.pregunta = p.value; cambio(); } });
          var r = h("textarea", { rows: "2", maxlength: "2000", placeholder: "Respuesta", "aria-label": "Respuesta", oninput: function () { q.respuesta = r.value; cambio(); } }); r.value = q.respuesta || "";
          return h("div", { clase: "fila", style: "grid-template-columns:20px 1fr 24px" }, asa(), h("div", { style: "display:grid;gap:4px" }, p, r), quitar("Quitar pregunta", x));
        }, function () { return { pregunta: "", respuesta: "" }; }, "+ Agregar pregunta"));
        break;
    }
  }

  // =====================================================================
  // AJUSTES
  // =====================================================================
  function contador(input, max) {
    var s = h("span", { clase: "contador" });
    function act() { s.textContent = input.value.length + " / " + max; s.classList.toggle("largo", input.value.length > max); }
    input.addEventListener("input", act); act(); return s;
  }
  function pintarAjustes() {
    var s = $("sec-ajustes"); s.innerHTML = "";
    var c0 = caja("Dirección de la página");
    var sl = entrada(P, "slug", { attrs: { id: "ed-slug", maxlength: 80, pattern: "[a-z0-9-]+" } }, function (v) { slugTocado = true; });
    sl.addEventListener("blur", function () { P.slug = slugify(sl.value); sl.value = P.slug; cambio(); });
    c0.appendChild(campo("mivaso.cl/…/", sl, P.estado === "publicado" ? "Cuidado: si cambias la dirección de un producto publicado, los enlaces antiguos (y anuncios) que apunten a él dejarán de funcionar." : "Se crea a partir del nombre. Solo minúsculas, números y guiones."));
    c0.appendChild(campo("Título grande en la página (opcional)", entrada(P, "titulo_ficha", { attrs: { maxlength: 120 } }), "Si lo llenas, se muestra en vez del nombre y el nombre queda como subtítulo."));
    s.appendChild(c0);
    var c1 = caja("Portada");
    var dest = h("input", { type: "checkbox", marcado: P.destacado.activo, onchange: function () { P.destacado.activo = dest.checked; cambio(); } });
    var vi = h("img", { src: P.destacado.img || P.miniatura || null, alt: "", hidden: !(P.destacado.img || P.miniatura), style: "width:70px;height:70px;object-fit:contain;background:#F5F8F3;border-radius:6px" });
    c1.append(h("label", { clase: "check" }, dest, "Mostrar en «Productos destacados» de la página de inicio"),
      h("div", { clase: "acciones" }, vi, h("button", { type: "button", clase: "btn borde chico", onclick: function () { Panel.elegirMedio().then(function (m) { if (m) { P.destacado.img = m.mini; vi.src = m.mini; vi.hidden = false; cambio(); } }); } }, "Imagen especial para portada (opcional)")));
    s.appendChild(c1);
    var c2 = caja("Google (SEO)", "Cómo aparece esta página en los resultados de búsqueda. Si lo dejas vacío se usa el nombre y el primer párrafo.");
    var ti = entrada(P.seo, "titulo", { attrs: { maxlength: 70, placeholder: (P.nombre || "Nombre") + " - MiVaso.cl" } });
    var de = entrada(P.seo, "descripcion", { multilinea: true, filas: 3, attrs: { maxlength: 300 } });
    c2.append(h("label", { for: ti.id = nid("t") }, "Título ", contador(ti, 60)), ti, h("label", { for: de.id = nid("d") }, "Descripción ", contador(de, 160)), de);
    s.appendChild(c2);
  }

  // ---------- inicio ----------
  P.cotizador.variantes = P.cotizador.variantes || [];
  P.bloques = P.bloques || [];
  P.destacado = P.destacado || { activo: false, img: "" };
  P.seo = P.seo || { titulo: "", descripcion: "" };
  P._cortoTocado = !!slugOriginal || (P.corto && P.corto !== P.nombre);
  pintarContenido(); pintarCotizador(); pintarBloques(); pintarAjustes(); pintarBarra();
  actualizarVista();
})();
