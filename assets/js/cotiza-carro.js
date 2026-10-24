/* Mi Vaso — página "Mi cotización": revisar, completar datos y enviar */
(function () {
  "use strict";
  var raiz = document.getElementById("carro");
  if (!raiz || !window.MiVasoCotizacion || !window.MIVASO_CATALOGO) return;
  var C = window.MiVasoCotizacion, CAT = window.MIVASO_CATALOGO, R = CAT.reglas;
  var $ = function (id) { return document.getElementById(id); };
  var lista = $("carro-lista"), vacio = $("carro-vacio"), conItems = $("carro-con-items"), form = $("carro-form");
  var urls = [];
  raiz.hidden = false;
  var ns = document.querySelector(".cotiza-sin-js"); if (ns) ns.remove();
  window.MiVaso && window.MiVaso.marcarTiempo(form);

  // Catálogo para el estado vacío
  var grilla = $("carro-catalogo");
  CAT.productos.forEach(function (p) {
    var a = document.createElement("a"); a.className = "prod"; a.href = "../" + p.slug + "/";
    a.innerHTML = '<span class="prod__img"><img src="' + p.img + '" alt="" width="300" height="300" loading="lazy"></span><span class="prod__nombre"></span>';
    a.querySelector(".prod__nombre").textContent = p.nombre;
    grilla.appendChild(a);
  });

  function totales() {
    var por = {};
    C.items().forEach(function (x) { por[x.slug] = (por[x.slug] || 0) + x.cantidad; });
    return por;
  }
  function problemas() {
    var errores = [], por = totales();
    Object.keys(por).forEach(function (slug) {
      if (por[slug] < R.minimo) {
        var p = C.producto(slug);
        errores.push((p ? p.nombre : slug) + ": suma " + C.formatoN(por[slug]) + " unidades; el mínimo por producto es " + C.formatoN(R.minimo) + ".");
      }
    });
    return errores;
  }

  function pintar() {
    urls.forEach(URL.revokeObjectURL); urls = [];
    var its = C.items();
    vacio.hidden = its.length > 0; conItems.hidden = its.length === 0;
    lista.innerHTML = "";
    its.forEach(function (x) {
      var li = document.createElement("li"); li.className = "linea";
      li.innerHTML =
        '<div class="linea__img"><img alt="" width="180" height="216"></div>' +
        '<div class="linea__info">' +
          '<h3 class="linea__n"><a></a></h3>' +
          '<ul class="linea__datos">' +
            '<li><span class="punto" style="--c:' + x.color.hex + '"></span><span class="d-color"></span>' + (x.color.stock ? ' <span class="etq stock">En stock</span>' : "") + '</li>' +
            '<li class="d-tintas"></li><li class="d-logo"></li>' + (x.variante ? '<li class="d-var"></li>' : "") +
          "</ul>" +
          '<div class="linea__acciones">' +
            '<label class="linea__cant"><span>Cantidad</span><input type="number" min="' + R.paso + '" step="' + R.paso + '" inputmode="numeric"></label>' +
            '<a class="btn btn--borde btn--chico d-editar">Editar diseño</a>' +
            '<button type="button" class="enlace-quitar">Quitar</button>' +
          "</div>" +
        "</div>";
      var a = li.querySelector(".linea__n a"); a.textContent = x.nombre; a.href = "../" + x.slug + "/";
      li.querySelector(".d-color").textContent = x.color.nombre;
      li.querySelector(".d-tintas").textContent = "Impresión: " + x.tintasNombre + (x.tintaColor ? " · tinta " + x.tintaColor.nombre.toLowerCase() : "");
      li.querySelector(".d-logo").textContent = x.logoNombre ? "Logo: " + x.logoNombre : "Sin logo (lo enviarás después)";
      if (x.variante) li.querySelector(".d-var").textContent = x.variante;
      li.querySelector(".d-editar").href = "../" + x.slug + "/?editar=" + encodeURIComponent(x.id) + "#cotiza";
      var inp = li.querySelector("input"); inp.value = x.cantidad;
      inp.setAttribute("aria-label", "Cantidad de " + x.nombre + " en " + x.color.nombre);
      inp.addEventListener("change", function () {
        var min = R.paso;
        var v = Math.max(min, Math.min(1000000, Math.round(Number(inp.value) || 0)));
        inp.value = v; x.cantidad = v; C.reemplazar(x); resumen();
      });
      li.querySelector(".enlace-quitar").addEventListener("click", function () { C.quitar(x.id); pintar(); });
      C.leerArchivo(x.previewId).then(function (f) {
        if (f && f.blob) { var u = URL.createObjectURL(f.blob); urls.push(u); var im = li.querySelector("img"); im.src = u; im.alt = "Vista previa de " + x.nombre; }
      });
      lista.appendChild(li);
    });
    resumen();
  }

  function resumen() {
    var its = C.items(), total = its.reduce(function (a, x) { return a + x.cantidad; }, 0);
    $("carro-total").textContent = its.length + (its.length === 1 ? " línea" : " líneas") + " · " + C.formatoN(total) + " unidades";
    var err = problemas(), caja = $("carro-problemas");
    caja.hidden = !err.length; caja.innerHTML = "";
    err.forEach(function (t) { var li = document.createElement("li"); li.textContent = t; caja.appendChild(li); });
  }

  // Fecha: desde mañana; aviso si es antes del plazo normal (5 días hábiles)
  var fecha = $("ct-fecha");
  function iso(d) { return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2) + "-" + ("0" + d.getDate()).slice(-2); }
  var hoy = new Date(); hoy.setHours(0, 0, 0, 0);
  var manana = new Date(hoy); manana.setDate(manana.getDate() + 1); fecha.min = iso(manana);
  var habil = new Date(hoy), n = 0; while (n < 5) { habil.setDate(habil.getDate() + 1); if (habil.getDay() % 6) n++; }
  fecha.addEventListener("change", function () {
    var nota = $("ct-fecha-nota"), d = fecha.value ? new Date(fecha.value + "T00:00:00") : null;
    if (d && d < habil) { nota.textContent = "Es un plazo ajustado: la producción normal toma hasta 5 días hábiles. Te confirmaremos si podemos cumplirlo."; nota.className = "cz-ayuda cz-alerta"; }
    else { nota.textContent = "Pedidos de 500 a 5.000 unidades: hasta 5 días hábiles. Sobre 10.000 coordinamos fechas contigo."; nota.className = "cz-ayuda"; }
  });
  $("ct-entrega").addEventListener("change", function (e) { $("ct-comuna-campo").hidden = e.target.value !== "Despacho"; });

  // Envío
  form.addEventListener("submit", function (ev) {
    ev.preventDefault();
    var estado = $("carro-estado"), boton = form.querySelector('[type="submit"]');
    var its = C.items();
    if (!its.length) return;
    var err = problemas();
    if (err.length) { estado.className = "form__estado mal"; estado.textContent = "Revisa las cantidades marcadas arriba antes de enviar."; $("carro-problemas").scrollIntoView({ block: "center" }); return; }
    var campos = Array.prototype.slice.call(form.querySelectorAll("input:not([type=hidden]):not([name=web]), textarea, select"))
      .filter(function (el) { return !el.closest("[hidden]"); });
    if (!window.MiVaso.validar(form, campos)) { estado.className = "form__estado mal"; estado.textContent = "Completa los campos marcados: fecha, nombre, correo válido, teléfono y RUT."; return; }

    estado.className = "form__estado"; estado.textContent = "Preparando archivos…"; boton.disabled = true;
    var datos = new FormData(form), logos = {}, nLogo = 0, pesos = 0;
    Promise.all(its.map(function (x) {
      return Promise.all([C.leerArchivo(x.previewId), x.logoId ? C.leerArchivo(x.logoId) : null]);
    })).then(function (archivos) {
      var lineas = its.map(function (x, i) {
        var prev = archivos[i][0], logo = archivos[i][1], refLogo = null;
        if (prev && prev.blob) { datos.append("vista_" + i, prev.blob, "vista-previa-" + (i + 1) + ".jpg"); pesos += prev.blob.size; }
        if (logo && logo.blob) {
          if (!(x.logoId in logos)) { logos[x.logoId] = nLogo; datos.append("logo_" + nLogo, logo.blob, x.logoNombre || logo.nombre); pesos += logo.blob.size; nLogo++; }
          refLogo = logos[x.logoId];
        }
        return { slug: x.slug, nombre: x.nombre, color: x.color.nombre, colorStock: !!x.color.stock, variante: x.variante || "",
                 cantidad: x.cantidad, tintas: x.tintasNombre, tintaColor: x.tintaColor ? x.tintaColor.nombre + " " + x.tintaColor.hex : "",
                 logo: refLogo, logoNombre: x.logoNombre || "", vista: prev && prev.blob ? i : null };
      });
      if (pesos > R.maxEnvioMB * 1048576) throw { mensaje: "Los archivos suman " + (pesos / 1048576).toFixed(1) + " MB y el máximo es " + R.maxEnvioMB + " MB. Usa logos más livianos (PNG o JPG) e inténtalo de nuevo." };
      datos.append("lineas", JSON.stringify(lineas));
      estado.textContent = "Enviando cotización…";
      return fetch(form.dataset.endpoint, { method: "POST", body: datos, headers: { "Accept": "application/json" } })
        .then(function (r) { return r.json().catch(function () { return { ok: false, error: r.status === 413 ? "Los archivos son demasiado pesados para el servidor." : "El servidor no respondió correctamente." }; }); });
    }).then(function (res) {
      if (!res.ok) throw { mensaje: res.error };
      var unidades = its.reduce(function (a, x) { return a + x.cantidad; }, 0);
      window.mvEvento && window.mvEvento("generate_lead", { form_name: "cotizacion", transaction_id: res.folio, items: its.map(function (x) { return { item_id: x.slug, item_name: x.nombre, item_variant: x.color.nombre, quantity: x.cantidad }; }), unidades: unidades });
      window.mvConversion && window.mvConversion("cotizacion", { transaction_id: res.folio });
      C.vaciar(); pintar();
      $("carro-exito-folio").textContent = res.folio || "";
      $("carro-exito").hidden = false; conItems.hidden = true; vacio.hidden = true;
      $("carro-exito").scrollIntoView({ block: "start" });
      $("carro-exito").focus();
    }).catch(function (e) {
      estado.className = "form__estado mal";
      estado.textContent = (e && e.mensaje) || "No pudimos enviar la cotización. Revisa tu conexión o escríbenos por WhatsApp al +56 9 5043 5981.";
    }).then(function () { boton.disabled = false; });
  });

  window.addEventListener("storage", function (e) { if (e.key && e.key.indexOf("mivaso_cotizacion") === 0) pintar(); });
  pintar();
})();
