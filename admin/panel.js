/* Mi Vaso — utilidades del panel: API, avisos, biblioteca de medios y arrastrar y soltar. */
(function () {
  "use strict";
  var CSRF = (document.querySelector('meta[name="csrf"]') || {}).content || "";

  function api(accion, cuerpo) {
    return fetch("api.php?accion=" + encodeURIComponent(accion), {
      method: "POST", credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-CSRF": CSRF, "Accept": "application/json" },
      body: JSON.stringify(cuerpo || {})
    }).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: "El servidor respondió con un error (" + r.status + ")." }; });
    }, function () { return { ok: false, error: "Sin conexión con el servidor." }; });
  }

  function subir(archivo) {
    var fd = new FormData(); fd.append("archivo", archivo);
    return fetch("api.php?accion=subir", { method: "POST", credentials: "same-origin", headers: { "X-CSRF": CSRF }, body: fd })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: r.status === 413 ? "El archivo supera el límite del servidor." : "Error al subir (" + r.status + ")." }; }); },
            function () { return { ok: false, error: "Sin conexión con el servidor." }; });
  }

  function aviso(texto, mal) {
    var a = document.createElement("div"); a.className = "aviso-ed" + (mal ? " mal" : ""); a.setAttribute("role", "status"); a.textContent = texto;
    document.body.appendChild(a); setTimeout(function () { a.remove(); }, mal ? 7000 : 3200);
  }

  // ---------- Biblioteca de medios: subir o elegir una imagen ya subida ----------
  var dlg = null;
  function elegirMedio(opciones) {
    opciones = opciones || {};
    return new Promise(function (ok) {
      if (!dlg) {
        dlg = document.createElement("dialog"); dlg.className = "medios";
        dlg.innerHTML = '<div class="medios__cab"><strong>Imágenes y archivos</strong><button type="button" class="x" aria-label="Cerrar">×</button></div>' +
          '<div style="padding:16px 16px 0"><label class="subida" tabindex="0"><input type="file" hidden multiple><strong>Subir archivo</strong> o arrástralo aquí · JPG, PNG, WEBP o PDF (máx. 8 MB)</label><p class="ayuda medios__estado" aria-live="polite"></p></div>' +
          '<ul class="medios__grilla"></ul>';
        document.body.appendChild(dlg);
        dlg.querySelector(".x").onclick = function () { dlg.close(); };
      }
      var input = dlg.querySelector("input"), zona = dlg.querySelector(".subida"), grilla = dlg.querySelector(".medios__grilla"), est = dlg.querySelector(".medios__estado");
      var listo = false;
      function elegir(obj) { if (listo) return; listo = true; dlg.close(); ok(obj); }
      dlg.onclose = function () { if (!listo) { listo = true; ok(null); } };
      input.multiple = !!opciones.varios;
      input.accept = opciones.pdf ? ".pdf,application/pdf" : "image/jpeg,image/png,image/webp";
      function subirVarios(files) {
        files = Array.prototype.slice.call(files); if (!files.length) return;
        est.textContent = "Subiendo " + files.length + " archivo(s)…";
        var subidos = [];
        files.reduce(function (p, f) {
          return p.then(function () { return subir(f).then(function (r) { if (r.ok) subidos.push(r.archivo); else aviso(f.name + ": " + r.error, true); }); });
        }, Promise.resolve()).then(function () {
          est.textContent = "";
          if (subidos.length) elegir(opciones.varios ? subidos : subidos[0]);
        });
      }
      input.onchange = function () { subirVarios(input.files); input.value = ""; };
      zona.ondragover = function (e) { e.preventDefault(); zona.classList.add("encima"); };
      zona.ondragleave = function () { zona.classList.remove("encima"); };
      zona.ondrop = function (e) { e.preventDefault(); zona.classList.remove("encima"); subirVarios(e.dataTransfer.files); };
      zona.onkeydown = function (e) { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); input.click(); } };
      grilla.innerHTML = '<li class="ayuda">Cargando…</li>';
      fetch("api.php?accion=medios", { credentials: "same-origin" }).then(function (r) { return r.json(); }).then(function (r) {
        grilla.innerHTML = "";
        (r.medios || []).filter(function (m) { return opciones.pdf ? m.tipo === "pdf" : m.tipo !== "pdf"; }).forEach(function (m) {
          var li = document.createElement("li"), b = document.createElement("button"); b.type = "button";
          b.innerHTML = (m.tipo === "pdf" ? '<span style="font-size:2rem;line-height:3">PDF</span>' : '<img alt="" loading="lazy">') + "<span></span>";
          if (m.tipo !== "pdf") b.querySelector("img").src = m.mini;
          b.lastChild.textContent = m.nombre || m.src;
          b.onclick = function () { elegir(opciones.varios ? [m] : m); };
          li.appendChild(b); grilla.appendChild(li);
        });
        if (!grilla.children.length) grilla.innerHTML = '<li class="ayuda">Aún no hay archivos subidos.</li>';
      });
      dlg.showModal();
    });
  }

  // ---------- Arrastrar y soltar (mouse y pantallas táctiles) ----------
  // ordenable(lista, {asa: selector, alSoltar: fn(), grilla: bool})
  function ordenable(lista, op) {
    op = op || {};
    lista.addEventListener("pointerdown", function (ev) {
      var asa = ev.target.closest(op.asa || ".asa");
      if (!asa || !lista.contains(asa) || ev.button > 0) return;
      var el = asa.closest(op.item || "li");
      if (!el || el.parentNode !== lista) return;
      ev.preventDefault();
      arrastrar(el, lista, ev, op);
    });
  }
  function arrastrar(el, lista, ev, op, alInsertar) {
    var r = el.getBoundingClientRect(), dx = ev.clientX - r.left, dy = ev.clientY - r.top;
    var hueco = el.cloneNode(true); hueco.classList.add("hueco"); hueco.removeAttribute("id");
    hueco.style.height = r.height + "px";
    var nuevo = !el.parentNode;
    if (!nuevo) el.parentNode.insertBefore(hueco, el);
    var fantasma = el;
    if (nuevo) { lista.appendChild(hueco); }
    fantasma.classList.add("arrastrando");
    var st = fantasma.style;
    st.position = "fixed"; st.left = r.left + "px"; st.top = r.top + "px"; st.width = r.width + "px"; st.pointerEvents = "none"; st.zIndex = 1000;
    if (nuevo) document.body.appendChild(fantasma);
    function mover(e) {
      st.left = (e.clientX - dx) + "px"; st.top = (e.clientY - dy) + "px";
      var dentro = (function () { var lr = lista.getBoundingClientRect(); return e.clientX >= lr.left - 40 && e.clientX <= lr.right + 40 && e.clientY >= lr.top - 60 && e.clientY <= lr.bottom + 60; })();
      if (!dentro && nuevo) { if (hueco.parentNode) hueco.remove(); return; }
      var hijos = Array.prototype.filter.call(lista.children, function (c) { return c !== hueco && c !== fantasma; });
      var antes = null;
      for (var i = 0; i < hijos.length; i++) {
        var b = hijos[i].getBoundingClientRect();
        if (op.grilla ? (e.clientY < b.bottom && (e.clientY < b.top || e.clientX < b.left + b.width / 2)) : e.clientY < b.top + b.height / 2) { antes = hijos[i]; break; }
      }
      if (antes) lista.insertBefore(hueco, antes); else lista.appendChild(hueco);
      // desplazar el contenedor si el puntero está cerca del borde
      var sc = op.scroll; if (sc) { var sr = sc.getBoundingClientRect(); if (e.clientY < sr.top + 40) sc.scrollTop -= 12; else if (e.clientY > sr.bottom - 40) sc.scrollTop += 12; }
    }
    function soltar() {
      document.removeEventListener("pointermove", mover); document.removeEventListener("pointerup", soltar); document.removeEventListener("pointercancel", soltar);
      fantasma.classList.remove("arrastrando"); st.position = st.left = st.top = st.width = st.pointerEvents = st.zIndex = "";
      if (hueco.parentNode) {
        if (nuevo) { hueco.parentNode.replaceChild(alInsertar ? alInsertar() : fantasma, hueco); fantasma.remove(); }
        else hueco.parentNode.replaceChild(fantasma, hueco);
        if (op.alSoltar) op.alSoltar();
      } else if (nuevo) fantasma.remove();
    }
    document.addEventListener("pointermove", mover); document.addEventListener("pointerup", soltar); document.addEventListener("pointercancel", soltar);
    mover(ev);
  }
  // Arrastrar un elemento nuevo desde una paleta hacia una lista: crear() devuelve el nodo a insertar
  function desdePaleta(boton, lista, crear, op) {
    boton.addEventListener("pointerdown", function (ev) {
      if (ev.button > 0) return;
      var x0 = ev.clientX, y0 = ev.clientY, empezo = false;
      function mov(e) {
        if (!empezo && Math.abs(e.clientX - x0) + Math.abs(e.clientY - y0) > 6) {
          empezo = true; quitar();
          var fan = boton.cloneNode(true); fan.style.width = boton.offsetWidth + "px";
          var r = boton.getBoundingClientRect(); fan.style.left = r.left + "px"; fan.style.top = r.top + "px";
          arrastrar(fan, lista, e, Object.assign({}, op), crear);
        }
      }
      function quitar() { document.removeEventListener("pointermove", mov); document.removeEventListener("pointerup", quitar); }
      document.addEventListener("pointermove", mov); document.addEventListener("pointerup", quitar);
    });
  }

  window.Panel = { api: api, subir: subir, aviso: aviso, elegirMedio: elegirMedio, ordenable: ordenable, desdePaleta: desdePaleta };
})();
