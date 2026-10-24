/* Mi Vaso — cotizador dentro de cada ficha de producto */
(function () {
  "use strict";
  var caja = document.getElementById("cotiza");
  if (!caja || !window.MIVASO_CATALOGO || !window.MiVasoVista || !window.MiVasoCotizacion) return;
  var C = window.MiVasoCotizacion, V = window.MiVasoVista, CAT = window.MIVASO_CATALOGO, R = CAT.reglas;
  var prod = C.producto(caja.dataset.slug);
  if (!prod) return;
  caja.hidden = false;
  var ns = document.querySelector(".cotiza-sin-js"); if (ns) ns.remove();

  var $ = function (id) { return document.getElementById(id); };
  var lienzo = $("previa-canvas"), previa = $("previa"), galeria = document.querySelector(".galeria");
  var tabsVisor = document.querySelector(".visor__tabs");
  var oscuros = ["negro", "azul", "morado", "rojo", "reciclado", "fucsia"];

  var estado = {
    color: prod.colores[0], variante: prod.variantes ? prod.variantes[0] : null,
    cantidad: R.minimo, tintas: prod.tintas[0], tintaColor: null,
    logo: null, fuente: null, quitarFondo: true, ajuste: { escala: 0.75, posY: 0.5 }, editando: null
  };

  // ---------- Colores ----------
  var cajaColores = $("cz-colores");
  prod.colores.forEach(function (c, i) {
    var id = "cz-color-" + c.id;
    var lab = document.createElement("label");
    lab.className = "muestra";
    lab.innerHTML = '<input type="radio" name="cz-color" id="' + id + '" value="' + c.id + '"' + (i === 0 ? " checked" : "") + '>' +
      '<span class="muestra__punto' + (c.foto ? ' muestra__punto--foto' : '') + '" style="--c:' + c.hex + ';--a:' + (c.foto ? 1 : Math.max(0.35, c.alfa)) + '"></span>' +
      '<span class="muestra__txt"><span class="muestra__n"></span>' + (c.stock ? '<span class="muestra__e stock">En stock</span>' : "") + "</span>";
    lab.querySelector(".muestra__n").textContent = c.nombre;
    if (c.foto) lab.querySelector(".muestra__punto").style.backgroundImage = "url(\"" + encodeURI(c.foto) + "\")";
    lab.querySelector("input").addEventListener("change", function () {
      estado.color = c; mostrarFotoColor(c);
      autoTinta(); notaCantidad(); dibujar(true);
    });
    cajaColores.appendChild(lab);
  });

  // ---------- Variante (portavaso) ----------
  var selVar = $("cz-variante");
  if (selVar && prod.variantes) {
    prod.variantes.forEach(function (v) { var o = document.createElement("option"); o.textContent = v; o.value = v; selVar.appendChild(o); });
    selVar.addEventListener("change", function () { estado.variante = selVar.value; dibujar(true); });
  }

  // ---------- Cantidad ----------
  var inCant = $("cz-cantidad");
  function minimoLinea() { return R.paso; }
  function fijarCantidad(v) {
    v = Math.round(Number(v) || 0);
    v = Math.max(minimoLinea(), Math.min(1000000, v));
    estado.cantidad = v; inCant.value = v; notaCantidad();
  }
  function enCarro() {
    return C.items().filter(function (x) { return x.slug === prod.slug && x.id !== estado.editando; })
      .reduce(function (a, x) { return a + x.cantidad; }, 0);
  }
  function notaCantidad() {
    var n = $("cz-cant-nota"), otras = enCarro(), total = otras + estado.cantidad;
    var t = "Mínimo " + C.formatoN(R.minimo) + " unidades por producto. Puedes repartirlas entre varios colores.";
    if (otras) t += " Ya tienes " + C.formatoN(otras) + " de este producto en tu cotización (total " + C.formatoN(total) + ").";
    else if (estado.cantidad < R.minimo) t += " Te faltan " + C.formatoN(R.minimo - estado.cantidad) + " para el mínimo; súmalas en otro color.";
    n.textContent = t;
  }
  inCant.addEventListener("change", function () { fijarCantidad(inCant.value); });
  $("cz-menos").addEventListener("click", function () { fijarCantidad(estado.cantidad - R.paso); });
  $("cz-mas").addEventListener("click", function () { fijarCantidad(estado.cantidad + R.paso); });

  // ---------- Tintas ----------
  var cajaTintas = $("cz-tintas"), cajaTintaColor = $("cz-tinta-color");
  prod.tintas.forEach(function (t, i) {
    var lab = document.createElement("label"); lab.className = "pastilla";
    lab.innerHTML = '<input type="radio" name="cz-tintas" value="' + t + '"' + (i === 0 ? " checked" : "") + "><span></span>";
    lab.querySelector("span").textContent = CAT.tintas[t];
    lab.querySelector("input").addEventListener("change", function () { estado.tintas = t; mostrarTintaColor(); prepararLogo(); });
    cajaTintas.appendChild(lab);
  });
  var cajaTC = $("cz-tinta-colores");
  CAT.tintaColores.forEach(function (tc) {
    var lab = document.createElement("label"); lab.className = "muestra muestra--chica";
    lab.innerHTML = '<input type="radio" name="cz-tinta-color" value="' + tc.id + '"><span class="muestra__punto" style="--c:' + tc.hex + ';--a:1"></span><span class="muestra__n"></span>';
    lab.querySelector(".muestra__n").textContent = tc.nombre;
    lab.querySelector("input").addEventListener("change", function () { estado.tintaColor = tc; estado.tintaManual = true; prepararLogo(); });
    cajaTC.appendChild(lab);
  });
  var otra = document.createElement("label"); otra.className = "muestra muestra--chica muestra--otra";
  otra.title = "Elige cualquier color (por ejemplo, el Pantone de tu marca)";
  otra.innerHTML = '<input type="radio" name="cz-tinta-color" value="otro" tabindex="-1"><span class="muestra__punto muestra__punto--arcoiris"></span><span class="muestra__n">Otro…</span><input type="color" id="cz-tinta-otro" value="#E6007E" aria-label="Elegir otro color de tinta">';
  cajaTC.appendChild(otra);
  function pintarOtro(hex) {
    var p = otra.querySelector(".muestra__punto");
    p.classList.remove("muestra__punto--arcoiris"); p.style.setProperty("--c", hex); p.style.setProperty("--a", "1");
    otra.querySelector(".muestra__n").textContent = hex.toUpperCase();
  }
  function elegirOtro() {
    var hex = $("cz-tinta-otro").value;
    estado.tintaColor = { id: "otro", nombre: "Otro (" + hex.toUpperCase() + ")", hex: hex }; estado.tintaManual = true;
    otra.querySelector('input[type="radio"]').checked = true; pintarOtro(hex); prepararLogo();
  }
  $("cz-tinta-otro").addEventListener("input", elegirOtro);
  $("cz-tinta-otro").addEventListener("change", elegirOtro);

  function autoTinta() {
    if (estado.tintaManual) return;
    var id = oscuros.indexOf(estado.color.id) >= 0 ? "blanco" : "negro";
    estado.tintaColor = CAT.tintaColores.filter(function (x) { return x.id === id; })[0];
    var r = cajaTC.querySelector('input[value="' + id + '"]'); if (r) r.checked = true;
    prepararLogo();
  }
  function mostrarTintaColor() { cajaTintaColor.hidden = estado.tintas !== "1"; }

  // ---------- Logo ----------
  var inLogo = $("cz-logo"), logoEstado = $("cz-logo-estado"), ajustes = $("cz-ajustes");
  function mensajeLogo(t, mal) { logoEstado.textContent = t; logoEstado.className = "cz-ayuda" + (mal ? " cz-mal" : ""); }

  function cargarLogo(archivo, info) {
    if (!archivo) return;
    var ext = (archivo.name.split(".").pop() || "").toLowerCase();
    if (["png", "jpg", "jpeg", "svg", "pdf", "ai", "eps"].indexOf(ext) < 0) { mensajeLogo("Formato no permitido. Usa PNG, JPG, SVG, PDF, AI o EPS.", true); return; }
    if (archivo.size > R.maxLogoMB * 1048576) { mensajeLogo("El archivo pesa más de " + R.maxLogoMB + " MB. Usa una versión más liviana.", true); return; }
    mensajeLogo("Cargando " + archivo.name + "…");
    var p = info ? Promise.resolve(info.id) : C.huella(archivo);
    p.then(function (id) {
      estado.logo = { id: id, nombre: archivo.name, blob: archivo };
      return V.leerImagen(archivo).then(function (fuente) {
        estado.fuente = fuente;
        estado.quitarFondo = info && typeof info.quitarFondo === "boolean" ? info.quitarFondo : !V.tieneTransparencia(fuente);
        $("cz-fondo").checked = estado.quitarFondo;
        ajustes.hidden = false;
        mensajeLogo("Logo cargado: " + archivo.name);
        prepararLogo(); mostrarPrevia();
      }, function (err) {
        estado.fuente = null; ajustes.hidden = true; dibujar(true);
        mensajeLogo(err && err.message === "eps"
          ? "Recibimos tu archivo EPS y lo enviaremos con la cotización, pero no podemos mostrarlo en la vista previa. Si quieres verlo, sube también una versión PNG."
          : "Guardamos tu archivo, pero no pudimos mostrarlo en la vista previa. Prueba con PNG o JPG.", false);
      });
    });
  }
  inLogo.addEventListener("change", function () { cargarLogo(inLogo.files[0]); });
  var zona = document.querySelector(".subir");
  ["dragenter", "dragover"].forEach(function (ev) { zona.addEventListener(ev, function (e) { e.preventDefault(); zona.classList.add("encima"); }); });
  ["dragleave", "drop"].forEach(function (ev) { zona.addEventListener(ev, function (e) { e.preventDefault(); zona.classList.remove("encima"); }); });
  zona.addEventListener("drop", function (e) { var f = e.dataTransfer.files[0]; if (f) cargarLogo(f); });

  var ult = C.ultimoLogo(), btnUlt = $("cz-ultimo");
  if (ult) {
    C.leerArchivo(ult.id).then(function (a) {
      if (!a || !a.blob) return;
      btnUlt.hidden = false; btnUlt.textContent = "Usar mi logo anterior (" + ult.nombre + ")";
      btnUlt.onclick = function () { cargarLogo(new File([a.blob], ult.nombre, { type: a.tipo }), ult); btnUlt.hidden = true; };
    });
  }

  var logoListo = null;
  function prepararLogo() {
    logoListo = estado.fuente ? V.procesarLogo(estado.fuente, {
      quitarFondo: estado.quitarFondo,
      tinta: estado.tintas === "1" && estado.tintaColor ? estado.tintaColor.hex : null
    }) : null;
    if (estado.fuente && !logoListo) mensajeLogo("Al quitar el fondo blanco el logo quedó vacío. Desmarca «Quitar fondo blanco».", true);
    dibujar(!!estado.fuente);
  }
  $("cz-fondo").addEventListener("change", function (e) { estado.quitarFondo = e.target.checked; prepararLogo(); });
  $("cz-escala").addEventListener("input", function (e) { estado.ajuste.escala = Number(e.target.value) / 100; dibujar(); });
  $("cz-posy").addEventListener("input", function (e) { estado.ajuste.posY = Number(e.target.value) / 100; dibujar(); });

  // ---------- Vista previa ----------
  var pendiente = false;
  function dibujar(mostrar) {
    if (mostrar) mostrarPrevia();
    if (pendiente) return; pendiente = true;
    requestAnimationFrame(function () {
      pendiente = false;
      V.dibujar(lienzo, opcionesDibujo());
    });
  }
  // Fotos por color (y foto base de productos con vista previa por foto)
  var fotos = {};
  function foto(url) {
    if (!url) return null;
    if (fotos[url]) return fotos[url].complete ? fotos[url] : null;
    var im = new Image(); im.decoding = "async";
    im.onload = function () { dibujar(); };
    im.src = url; fotos[url] = im;
    return null;
  }
  function opcionesDibujo() {
    var o = { forma: prod.forma, color: estado.color, logo: logoListo, nombre: prod.nombre, ajuste: estado.ajuste, variante: estado.variante };
    if (prod.forma.tipo === "foto") o.foto = foto(estado.color.foto || prod.forma.imagen);
    return o;
  }
  // Al elegir un color con foto propia, la galería muestra esa foto
  var fotoPrincipal = document.getElementById("foto-principal");
  function mostrarFotoColor(c) {
    if (!c.foto || !fotoPrincipal) return;
    fotoPrincipal.src = c.foto;
    var a = fotoPrincipal.closest("a"); if (a && c.fotoGrande) a.href = c.fotoGrande;
    fotoPrincipal.alt = prod.nombre + " en " + c.nombre;
    document.querySelectorAll(".miniatura").forEach(function (x) { x.classList.remove("activa"); x.removeAttribute("aria-current"); });
  }
  function verVista(cual) {
    var previaSel = cual === "previa";
    previa.hidden = !previaSel; galeria.hidden = previaSel;
    if (tabsVisor) tabsVisor.querySelectorAll("button").forEach(function (b) { b.setAttribute("aria-pressed", b.dataset.vista === cual ? "true" : "false"); });
  }
  var interactuo = false;
  function mostrarPrevia() { interactuo = true; if (!movil.matches) verVista("previa"); }
  if (tabsVisor) {
    tabsVisor.hidden = false;
    tabsVisor.querySelectorAll("button").forEach(function (b) { b.addEventListener("click", function () { verVista(b.dataset.vista); dibujar(); }); });
  }
  // En pantallas angostas la vista previa vive dentro del cotizador, junto a los controles
  var movil = matchMedia("(max-width: 820px)"), lugarMovil = $("cz-previa-movil"), lugarEscritorio = previa.parentNode;
  function ubicar() {
    if (movil.matches) { lugarMovil.appendChild(previa); previa.hidden = false; galeria.hidden = false; if (tabsVisor) tabsVisor.hidden = true; }
    else { lugarEscritorio.appendChild(previa); if (tabsVisor) tabsVisor.hidden = false; verVista(interactuo ? "previa" : "fotos"); }
  }
  movil.addEventListener ? movil.addEventListener("change", ubicar) : movil.addListener(ubicar);
  ubicar();

  $("cz-descargar").addEventListener("click", function () {
    lienzo.toBlob(function (b) {
      var a = document.createElement("a"); a.href = URL.createObjectURL(b);
      a.download = "vista-previa-" + prod.slug + ".jpg"; document.body.appendChild(a); a.click();
      setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
    }, "image/jpeg", 0.9);
  });

  // ---------- Agregar ----------
  var estadoMsg = $("cz-estado");
  function aviso(html, tipo) {
    var t = document.createElement("div"); t.className = "aviso-flotante" + (tipo ? " " + tipo : ""); t.setAttribute("role", "status");
    t.innerHTML = html; document.body.appendChild(t);
    t.querySelector(".aviso-flotante__cerrar").addEventListener("click", function () { t.remove(); });
    setTimeout(function () { t.classList.add("sale"); setTimeout(function () { t.remove(); }, 400); }, 9000);
  }
  $("cz-agregar").addEventListener("click", function () {
    fijarCantidad(inCant.value);
    if (window.MIVASO_VISTA_PREVIA) { estadoMsg.className = "form__estado ok"; estadoMsg.textContent = "Vista previa del panel: en el sitio publicado este botón agrega el producto a la cotización."; return; }
    var boton = this; boton.disabled = true;
    estadoMsg.className = "form__estado"; estadoMsg.textContent = "Guardando…";
    // Siempre se guarda la vista previa con los datos actuales
    V.dibujar(lienzo, opcionesDibujo());
    var conBlob = function (fn) {
      // Si alguna imagen viene de otro dominio el navegador no deja exportar el lienzo: se sigue sin vista previa
      try { lienzo.toBlob(fn, "image/jpeg", 0.88); } catch (e) { fn(null); }
    };
    conBlob(function (blobPrevia) {
      var id = estado.editando || C.nuevoId(), previewId = blobPrevia ? "prev_" + id + "_" + Date.now().toString(36) : null;
      var guardados = blobPrevia ? [C.guardarArchivo(previewId, blobPrevia, { nombre: "vista-previa.jpg" })] : [];
      if (estado.logo) {
        guardados.push(C.guardarArchivo(estado.logo.id, estado.logo.blob, { nombre: estado.logo.nombre }));
        C.fijarUltimoLogo({ id: estado.logo.id, nombre: estado.logo.nombre });
      }
      Promise.all(guardados).then(function () {
        var anterior = estado.editando ? C.items().filter(function (x) { return x.id === id; })[0] : null;
        var item = {
          id: id, slug: prod.slug, nombre: prod.nombre, color: { id: estado.color.id, nombre: estado.color.nombre, hex: estado.color.hex, stock: estado.color.stock },
          variante: estado.variante, cantidad: estado.cantidad, tintas: estado.tintas, tintasNombre: CAT.tintas[estado.tintas],
          tintaColor: estado.tintas === "1" && estado.tintaColor ? { nombre: estado.tintaColor.nombre, hex: estado.tintaColor.hex } : null,
          logoId: estado.logo ? estado.logo.id : null, logoNombre: estado.logo ? estado.logo.nombre : null,
          ajuste: { escala: estado.ajuste.escala, posY: estado.ajuste.posY, quitarFondo: estado.quitarFondo },
          previewId: previewId, fecha: Date.now()
        };
        if (anterior) { C.reemplazar(item); if (anterior.previewId && anterior.previewId !== previewId) C.borrarArchivo(anterior.previewId); }
        else { C.agregar(item); window.mvEvento && window.mvEvento("agregar_cotizacion", { items: [{ item_id: prod.slug, item_name: prod.nombre, item_variant: estado.color.nombre, quantity: estado.cantidad }], con_logo: !!estado.logo }); }
        var falta = R.minimo - C.items().filter(function (x) { return x.slug === prod.slug; }).reduce(function (a, x) { return a + x.cantidad; }, 0);
        estadoMsg.className = "form__estado ok";
        estadoMsg.textContent = (anterior ? "Cambios guardados." : "Agregado a tu cotización.") + (falta > 0 ? " Te faltan " + C.formatoN(falta) + " unidades para el mínimo de este producto." : "");
        aviso('<strong>' + (anterior ? "Cambios guardados" : "Agregado a tu cotización") + '</strong><span>' + prod.nombre + " · " + estado.color.nombre + " · " + C.formatoN(estado.cantidad) + ' u.</span>' +
          '<div class="aviso-flotante__acciones"><a class="btn" href="' + caja.dataset.carro + '">Ver mi cotización</a><button type="button" class="btn btn--borde aviso-flotante__cerrar">Seguir cotizando</button></div>');
        if (anterior) { estado.editando = null; $("cz-agregar").textContent = "Agregar a mi cotización"; history.replaceState(null, "", location.pathname); }
        notaCantidad();
      }).catch(function () {
        estadoMsg.className = "form__estado mal";
        estadoMsg.textContent = "No pudimos guardar en este navegador. Revisa que no esté en modo privado o escríbenos por WhatsApp.";
      }).then(function () { boton.disabled = false; });
    });
  });

  // ---------- Editar un ítem existente (?editar=id) ----------
  var m = location.search.match(/[?&]editar=([\w-]+)/);
  var it = m && C.items().filter(function (x) { return x.id === m[1] && x.slug === prod.slug; })[0];
  if (it) {
    estado.editando = it.id;
    var c = prod.colores.filter(function (x) { return x.id === it.color.id; })[0];
    if (c) { estado.color = c; var r = cajaColores.querySelector('input[value="' + c.id + '"]'); if (r) r.checked = true; }
    if (it.variante && selVar) { selVar.value = it.variante; estado.variante = it.variante; }
    estado.tintas = it.tintas; var rt = cajaTintas.querySelector('input[value="' + it.tintas + '"]'); if (rt) rt.checked = true;
    if (it.tintaColor) {
      var tc = CAT.tintaColores.filter(function (x) { return x.hex.toLowerCase() === it.tintaColor.hex.toLowerCase(); })[0];
      estado.tintaColor = tc || { id: "otro", nombre: it.tintaColor.nombre, hex: it.tintaColor.hex }; estado.tintaManual = true;
      var rc = cajaTC.querySelector('input[value="' + (tc ? tc.id : "otro") + '"]'); if (rc) rc.checked = true;
      if (!tc) { $("cz-tinta-otro").value = it.tintaColor.hex; pintarOtro(it.tintaColor.hex); }
    }
    estado.ajuste = { escala: it.ajuste.escala, posY: it.ajuste.posY };
    $("cz-escala").value = Math.round(it.ajuste.escala * 100); $("cz-posy").value = Math.round(it.ajuste.posY * 100);
    fijarCantidad(it.cantidad);
    $("cz-agregar").textContent = "Guardar cambios";
    if (it.logoId) C.leerArchivo(it.logoId).then(function (a) {
      if (a && a.blob) cargarLogo(new File([a.blob], it.logoNombre || "logo", { type: a.tipo }), { id: it.logoId, quitarFondo: it.ajuste.quitarFondo });
    });
    caja.scrollIntoView();
  }

  mostrarTintaColor(); autoTinta(); notaCantidad(); fijarCantidad(estado.cantidad); dibujar();
})();
