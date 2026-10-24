/* Mi Vaso — carro de cotización compartido entre páginas.
   Los datos livianos van en localStorage; los archivos (logos y vistas previas) en IndexedDB. */
(function () {
  "use strict";
  var CLAVE = "mivaso_cotizacion_v1";
  var memoria = { items: [], archivos: {} };   // respaldo si el navegador bloquea el almacenamiento

  // ---------- IndexedDB ----------
  var dbProm = null;
  function db() {
    if (dbProm) return dbProm;
    dbProm = new Promise(function (ok, mal) {
      try {
        var r = indexedDB.open("mivaso", 1);
        r.onupgradeneeded = function () { r.result.createObjectStore("archivos"); };
        r.onsuccess = function () { ok(r.result); };
        r.onerror = function () { mal(r.error); };
      } catch (e) { mal(e); }
    });
    return dbProm;
  }
  function tx(modo, fn) {
    return db().then(function (d) {
      return new Promise(function (ok, mal) {
        var t = d.transaction("archivos", modo), st = t.objectStore("archivos"), res = fn(st);
        t.oncomplete = function () { ok(res && "result" in res ? res.result : undefined); };
        t.onerror = function () { mal(t.error); };
      });
    });
  }
  function guardarArchivo(id, blob, meta) {
    var v = { blob: blob, nombre: (meta && meta.nombre) || id, tipo: blob.type || (meta && meta.tipo) || "" };
    return tx("readwrite", function (st) { st.put(v, id); }).catch(function () { memoria.archivos[id] = v; });
  }
  function leerArchivo(id) {
    if (memoria.archivos[id]) return Promise.resolve(memoria.archivos[id]);
    return tx("readonly", function (st) { return st.get(id); }).catch(function () { return null; });
  }
  function borrarArchivo(id) {
    delete memoria.archivos[id];
    return tx("readwrite", function (st) { st.delete(id); }).catch(function () {});
  }

  // ---------- items ----------
  function items() {
    try { var v = JSON.parse(localStorage.getItem(CLAVE) || "[]"); return Array.isArray(v) ? v : []; }
    catch (e) { return memoria.items.slice(); }
  }
  function guardar(lista) {
    memoria.items = lista.slice();
    try { localStorage.setItem(CLAVE, JSON.stringify(lista)); } catch (e) {}
    actualizarContador();
    try { window.dispatchEvent(new CustomEvent("mivaso:cotizacion")); } catch (e) {}
  }
  function agregar(item) { var l = items(); l.push(item); guardar(l); return item; }
  function reemplazar(item) { guardar(items().map(function (x) { return x.id === item.id ? item : x; })); }
  function quitar(id) {
    var l = items(), it = l.filter(function (x) { return x.id === id; })[0];
    var resto = l.filter(function (x) { return x.id !== id; });
    guardar(resto);
    if (it) {
      borrarArchivo(it.previewId);
      // el logo puede estar compartido con otros ítems
      if (it.logoId && !resto.some(function (x) { return x.logoId === it.logoId; })) borrarArchivo(it.logoId);
    }
  }
  function vaciar() {
    var l = items();
    guardar([]);
    l.forEach(function (x) { borrarArchivo(x.previewId); if (x.logoId) borrarArchivo(x.logoId); });
  }

  // Identificador estable de un archivo (para no guardar dos veces el mismo logo)
  function huella(archivo) {
    if (window.crypto && crypto.subtle && archivo.arrayBuffer) {
      return archivo.arrayBuffer().then(function (b) { return crypto.subtle.digest("SHA-256", b); }).then(function (h) {
        return "logo_" + Array.prototype.map.call(new Uint8Array(h).slice(0, 12), function (x) { return ("0" + x.toString(16)).slice(-2); }).join("");
      }).catch(function () { return simple(); });
    }
    return Promise.resolve(simple());
    function simple() { return "logo_" + [archivo.name, archivo.size, archivo.lastModified].join("_").replace(/[^a-z0-9_]/gi, ""); }
  }
  function nuevoId() { return "it_" + Date.now().toString(36) + Math.random().toString(36).slice(2, 8); }

  // Último logo usado (para no volver a subirlo en cada producto)
  function ultimoLogo() { try { return JSON.parse(localStorage.getItem(CLAVE + "_ultimo_logo") || "null"); } catch (e) { return null; } }
  function fijarUltimoLogo(info) { try { localStorage.setItem(CLAVE + "_ultimo_logo", JSON.stringify(info)); } catch (e) {} }

  // ---------- contador en el menú ----------
  function actualizarContador() {
    var n = items().length;
    document.querySelectorAll(".btn--nav").forEach(function (b) {
      if (!b.dataset.textoOriginal) b.dataset.textoOriginal = b.textContent;
      b.textContent = n ? "MI COTIZACIÓN (" + n + ")" : b.dataset.textoOriginal;
    });
  }
  window.addEventListener("storage", function (e) { if (e.key === CLAVE) actualizarContador(); });
  if (document.readyState !== "loading") actualizarContador(); else document.addEventListener("DOMContentLoaded", actualizarContador);

  function formatoN(n) { return Number(n || 0).toLocaleString("es-CL"); }
  function producto(slug) { return (window.MIVASO_CATALOGO ? window.MIVASO_CATALOGO.productos : []).filter(function (p) { return p.slug === slug; })[0]; }

  window.MiVasoCotizacion = {
    items: items, agregar: agregar, reemplazar: reemplazar, quitar: quitar, vaciar: vaciar,
    guardarArchivo: guardarArchivo, leerArchivo: leerArchivo, borrarArchivo: borrarArchivo, huella: huella, nuevoId: nuevoId,
    ultimoLogo: ultimoLogo, fijarUltimoLogo: fijarUltimoLogo, formatoN: formatoN, producto: producto,
    actualizarContador: actualizarContador
  };
})();
