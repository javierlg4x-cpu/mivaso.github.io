/* Mi Vaso — constructor de páginas del panel.
   La página es una lista de secciones (datos). Se arrastran desde la paleta, se ordenan, se editan con
   formularios generados desde el esquema del servidor y se ven en vivo a la derecha. */
(function () {
  "use strict";
  var ED = JSON.parse(document.getElementById("ed-datos").textContent);
  var P = ED.pagina, idOriginal = ED.idOriginal, revision = ED.revision, enMenu = ED.enMenu, etiquetaMenu = "";
  var E = ED.esquema, COMUNES = ED.comunes;
  var CSRF = document.querySelector('meta[name="csrf"]').content;
  var U = "https://mivaso.cl/wp-content/uploads/";
  var slugTocado = !!idOriginal, sucio = false, abierta = null;
  var $ = function (id) { return document.getElementById(id); };

  // ---------- utilidades ----------
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
  function nid(p) { return (p || "c") + "-" + (++uid); }
  function sid() { return "s-" + Math.random().toString(36).slice(2, 10); }
  function copia(o) { return JSON.parse(JSON.stringify(o)); }
  function svg(d, tam) { var s = document.createElementNS("http://www.w3.org/2000/svg", "svg"); s.setAttribute("viewBox", "0 0 24 24"); s.setAttribute("width", tam || 18); s.setAttribute("height", tam || 18); s.setAttribute("aria-hidden", "true"); s.innerHTML = '<path d="' + d + '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'; return s; }
  function asa(et) {
    var b = h("button", { type: "button", clase: "asa", "aria-label": et || "Arrastrar para ordenar", title: "Arrastrar para ordenar" });
    b.innerHTML = '<svg width="14" height="20" viewBox="0 0 14 20" aria-hidden="true"><g fill="currentColor"><circle cx="4" cy="4" r="1.6"/><circle cx="10" cy="4" r="1.6"/><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="4" cy="16" r="1.6"/><circle cx="10" cy="16" r="1.6"/></g></svg>';
    return b;
  }
  function boton(texto, fn, clase, titulo) { return h("button", { type: "button", clase: clase || "btn borde chico", title: titulo || null, "aria-label": titulo || null, onclick: fn }, texto); }
  function caja(titulo, ayuda) { return h("div", { clase: "caja" }, h("h2", { texto: titulo }), ayuda ? h("p", { clase: "ayuda", texto: ayuda }) : null); }
  function etiquetado(texto, control, ayuda) {
    if (!control.id) control.id = nid("f");
    return h("div", { clase: "campo-ed" }, h("label", { for: control.id }, texto), control, ayuda ? h("p", { clase: "ayuda", style: "margin:4px 0 0" }, ayuda) : null);
  }
  function slugify(t) { return (t || "").normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 80); }

  // ---------- estado, deshacer y guardado ----------
  var historia = [], tHist = null, ultimo = JSON.stringify(P);
  function cambio(sinVista) {
    sucio = true; $("ed-estado").className = "ed-estado"; $("ed-estado").textContent = "Cambios sin guardar";
    clearTimeout(tHist); tHist = setTimeout(function () { var ahora = JSON.stringify(P); if (ahora !== ultimo) { historia.push(ultimo); if (historia.length > 60) historia.shift(); ultimo = ahora; $("ed-deshacer").disabled = false; } }, 700);
    if (!sinVista) programarVista();
    pintarBarra();
  }
  function deshacer() {
    clearTimeout(tHist);
    var ahora = JSON.stringify(P);
    if (ahora !== ultimo) { P = JSON.parse(ultimo); } else if (historia.length) { P = JSON.parse(historia.pop()); ultimo = JSON.stringify(P); } else return;
    $("ed-deshacer").disabled = !historia.length;
    pintarTodo(); programarVista(); sucio = true; pintarBarra();
  }
  $("ed-deshacer").onclick = deshacer;
  document.addEventListener("keydown", function (e) {
    if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key.toLowerCase() === "z" && !/INPUT|TEXTAREA|SELECT/.test((document.activeElement || {}).tagName || "")) { e.preventDefault(); deshacer(); }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "s") { e.preventDefault(); guardar(P.estado === "publicado" || P.sistema ? "publicado" : "borrador"); }
  });
  window.addEventListener("beforeunload", function (e) { if (sucio) { e.preventDefault(); e.returnValue = ""; } });

  function pintarBarra() {
    $("ed-titulo").textContent = P.titulo || "Nueva página";
    var pub = P.estado === "publicado";
    $("ed-pill").textContent = (P.tipo === "landing" ? "Landing · " : "") + (pub ? "Publicada" : "Borrador"); $("ed-pill").className = "pill" + (pub ? "" : " borrador");
    $("ed-guardar").hidden = !!P.sistema;
    $("ed-guardar").textContent = pub ? "Pasar a borrador" : "Guardar borrador";
    $("ed-publicar").textContent = pub ? "Guardar y publicar cambios" : "Publicar";
    $("ed-ver").hidden = !(pub && idOriginal); $("ed-ver").href = "../" + (P.slug ? P.slug + "/" : "");
  }
  function guardar(estado) {
    var antes = P.estado; P.estado = estado;
    var bs = [$("ed-guardar"), $("ed-publicar")]; bs.forEach(function (b) { b.disabled = true; });
    $("ed-estado").className = "ed-estado"; $("ed-estado").textContent = estado === "publicado" ? "Publicando…" : "Guardando…";
    Panel.api("pagina_guardar", { pagina: P, idOriginal: idOriginal, revision: revision, enMenu: enMenu, etiquetaMenu: etiquetaMenu }).then(function (r) {
      bs.forEach(function (b) { b.disabled = false; });
      if (!r.pagina) { P.estado = antes; $("ed-estado").className = "ed-estado mal"; $("ed-estado").textContent = r.error; Panel.aviso(r.error, true); pintarBarra(); return; }
      var abiertaId = abierta;
      P = r.pagina; idOriginal = P.id; revision = r.revision; enMenu = r.enMenu; sucio = false; slugTocado = true; ultimo = JSON.stringify(P);
      history.replaceState(null, "", "./?p=pagina&id=" + encodeURIComponent(P.id));
      $("ed-estado").textContent = r.ok ? (P.estado === "publicado" ? "Publicada. Ya está en el sitio." : "Guardada como borrador.") : r.error;
      if (!r.ok) $("ed-estado").className = "ed-estado mal";
      Panel.aviso(r.ok ? (P.estado === "publicado" ? "Publicada en el sitio" : "Borrador guardado") : r.error, !r.ok);
      abierta = abiertaId; pintarTodo(); pintarBarra();
    });
  }
  $("ed-guardar").onclick = function () { if (P.estado === "publicado" && !confirm("La página dejará de verse en el sitio. ¿Continuar?")) return; guardar("borrador"); };
  $("ed-publicar").onclick = function () { guardar("publicado"); };

  // ---------- vista previa ----------
  var iframe = $("ed-iframe"), tVista = null, pedido = 0;
  function programarVista() { clearTimeout(tVista); tVista = setTimeout(actualizarVista, 600); }
  function actualizarVista() {
    var n = ++pedido; $("ed-vista-estado").textContent = "Actualizando…";
    fetch("api.php?accion=pagina_vista", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json", "X-CSRF": CSRF }, body: JSON.stringify({ pagina: P, idOriginal: idOriginal }) })
      .then(function (r) { return r.text(); }).then(function (html) {
        if (n !== pedido) return;
        var y = 0; try { y = iframe.contentWindow.scrollY; } catch (e) {}
        iframe.onload = function () { try { iframe.contentWindow.scrollTo(0, y); } catch (e) {} $("ed-vista-estado").textContent = "Vista previa en vivo · haz clic en una sección para editarla"; };
        iframe.srcdoc = html;
      }).catch(function () { $("ed-vista-estado").textContent = "No se pudo actualizar la vista previa"; });
  }
  function irEnVista(id) { try { iframe.contentWindow.postMessage({ mvIr: id }, "*"); } catch (e) {} }
  window.addEventListener("message", function (e) {
    if (e.source !== iframe.contentWindow || !e.data || !e.data.mvSeccion) return;
    mostrarPestana("secciones"); abrirSeccion(e.data.mvSeccion, true);
  });
  document.querySelectorAll(".ed-vista__barra [data-ancho]").forEach(function (b) {
    b.onclick = function () {
      document.querySelectorAll(".ed-vista__barra [data-ancho]").forEach(function (x) { x.setAttribute("aria-pressed", x === b ? "true" : "false"); });
      $("ed-vista").classList.toggle("movil", b.dataset.ancho === "movil");
    };
  });
  function mostrarPestana(cual) {
    document.querySelectorAll(".ed-tabs [data-sec]").forEach(function (x) { x.setAttribute("aria-selected", x.dataset.sec === cual ? "true" : "false"); $("sec-" + x.dataset.sec).hidden = x.dataset.sec !== cual; });
  }
  document.querySelectorAll(".ed-tabs [data-sec]").forEach(function (b) { b.onclick = function () { mostrarPestana(b.dataset.sec); $("ed-panel").scrollTop = 0; }; });

  // =====================================================================
  // CAMPOS (generados desde el esquema)
  // =====================================================================
  function valorDefecto(c) {
    if ("def" in c) return c.def;
    return { texto: "", parrafo: "", img: null, enlace: "", numero: c.min || 0, si: false, fecha: "", lista: [], productos: [], categoria: (ED.categorias[0] || {}).id || "" }[c.t];
  }
  function nuevoItem(c) { var o = {}; c.sub.forEach(function (s) { o[s.k] = valorDefecto(s); }); return o; }

  function herramientas(ta) {
    function envolver(a, b, def) { var s = ta.selectionStart, e = ta.selectionEnd, sel = ta.value.slice(s, e) || def; ta.setRangeText(a + sel + b, s, e, "end"); ta.dispatchEvent(new Event("input")); ta.focus(); }
    function lista(marca) {
      var s = ta.selectionStart, e = ta.selectionEnd, v = ta.value;
      var ini = v.lastIndexOf("\n", s - 1) + 1, fin = v.indexOf("\n", e); if (fin < 0) fin = v.length;
      var bloque = v.slice(ini, fin).split("\n").map(function (l, i) { l = l.replace(/^\s*([-•*]|\d+[.)])\s+/, ""); return (marca === "1" ? (i + 1) + ". " : "- ") + l; }).join("\n");
      var antes = ini > 0 && v.charAt(ini - 1) === "\n" && v.charAt(ini - 2) !== "\n" ? "\n" : "";
      ta.setRangeText(antes + bloque, ini, fin, "end"); ta.dispatchEvent(new Event("input")); ta.focus();
    }
    return h("div", { clase: "herr" },
      h("button", { type: "button", onclick: function () { envolver("**", "**", "texto"); } }, h("strong", { texto: "N" }), " Negrita"),
      h("button", { type: "button", onclick: function () { var u = prompt("Dirección del enlace (https://… o /pagina/)", "https://"); if (u) envolver("[", "](" + u + ")", "texto del enlace"); } }, "Enlace"),
      h("button", { type: "button", onclick: function () { lista("-"); } }, "• Lista"),
      h("button", { type: "button", onclick: function () { lista("1"); } }, "1. Lista"));
  }

  function selectorImagen(obj, k, alCambiar) {
    var cont = h("div", { clase: "img-campo" });
    function pintar() {
      cont.innerHTML = "";
      var v = obj[k];
      var ver = h("button", { type: "button", clase: "img-campo__ver" + (v ? "" : " vacio"), title: "Cambiar imagen", onclick: elegir });
      if (v) ver.appendChild(h("img", { src: v.mini || v.src, alt: "" })); else ver.appendChild(h("span", { texto: "+ Imagen" }));
      cont.appendChild(ver);
      var acc = h("div", { clase: "img-campo__acc" }, boton(v ? "Cambiar" : "Elegir o subir", elegir));
      if (v) {
        acc.appendChild(boton("Quitar", function () { obj[k] = null; pintar(); cambio(); if (alCambiar) alCambiar(); }));
        var alt = h("input", { valor: v.alt || "", placeholder: "Descripción (opcional)", maxlength: "160", "aria-label": "Descripción de la imagen", oninput: function () { obj[k].alt = alt.value; cambio(); } });
        acc.appendChild(alt);
      }
      cont.appendChild(acc);
    }
    function elegir() { Panel.elegirMedio().then(function (m) { if (!m) return; obj[k] = { src: m.src, medio: m.medio || m.src, mini: m.mini || m.src, alt: (obj[k] && obj[k].alt) || "" }; pintar(); cambio(); if (alCambiar) alCambiar(); }); }
    pintar();
    return cont;
  }

  function campo(obj, c, ctx) {
    var v = obj[c.k];
    if (v === undefined) { v = obj[c.k] = copia(valorDefecto(c) === undefined ? null : valorDefecto(c)); }
    var el;
    switch (c.t) {
      case "texto":
        el = h("input", { valor: v, maxlength: c.max || 200, oninput: function () { obj[c.k] = el.value; cambio(); if (ctx.resumen) ctx.resumen(); } });
        return etiquetado(c.e, el, c.ayuda);
      case "parrafo":
        el = h("textarea", { rows: c.filas || 4, maxlength: c.max || 4000, oninput: function () { obj[c.k] = el.value; cambio(); if (ctx.resumen) ctx.resumen(); } }); el.value = v || "";
        el.id = nid("t");
        return h("div", { clase: "campo-ed" }, h("label", { for: el.id }, c.e), herramientas(el), el);
      case "img":
        return h("div", { clase: "campo-ed" }, h("span", { clase: "lbl", texto: c.e }), selectorImagen(obj, c.k, ctx.resumen));
      case "enlace":
        el = h("input", { valor: v, list: "ed-enlaces", placeholder: "Elige de la lista o escribe https://…", oninput: function () { obj[c.k] = el.value.trim(); cambio(); } });
        return etiquetado(c.e, el, c.ayuda || "Para páginas de este sitio usa /pagina/ (ej.: /contacto/).");
      case "opcion":
        el = h("select", { onchange: function () { obj[c.k] = el.value; cambio(); if (ctx.dependen && ctx.dependen[c.k]) ctx.repintar(); } },
          Object.keys(c.op).map(function (k) { return h("option", { value: k, selected: k === v }, c.op[k]); }));
        return etiquetado(c.e, el, c.ayuda);
      case "numero":
        el = h("input", { type: "number", valor: v, min: c.min, max: c.max, step: "1", oninput: function () { obj[c.k] = el.value === "" ? c.min : Number(el.value); cambio(); } });
        return etiquetado(c.e, el, c.ayuda);
      case "si":
        el = h("input", { type: "checkbox", marcado: !!v, onchange: function () { obj[c.k] = el.checked; cambio(); } });
        return h("label", { clase: "check" }, el, c.e);
      case "fecha":
        el = h("input", { type: "datetime-local", valor: v, onchange: function () { obj[c.k] = el.value; cambio(); } });
        return etiquetado(c.e, el, c.ayuda);
      case "categoria":
        el = h("select", { onchange: function () { obj[c.k] = el.value; cambio(); } }, ED.categorias.map(function (x) { return h("option", { value: x.id, selected: x.id === v }, x.titulo); }));
        return etiquetado(c.e, el);
      case "productos": {
        var lista = h("div", { clase: "prods-sel" });
        ED.productos.filter(function (p) { return p.publicado; }).forEach(function (p) {
          var cb = h("input", { type: "checkbox", marcado: v.indexOf(p.slug) >= 0, onchange: function () {
            var a = obj[c.k]; var i = a.indexOf(p.slug); if (cb.checked && i < 0) a.push(p.slug); if (!cb.checked && i >= 0) a.splice(i, 1); cambio();
          } });
          lista.appendChild(h("label", null, cb, h("img", { src: p.mini, alt: "" }), p.nombre));
        });
        return h("div", { clase: "campo-ed" }, h("span", { clase: "lbl", texto: c.e + " (se muestran en el orden en que los marques)" }), lista);
      }
      case "lista": return campoLista(obj, c, ctx);
    }
    return h("span");
  }

  function campoLista(obj, c, ctx) {
    var arr = obj[c.k] = obj[c.k] || [];
    var cont = h("div", { clase: "lista-ed" + (c.compacta ? " lista-ed--compacta" : "") });
    var ul = h("ul", { clase: "lista-ed__items" });
    function resumen(it, i) {
      var t = it[c.resumen] || it.titulo || it.nombre || it.pregunta || "";
      return (i + 1) + ". " + (t ? String(t).slice(0, 60) : "(sin título)");
    }
    function pintar(abrirIdx) {
      ul.innerHTML = "";
      arr.forEach(function (it, i) {
        var img = it.img ? h("img", { src: it.img.mini || it.img.src, alt: "" }) : null;
        var tit = h("span", { clase: "lista-ed__tit", texto: resumen(it, i) });
        var cuerpo = h("div", { clase: "lista-ed__cuerpo" });
        var li = h("li", { clase: "lista-ed__item" + (abrirIdx === i ? "" : " cerrado") },
          h("div", { clase: "lista-ed__cab" }, asa("Arrastrar para ordenar"), img || h("span", { clase: "lista-ed__sinimg" }), tit,
            h("button", { type: "button", clase: "plegar", "aria-label": "Editar", title: "Editar", onclick: function () { li.classList.toggle("cerrado"); } }, "✎"),
            h("button", { type: "button", clase: "x", title: "Duplicar", "aria-label": "Duplicar", style: "color:#4B873E", onclick: function () { if (arr.length >= (c.max || 99)) return; arr.splice(i + 1, 0, copia(it)); pintar(i + 1); cambio(); } }, "⧉"),
            h("button", { type: "button", clase: "x", title: "Quitar", "aria-label": "Quitar", onclick: function () { arr.splice(arr.indexOf(it), 1); pintar(); cambio(); } }, "×")),
          cuerpo);
        li._dato = it;
        var sub = { resumen: function () { tit.textContent = resumen(it, arr.indexOf(it)); var im = li.querySelector(".lista-ed__cab img"); if (it.img) { if (im) im.src = it.img.mini || it.img.src; else pintar(arr.indexOf(it)); } } };
        c.sub.forEach(function (s) { cuerpo.appendChild(campo(it, s, sub)); });
        ul.appendChild(li);
      });
      agregar.disabled = arr.length >= (c.max || 99);
    }
    Panel.ordenable(ul, { item: "li", scroll: $("ed-panel"), alSoltar: function () {
      var orden = Array.prototype.map.call(ul.children, function (li) { return li._dato; }); arr.length = 0; orden.forEach(function (x) { arr.push(x); }); pintar(); cambio();
    }});
    var agregar = boton("+ " + (c.nuevo || "Agregar"), function () { arr.push(nuevoItem(c)); pintar(arr.length - 1); cambio(); });
    var acciones = h("div", { clase: "acciones", style: "margin-top:8px" }, agregar);
    var tieneImg = c.sub.some(function (s) { return s.t === "img" && s.k === "img"; });
    if (tieneImg) acciones.appendChild(boton("+ Subir varias imágenes", function () {
      Panel.elegirMedio({ varios: true }).then(function (ms) {
        if (!ms) return;
        ms.forEach(function (m) { if (arr.length >= (c.max || 99)) return; var it = nuevoItem(c); it.img = { src: m.src, medio: m.medio || m.src, mini: m.mini || m.src, alt: "" }; arr.push(it); });
        pintar(); cambio();
      });
    }));
    pintar();
    return h("div", { clase: "campo-ed" }, h("span", { clase: "lbl", texto: c.e }), cont.appendChild(ul) && cont, acciones);
  }

  // =====================================================================
  // SECCIONES
  // =====================================================================
  var MUESTRA = { src: U + "2025/11/Color-scaled.jpg", medio: U + "2025/11/Color-scaled.jpg", mini: U + "2025/11/Color-scaled.jpg", alt: "" };
  function nuevaSeccion(tipo) {
    var s = { id: sid(), tipo: tipo, oculto: false };
    E[tipo].campos.concat(COMUNES).forEach(function (c) { s[c.k] = copia(valorDefecto(c) === undefined ? null : valorDefecto(c)); });
    // contenido de ejemplo para que se vea algo al agregarla
    var ej = {
      banner: function () { s.slides = [{ img: copia(MUESTRA), img_movil: null, titulo: "Título del banner", texto: "Una frase corta y atractiva.", boton_texto: "Cotiza aquí", boton_url: "/cotizador/", posicion: "izq", alt: "" }]; s.animacion = "ninguna"; },
      texto_imagen: function () { s.img = copia(MUESTRA); s.titulo = "Título de la sección"; s.texto = "Escribe aquí el texto. Deja una línea en blanco para separar párrafos."; },
      texto: function () { s.titulo = "Título de la sección"; s.texto = "Escribe aquí el texto.\n\n- Puedes hacer listas\n- con guiones"; },
      iconos: function () { s.titulo = "Beneficios"; s.items = [1, 2, 3].map(function (n) { return { img: null, titulo: "Beneficio " + n, texto: "Descripción breve.", url: "" }; }); },
      tarjetas: function () { s.titulo = "Título"; s.items = [1, 2].map(function (n) { return { img: copia(MUESTRA), titulo: "Tarjeta " + n, etiqueta: "Subtítulo", texto: "Texto de la tarjeta.", url: "", boton_texto: "Saber más" }; }); },
      desplegables: function () { s.titulo = "Título de la sección"; s.items = [1, 2, 3, 4].map(function (n) { return { img: null, titulo: "Título " + n, etiqueta: "TEXTO DE LA BARRA", texto: "Texto que aparece al abrir.\n\n1. Punto uno\n2. Punto dos", url: "", boton_texto: "Saber más…" }; }); },
      productos: function () { s.titulo = "Productos destacados"; s.boton_texto = "Ver todos los productos"; s.boton_url = "/productos/"; s.boton_estilo = "borde"; },
      logos: function () { s.titulo = "Ellos confían en nosotros"; s.fondo = "claro"; },
      acordeon: function () { s.titulo = "Preguntas frecuentes"; s.items = [{ pregunta: "¿Primera pregunta?", respuesta: "Respuesta." }, { pregunta: "¿Segunda pregunta?", respuesta: "Respuesta." }]; },
      imagen: function () { s.img = copia(MUESTRA); },
      galeria: function () { s.titulo = "Galería"; },
      video: function () { s.titulo = "Video"; },
      cta: function () { s.titulo = "¿Listo para tu evento?"; s.texto = "Cotiza tus vasos personalizados desde 500 unidades."; s.boton_texto = "Cotiza aquí"; s.boton_url = "/cotizador/"; s.fondo = "verde"; },
      contador: function () { var d = new Date(Date.now() + 30 * 864e5); s.titulo = "Faltan"; s.fecha = d.toISOString().slice(0, 10) + "T20:00"; s.fondo = "claro"; },
      numeros: function () { s.items = [{ numero: 10, prefijo: "+", sufijo: "años", texto: "de experiencia" }, { numero: 500, prefijo: "", sufijo: "", texto: "unidades mínimas" }, { numero: 5, prefijo: "", sufijo: "días", texto: "de producción" }]; s.columnas = 3; s.fondo = "verde"; },
      contacto: function () { s.titulo = "Contáctanos"; s.texto = "Completa el formulario y te responderemos a la brevedad."; s.datos = [{ etiqueta: "WhatsApp", valor: "+56 9 5043 5981", url: "https://wa.me/56950435981" }]; s.espacio = "ninguno"; s.animacion = "ninguna"; },
      mapa: function () { s.titulo = ""; },
      espacio: function () { s.animacion = "ninguna"; }
    }[tipo];
    if (ej) ej();
    return s;
  }
  var CONVERTIR = { tarjetas: "desplegables", desplegables: "tarjetas" };
  function resumenSeccion(s) {
    var t = s.titulo || (s.slides && s.slides[0] && s.slides[0].titulo) || (s.img && s.img.alt) || "";
    return t || "(sin título)";
  }

  var listaSec = null;
  function pintarSecciones() {
    var cont = $("sec-secciones"); cont.innerHTML = "";
    var c0 = caja("Agregar sección", "Arrastra una sección a la lista (al lugar que quieras) o tócala para agregarla al final.");
    var paleta = h("div", { clase: "paleta paleta--secciones" });
    listaSec = h("ul", { clase: "bloques-ed secciones-ed" });
    Object.keys(E).forEach(function (t) {
      var b = h("button", { type: "button", title: E[t].d, onclick: function () {
        if (this._arrastrado) { this._arrastrado = false; return; }
        var s = nuevaSeccion(t); P.secciones.push(s); abierta = s.id; pintarLista(); cambio();
        setTimeout(function () { var li = listaSec.lastChild; if (li && li.scrollIntoView) li.scrollIntoView({ behavior: "smooth", block: "start" }); }, 30);
      } }, svg(E[t].i, 22), h("span", { texto: E[t].n }));
      Panel.desdePaleta(b, listaSec, function () {
        b._arrastrado = true;
        var s = nuevaSeccion(t); abierta = s.id;
        var li = tarjeta(s);
        setTimeout(function () { sincronizar(); pintarLista(); cambio(); }, 0);
        return li;
      }, { scroll: $("ed-panel") });
      paleta.appendChild(b);
    });
    c0.appendChild(paleta); cont.appendChild(c0);
    var c1 = caja("Secciones de esta página", "Ordena arrastrando con ⋮⋮. Haz clic en el título para editar, o en la sección dentro de la vista previa.");
    c1.appendChild(listaSec); cont.appendChild(c1);
    Panel.ordenable(listaSec, { item: "li", scroll: $("ed-panel"), alSoltar: function () { sincronizar(); cambio(); pintarLista(); } });
    pintarLista();
  }
  function sincronizar() {
    Array.prototype.slice.call(listaSec.childNodes).forEach(function (n) { if (n.nodeType === 3) n.remove(); });
    P.secciones = Array.prototype.map.call(listaSec.children, function (li) { return li._dato; }).filter(Boolean);
  }
  function pintarLista() {
    listaSec.innerHTML = "";
    P.secciones.forEach(function (s) { listaSec.appendChild(tarjeta(s)); });
    listaSec.classList.toggle("vacio", !P.secciones.length);
    if (!P.secciones.length) listaSec.textContent = "Arrastra aquí la primera sección";
  }
  function tarjeta(s) {
    var T = E[s.tipo];
    var cuerpo = h("div", { clase: "bloque-ed__cuerpo" });
    var res = h("span", { clase: "sec-ed__res", texto: resumenSeccion(s) });
    var ojo = boton(s.oculto ? "Oculta" : "Visible", function () { s.oculto = !s.oculto; ojo.textContent = s.oculto ? "Oculta" : "Visible"; li.classList.toggle("oculta", s.oculto); cambio(); }, "btn borde chico sec-ed__ojo", s.oculto ? "Mostrar en el sitio" : "Ocultar del sitio sin borrarla");
    var cab = h("div", { clase: "bloque-ed__cab sec-ed__cab" }, asa("Arrastrar sección"),
      h("button", { type: "button", clase: "sec-ed__abrir", onclick: function () { if (li.classList.contains("cerrado")) abrirSeccion(s.id); else { li.classList.add("cerrado"); abierta = null; } } },
        svg(T.i, 18), h("span", { clase: "bloque-ed__tipo", texto: T.n }), res),
      ojo,
      (CONVERTIR[s.tipo] ? h("button", { type: "button", clase: "x", title: "Convertir en «" + E[CONVERTIR[s.tipo]].n + "» (mantiene el contenido)", "aria-label": "Convertir en " + E[CONVERTIR[s.tipo]].n, style: "color:#4B873E", onclick: function () {
        var otro = CONVERTIR[s.tipo], n = nuevaSeccion(otro);
        Object.keys(n).forEach(function (k) { if (k !== "tipo" && s[k] !== undefined) n[k] = s[k]; });
        n.id = s.id; n.columnas = Math.min(Math.max(s.columnas || 2, otro === "tarjetas" ? 1 : 2), otro === "tarjetas" ? 3 : 4);
        P.secciones[P.secciones.indexOf(s)] = n; abierta = n.id; pintarLista(); cambio();
      } }, "⇄") : null),
      h("button", { type: "button", clase: "x", title: "Duplicar sección", "aria-label": "Duplicar sección", style: "color:#4B873E", onclick: function () {
        var c = copia(s); c.id = sid(); c.ancla = ""; P.secciones.splice(P.secciones.indexOf(s) + 1, 0, c); abierta = c.id; pintarLista(); cambio();
      } }, "⧉"),
      h("button", { type: "button", clase: "x", title: "Eliminar sección", "aria-label": "Eliminar sección", onclick: function () {
        if (!confirm("¿Eliminar la sección «" + T.n + ": " + resumenSeccion(s) + "»? (Puedes deshacer con ↶)")) return;
        P.secciones.splice(P.secciones.indexOf(s), 1); li.remove(); if (!P.secciones.length) pintarLista(); cambio();
      } }, "×"));
    var li = h("li", { clase: "bloque-ed sec-ed" + (abierta === s.id ? "" : " cerrado") + (s.oculto ? " oculta" : ""), "data-id": s.id }, cab, cuerpo);
    li._dato = s;
    var ctx = { resumen: function () { res.textContent = resumenSeccion(s); }, dependen: {}, repintar: function () { cuerpo.innerHTML = ""; llenar(); } };
    T.campos.concat(COMUNES).forEach(function (c) { if (c.si) ctx.dependen[c.si[0]] = true; });
    function llenar() {
      T.campos.forEach(function (c) { if (!c.si || s[c.si[0]] === c.si[1]) cuerpo.appendChild(campo(s, c, ctx)); });
      var det = h("details", { clase: "sec-ed__diseno" }, h("summary", { texto: "Diseño, fondo y animación" }));
      COMUNES.forEach(function (c) { if (s.tipo === "banner" && c.k !== "ancla") return; if (!c.si || s[c.si[0]] === c.si[1]) det.appendChild(campo(s, c, ctx)); });
      if (ctx._detAbierto) det.open = true;
      det.addEventListener("toggle", function () { ctx._detAbierto = det.open; });
      cuerpo.appendChild(det);
    }
    llenar();
    return li;
  }
  function abrirSeccion(id, desdeVista) {
    abierta = id;
    Array.prototype.forEach.call(listaSec.children, function (li) { if (li._dato) li.classList.toggle("cerrado", li._dato.id !== id); });
    var li = listaSec.querySelector('[data-id="' + id + '"]');
    if (li) { li.scrollIntoView({ behavior: "smooth", block: "start" }); li.classList.add("resaltar"); setTimeout(function () { li.classList.remove("resaltar"); }, 1200); }
    if (!desdeVista) irEnVista(id);
  }

  // =====================================================================
  // AJUSTES DE LA PÁGINA
  // =====================================================================
  function pintarAjustes() {
    var s = $("sec-ajustes"); s.innerHTML = "";
    var c0 = caja("Página");
    var nom = h("input", { valor: P.titulo, maxlength: "120", placeholder: "Ej.: Fiestas Patrias 2026", oninput: function () {
      P.titulo = nom.value;
      if (!slugTocado && !P.sistema) { P.slug = slugify(nom.value); sl.value = P.slug; }
      cambio();
    } });
    c0.appendChild(etiquetado("Nombre de la página", nom, "Se usa en el panel, en el menú y en el título de Google si no pones uno."));
    var sl = h("input", { valor: P.slug, maxlength: "80", disabled: P.sistema ? true : null, oninput: function () { slugTocado = true; P.slug = sl.value; cambio(true); } });
    sl.addEventListener("blur", function () { P.slug = slugify(sl.value); sl.value = P.slug; cambio(); });
    c0.appendChild(etiquetado("Dirección: mivaso.cl/…/", sl, P.sistema ? "Es una página principal del sitio: su dirección no cambia (así no se rompen enlaces ni anuncios)." : (P.estado === "publicado" ? "Cuidado: si cambias la dirección de una página publicada, los enlaces antiguos dejarán de funcionar." : "Solo minúsculas, números y guiones.")));
    var tipo = h("select", { disabled: P.sistema ? true : null, onchange: function () {
      P.tipo = tipo.value;
      if (P.tipo === "landing" && !idOriginal) { P.plantilla.cabecera = "simple"; P.plantilla.pie = "simple"; enMenu = false; }
      cambio(); pintarAjustes();
    } }, h("option", { value: "pagina", selected: P.tipo !== "landing" }, "Página normal del sitio"), h("option", { value: "landing", selected: P.tipo === "landing" }, "Landing (campaña o evento)"));
    c0.appendChild(etiquetado("Tipo", tipo));
    s.appendChild(c0);

    var c1 = caja("Menú y diseño general");
    var cm = h("input", { type: "checkbox", marcado: enMenu, disabled: P.sistema && enMenu ? null : null, onchange: function () { enMenu = cm.checked; cambio(true); pintarAjustes(); } });
    c1.appendChild(h("label", { clase: "check" }, cm, "Mostrar en el menú principal"));
    if (enMenu && !idOriginal) {
      var em = h("input", { valor: etiquetaMenu, maxlength: "40", placeholder: (P.titulo || "Nombre").toUpperCase(), oninput: function () { etiquetaMenu = em.value; } });
      c1.appendChild(etiquetado("Texto en el menú (opcional)", em, "El orden y los submenús se ajustan en «Páginas y menú»."));
    } else c1.appendChild(h("p", { clase: "ayuda" }, "El orden, los submenús y el texto del menú se cambian en ", h("a", { href: "./?p=paginas", target: "_blank" }, "Páginas y menú"), "."));
    var cab = h("select", { onchange: function () { P.plantilla.cabecera = cab.value; cambio(); } }, h("option", { value: "normal", selected: P.plantilla.cabecera !== "simple" }, "Normal (logo y menú completo)"), h("option", { value: "simple", selected: P.plantilla.cabecera === "simple" }, "Simple (logo y botón «Cotiza aquí»)"));
    var pie = h("select", { onchange: function () { P.plantilla.pie = pie.value; cambio(); } }, h("option", { value: "normal", selected: P.plantilla.pie !== "simple" }, "Normal (completo)"), h("option", { value: "simple", selected: P.plantilla.pie === "simple" }, "Simple (una línea)"));
    c1.append(etiquetado("Cabecera", cab, "Para landings conviene la simple: el visitante no se distrae y va directo a cotizar."), etiquetado("Pie de página", pie));
    s.appendChild(c1);

    var c2 = caja("Google (SEO) y redes", "Cómo aparece esta página en Google y al compartirla en WhatsApp o redes sociales.");
    var ti = h("input", { valor: P.seo.titulo, maxlength: "90", placeholder: (P.titulo || "Nombre") + " - MiVaso.cl", oninput: function () { P.seo.titulo = ti.value; cambio(true); cont(ti, cti, 60); } });
    var de = h("textarea", { rows: "3", maxlength: "300", oninput: function () { P.seo.descripcion = de.value; cambio(true); cont(de, cde, 160); } }); de.value = P.seo.descripcion || "";
    var cti = h("span", { clase: "contador" }), cde = h("span", { clase: "contador" });
    function cont(i, s2, max) { s2.textContent = i.value.length + " / " + max; s2.classList.toggle("largo", i.value.length > max); }
    cont(ti, cti, 60); cont(de, cde, 160);
    ti.id = nid("t"); de.id = nid("d");
    c2.append(h("label", { for: ti.id }, "Título ", cti), ti, h("label", { for: de.id }, "Descripción ", cde), de,
      h("div", { clase: "campo-ed" }, h("span", { clase: "lbl", texto: "Imagen al compartir (opcional; si no, se usa la primera imagen de la página)" }), selectorImagen(P.seo, "imagen")));
    var ni = h("input", { type: "checkbox", marcado: !!P.seo.noindex, onchange: function () { P.seo.noindex = ni.checked; cambio(true); } });
    c2.appendChild(h("label", { clase: "check" }, ni, "Ocultar de Google (útil para landings de prueba o privadas)"));
    s.appendChild(c2);
  }

  function pintarTodo() { pintarSecciones(); pintarAjustes(); }

  // ---------- inicio ----------
  P.secciones = P.secciones || []; P.plantilla = P.plantilla || { cabecera: "normal", pie: "normal" }; P.seo = P.seo || { titulo: "", descripcion: "", imagen: null, noindex: false };
  if (!idOriginal && !P.titulo) mostrarPestana("secciones");
  pintarTodo(); pintarBarra(); actualizarVista();
  if (!idOriginal) { sucio = true; $("ed-estado").textContent = "Página nueva sin guardar"; }
})();
