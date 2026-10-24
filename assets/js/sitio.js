/* Mi Vaso — comportamiento general del sitio */
(function () {
  "use strict";
  document.documentElement.classList.add("js");

  // Al abrir los archivos directo desde el computador (file://), las carpetas no cargan index.html solas.
  if (location.protocol === "file:") {
    document.querySelectorAll('a[href]').forEach(function (a) {
      var h = a.getAttribute("href");
      if (/^(https?:|mailto:|tel:|#)/.test(h)) return;
      a.setAttribute("href", h.replace(/\/(\?|#|$)/, "/index.html$1").replace(/^\.\/$/, "index.html"));
    });
  }

  // Medición (Google Analytics 4 / Google Ads). Si no hay etiqueta configurada, no hace nada.
  window.mvEvento = function (nombre, datos) {
    try { if (typeof window.gtag === "function") window.gtag("event", nombre, datos || {}); } catch (e) {}
  };
  window.mvConversion = function (tipo, datos) {
    var destino = (window.MIVASO_CONVERSIONES || {})[tipo];
    if (destino) window.mvEvento("conversion", Object.assign({ send_to: destino }, datos || {}));
  };
  document.addEventListener("click", function (ev) {
    var a = ev.target.closest && ev.target.closest("a[href]");
    if (!a) return;
    var h = a.getAttribute("href");
    if (/wa\.me|whatsapp/i.test(h)) { window.mvEvento("contacto_whatsapp", { link_url: h }); window.mvConversion("whatsapp"); }
    else if (/^tel:/i.test(h)) window.mvEvento("contacto_telefono", { link_url: h });
    else if (/^mailto:/i.test(h)) window.mvEvento("contacto_correo", { link_url: h });
  });

  // Menú móvil
  var btn = document.querySelector(".menu-btn"), nav = document.getElementById("menu");
  if (btn && nav) {
    btn.addEventListener("click", function () {
      var abierto = nav.classList.toggle("abierto");
      btn.setAttribute("aria-expanded", abierto ? "true" : "false");
    });
    document.addEventListener("keydown", function (ev) {
      if (ev.key === "Escape" && nav.classList.contains("abierto")) { nav.classList.remove("abierto"); btn.setAttribute("aria-expanded", "false"); btn.focus(); }
    });
  }
  document.querySelectorAll(".sub-btn").forEach(function (b) {
    b.addEventListener("click", function () {
      var li = b.closest(".has-sub"), abierto = li.classList.toggle("abierto");
      b.setAttribute("aria-expanded", abierto ? "true" : "false");
    });
  });

  // Galería de producto
  var foto = document.getElementById("foto-principal");
  if (foto) {
    var enlace = foto.closest("a");
    document.querySelectorAll(".miniatura").forEach(function (m) {
      m.addEventListener("click", function () {
        foto.src = m.dataset.src;
        if (enlace) enlace.href = m.dataset.full;
        document.querySelectorAll(".miniatura").forEach(function (x) { x.classList.remove("activa"); x.removeAttribute("aria-current"); });
        m.classList.add("activa"); m.setAttribute("aria-current", "true");
      });
    });
  }

  // Pestañas de la ficha
  document.querySelectorAll("[data-tabs]").forEach(function (cont) {
    var tabs = Array.prototype.slice.call(cont.querySelectorAll('[role="tab"]'));
    function mostrar(t, foco) {
      tabs.forEach(function (x) {
        var sel = x === t;
        x.setAttribute("aria-selected", sel ? "true" : "false");
        x.tabIndex = sel ? 0 : -1;
        document.getElementById(x.getAttribute("aria-controls")).hidden = !sel;
      });
      if (foco) t.focus();
    }
    tabs.forEach(function (t, i) {
      t.addEventListener("click", function () { mostrar(t); });
      t.addEventListener("keydown", function (ev) {
        var d = ev.key === "ArrowRight" ? 1 : ev.key === "ArrowLeft" ? -1 : 0;
        if (d) { ev.preventDefault(); mostrar(tabs[(i + d + tabs.length) % tabs.length], true); }
      });
    });
    cont.classList.add("listo");
    mostrar(tabs[0]);
  });

  // Formularios de contacto
  window.MiVaso = window.MiVaso || {};
  window.MiVaso.marcarTiempo = function (form) {
    var ts = form.querySelector('input[name="ts"]');
    if (ts) ts.value = String(Math.floor(Date.now() / 1000));
  };
  window.MiVaso.validar = function (form, campos) {
    var primero = null;
    campos.forEach(function (el) {
      var c = el.closest(".campo"), ok = el.checkValidity() && (!el.required || el.value.trim() !== "");
      if (c) c.classList.toggle("error", !ok);
      el.setAttribute("aria-invalid", ok ? "false" : "true");
      if (!ok && !primero) primero = el;
    });
    if (primero) primero.focus();
    return !primero;
  };
  window.MiVaso.enviar = function (form, datos, estado, boton, alTerminar) {
    var txt = boton.textContent;
    boton.disabled = true; boton.textContent = "Enviando…";
    estado.className = "form__estado"; estado.textContent = "";
    fetch(form.dataset.endpoint, { method: "POST", body: datos, headers: { "Accept": "application/json" } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: "El servidor no respondió correctamente." }; }); })
      .then(function (res) {
        if (res.ok) { estado.className = "form__estado ok"; estado.textContent = res.mensaje || "¡Mensaje enviado! Te responderemos a la brevedad."; if (alTerminar) alTerminar(); }
        else { estado.className = "form__estado mal"; estado.textContent = res.error || "No pudimos enviar el mensaje. Escríbenos por WhatsApp al +56 9 5043 5981."; }
      })
      .catch(function () {
        estado.className = "form__estado mal";
        estado.textContent = "No pudimos enviar el mensaje. Revisa tu conexión o escríbenos por WhatsApp al +56 9 5043 5981.";
      })
      .then(function () { boton.disabled = false; boton.textContent = txt; });
  };

  document.querySelectorAll('form.form').forEach(function (form) {
    window.MiVaso.marcarTiempo(form);
    form.addEventListener("submit", function (ev) {
      ev.preventDefault();
      var campos = Array.prototype.slice.call(form.querySelectorAll("input:not([type=hidden]):not([name=web]), textarea"));
      var estado = form.querySelector(".form__estado");
      if (!window.MiVaso.validar(form, campos)) { estado.className = "form__estado mal"; estado.textContent = "Revisa los campos marcados: nombre, un correo válido y tu mensaje son obligatorios."; return; }
      window.MiVaso.enviar(form, new FormData(form), estado, form.querySelector('[type="submit"]'), function () {
        form.reset(); window.MiVaso.marcarTiempo(form);
        window.mvEvento("generate_lead", { form_name: "contacto" }); window.mvConversion("contacto");
      });
    });
  });

  // Aviso tras envío sin JavaScript (enviar.php redirige con ?enviado=1)
  if (/[?&]enviado=1/.test(location.search)) {
    var est = document.querySelector(".form__estado");
    if (est) { est.className = "form__estado ok"; est.textContent = "¡Mensaje enviado! Te responderemos a la brevedad."; }
  }
})();

/* Secciones del constructor de páginas: banners animados, animaciones al aparecer, cuenta regresiva, cifras y galería. */
(function () {
  "use strict";
  var quieto = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // ---------- Banner / carrusel ----------
  document.querySelectorAll("[data-banner]").forEach(function (b) {
    var slides = Array.prototype.slice.call(b.querySelectorAll(".bnr__slide"));
    var puntos = Array.prototype.slice.call(b.querySelectorAll(".bnr__punto"));
    var pausa = b.querySelector(".bnr__pausa");
    var i = 0, t = null, pausado = quieto, encima = false, ms = parseInt(b.dataset.intervalo, 10) || 6000;
    function ir(n) {
      var nuevo = (n + slides.length) % slides.length, dir = n > i ? 1 : -1;
      if (nuevo === i) return;
      var e = slides[nuevo], v = slides[i];
      // colocar la entrante al lado correcto sin animación, y luego animar
      e.style.transition = "none"; e.classList.toggle("antes", dir < 0); void e.offsetWidth; e.style.transition = "";
      v.classList.remove("activa"); v.classList.toggle("antes", dir > 0); v.setAttribute("aria-hidden", "true");
      e.classList.add("activa"); e.classList.remove("antes"); e.removeAttribute("aria-hidden");
      i = nuevo;
      puntos.forEach(function (p, k) { if (k === i) p.setAttribute("aria-current", "true"); else p.removeAttribute("aria-current"); });
      programar();
    }
    function programar() { clearTimeout(t); if (!pausado && !encima) t = setTimeout(function () { ir(i + 1); }, ms); }
    var ant = b.querySelector(".bnr__flecha--ant"), sig = b.querySelector(".bnr__flecha--sig");
    if (ant) ant.addEventListener("click", function () { ir(i - 1); });
    if (sig) sig.addEventListener("click", function () { ir(i + 1); });
    puntos.forEach(function (p, k) { p.addEventListener("click", function () { ir(k); }); });
    function pintarPausa() {
      if (!pausa) return;
      pausa.setAttribute("aria-pressed", pausado ? "true" : "false");
      pausa.setAttribute("aria-label", pausado ? "Reanudar el carrusel" : "Pausar el carrusel");
      pausa.innerHTML = pausado ? '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M8 5l11 7-11 7z" fill="currentColor"/></svg>' : '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M7 5h3v14H7zM14 5h3v14h-3z" fill="currentColor"/></svg>';
    }
    if (pausa) pausa.addEventListener("click", function () { pausado = !pausado; pintarPausa(); programar(); });
    b.addEventListener("mouseenter", function () { encima = true; clearTimeout(t); });
    b.addEventListener("mouseleave", function () { encima = false; programar(); });
    b.addEventListener("focusin", function (e) { var fv = false; try { fv = e.target.matches(":focus-visible"); } catch (x) {} if (fv) { encima = true; clearTimeout(t); } });
    b.addEventListener("focusout", function () { encima = false; programar(); });
    // deslizar con el dedo
    var x0 = null;
    b.addEventListener("touchstart", function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    b.addEventListener("touchend", function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) ir(i + (dx < 0 ? 1 : -1)); x0 = null; });
    document.addEventListener("visibilitychange", function () { if (document.hidden) clearTimeout(t); else programar(); });
    pintarPausa(); programar();
  });

  // ---------- Animación al aparecer + cifras ----------
  function contar(el) {
    var fin = parseInt(el.dataset.contar, 10) || 0, t0 = null, dur = 1400;
    if (quieto) return;
    function paso(t) { if (!t0) t0 = t; var p = Math.min(1, (t - t0) / dur), v = Math.round(fin * (1 - Math.pow(1 - p, 3))); el.textContent = v.toLocaleString("es-CL"); if (p < 1) requestAnimationFrame(paso); }
    el.textContent = "0"; requestAnimationFrame(paso);
  }
  var animables = document.querySelectorAll(".anima, [data-contar]");
  if ("IntersectionObserver" in window) {
    var io = new IntersectionObserver(function (ents) {
      ents.forEach(function (en) {
        if (!en.isIntersecting) return;
        io.unobserve(en.target);
        if (en.target.hasAttribute("data-contar")) contar(en.target); else en.target.classList.add("visible");
      });
    }, { rootMargin: "0px 0px -8% 0px", threshold: 0.08 });
    animables.forEach(function (el) { io.observe(el); });
  } else animables.forEach(function (el) { el.classList.add("visible"); });

  // ---------- Cuenta regresiva ----------
  document.querySelectorAll("[data-cuenta]").forEach(function (c) {
    var fin = parseInt(c.dataset.cuenta, 10), dos = function (n) { return (n < 10 ? "0" : "") + n; };
    function tic() {
      var r = Math.max(0, Math.floor((fin - Date.now()) / 1000));
      if (r <= 0) { c.outerHTML = '<p class="cuenta--fin">' + (c.dataset.fin || "").replace(/[<>&]/g, "") + "</p>"; return; }
      c.querySelector("[data-d]").textContent = Math.floor(r / 86400);
      c.querySelector("[data-h]").textContent = dos(Math.floor(r % 86400 / 3600));
      c.querySelector("[data-m]").textContent = dos(Math.floor(r % 3600 / 60));
      c.querySelector("[data-s]").textContent = dos(r % 60);
      setTimeout(tic, 1000);
    }
    tic();
  });

  // ---------- Galería: ampliar foto ----------
  var visor = null;
  document.addEventListener("click", function (e) {
    var a = e.target.closest("[data-ampliar]");
    if (!a || !window.HTMLDialogElement) return;
    e.preventDefault();
    if (!visor) {
      visor = document.createElement("dialog"); visor.className = "visor-foto";
      visor.innerHTML = '<button type="button" class="visor-foto__x" aria-label="Cerrar">×</button><img alt=""><p></p>';
      document.body.appendChild(visor);
      visor.querySelector("button").onclick = function () { visor.close(); };
      visor.addEventListener("click", function (ev) { if (ev.target === visor) visor.close(); });
    }
    var img = a.querySelector("img");
    visor.querySelector("img").src = a.href; visor.querySelector("img").alt = img ? img.alt : "";
    visor.querySelector("p").textContent = img ? img.alt : "";
    visor.showModal();
  });
})();

/* Columnas desplegables: opción «al abrir una, cerrar las demás» */
document.querySelectorAll(".desp[data-uno]").forEach(function (g) {
  g.addEventListener("toggle", function (e) {
    if (!e.target.open) return;
    g.querySelectorAll("details[open]").forEach(function (d) { if (d !== e.target) d.open = false; });
  }, true);
});
