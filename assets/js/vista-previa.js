/* Mi Vaso — vista previa del vaso/copa con el logo del cliente.
   Dibuja cada producto a escala (medidas en mm de la ficha) y "envuelve" el logo
   alrededor de la superficie curva. Todo ocurre en el navegador. */
(function () {
  "use strict";

  var W = 900, H = 1080;          // tamaño interno del lienzo
  var PISO = 880;                 // línea del piso (px)
  var K = 0.17;                   // achatamiento de las elipses (perspectiva)

  // ---------- utilidades de color ----------
  function rgb(hex) {
    var h = hex.replace("#", "");
    if (h.length === 3) h = h.split("").map(function (c) { return c + c; }).join("");
    return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
  }
  function rgba(hex, a) { var c = rgb(hex); return "rgba(" + c[0] + "," + c[1] + "," + c[2] + "," + a + ")"; }
  function mezcla(hex, otro, f) {
    var a = rgb(hex), b = rgb(otro);
    return "#" + [0, 1, 2].map(function (i) { return ("0" + Math.round(a[i] + (b[i] - a[i]) * f).toString(16)).slice(-2); }).join("");
  }
  function oscuro(hex, f) { return mezcla(hex, "#000000", f); }
  function claro(hex, f) { return mezcla(hex, "#ffffff", f); }

  // ---------- perfiles de las copas ----------
  // Medidos sobre las fotos reales de cada modelo: [altura mm desde el piso, radio mm].
  // "tallo" = pie + tallo (hasta donde empieza la copa); "cuerpo" = la copa hasta el borde.
  // Para otras medidas se escalan: altura según el alto, la copa según la boca y el pie según el pie.
  var COPAS = {
    flauta: { // Copa Valparaíso 120 cc
      alto: 184, boca: 57, pie: 65, banda: [0.46, 0.95],
      tallo: [[0, 32.5], [2.2, 32.5], [3.6, 31], [5, 26], [6.4, 15], [7.8, 7], [10, 4.4], [14, 3.2], [22, 2.7], [40, 2.6], [56, 2.7], [62, 3]],
      cuerpo: [[62, 3], [68, 3.6], [74, 4.4], [83, 6.6], [91, 10.3], [100, 14.4], [109, 18.8], [117, 22.2], [134, 25.6], [152, 27.9], [169, 28.6], [184, 28.2]]
    },
    tulipa: { // Copa Nevados 450 cc
      alto: 210, boca: 85, pie: 77, banda: [0.22, 0.95],
      tallo: [[0, 38.5], [2.2, 38.5], [3.6, 37], [5, 31], [6.4, 15], [8.5, 6], [12, 3.9], [18, 3.2], [30, 3.1], [58, 3.8], [77, 4.8], [87, 5.8], [93, 5.8], [95.5, 5.3], [97, 7.4], [99.5, 8.4], [102, 7.6], [103.5, 6.8]],
      cuerpo: [[103.5, 6.8], [104.3, 10.5], [105.6, 16.5], [107.6, 22], [110.8, 27], [115.5, 32.4], [120, 36.2], [125, 38.8], [135, 40.2], [173, 41.6], [210, 42.3]]
    },
    balon: { // Copa Torres del Paine 580 cc
      alto: 200, boca: 104, pie: 80, banda: [0.3, 0.93],
      tallo: [[0, 40], [2.4, 40], [4, 38.5], [6, 32], [8, 18], [10, 9], [13, 6], [19, 4.6], [30, 3.8], [60, 3.4], [90, 3.7], [103, 4.3], [106, 5]],
      cuerpo: [[106, 5], [107, 7], [108.5, 11], [110.4, 18], [113.6, 27], [118.6, 36], [127, 45], [134.7, 50], [142, 52.4], [150, 53.2], [164, 53.4], [183, 52.9], [200, 52]]
    }
  };

  // Interpolación suave que respeta la forma (PCHIP): no inventa "panzas" entre puntos
  function suave(pts) {
    var n = pts.length, h = [], d = [], m = [];
    for (var i = 0; i < n - 1; i++) { h[i] = pts[i + 1][0] - pts[i][0]; d[i] = (pts[i + 1][1] - pts[i][1]) / h[i]; }
    m[0] = d[0]; m[n - 1] = d[n - 2];
    for (i = 1; i < n - 1; i++) {
      if (d[i - 1] * d[i] <= 0) m[i] = 0;
      else { var w1 = 2 * h[i] + h[i - 1], w2 = h[i] + 2 * h[i - 1]; m[i] = (w1 + w2) / (w1 / d[i - 1] + w2 / d[i]); }
    }
    return function (y) {
      if (y <= pts[0][0]) return pts[0][1];
      if (y >= pts[n - 1][0]) return pts[n - 1][1];
      var k = 0; while (k < n - 2 && y > pts[k + 1][0]) k++;
      var t = (y - pts[k][0]) / h[k], t2 = t * t, t3 = t2 * t;
      return (2 * t3 - 3 * t2 + 1) * pts[k][1] + (t3 - 2 * t2 + t) * h[k] * m[k] + (-2 * t3 + 3 * t2) * pts[k + 1][1] + (t3 - t2) * h[k] * m[k + 1];
    };
  }

  function perfilCopa(forma) {
    var ref = COPAS[forma.estilo] || COPAS.tulipa;
    var fy = (forma.alto || ref.alto) / ref.alto;
    var fb = (forma.boca || ref.boca) / ref.boca, fp = (forma.pie || ref.pie) / ref.pie;
    var base = ref.cuerpo[0][0] * fy, rUnion = ref.cuerpo[0][1];
    var tallo = ref.tallo.map(function (q) {
      // el pie escala con su medida; el tallo es delgado y se mantiene, empalmando con la copa
      var r = q[1] > 12 ? q[1] * fp : q[1];
      return [q[0] * fy, r];
    });
    var cuerpo = ref.cuerpo.map(function (q, i) {
      var r = i === 0 ? rUnion : rUnion + (q[1] - rUnion) * fb;
      return [q[0] * fy - base, r];
    });
    return { base: base, tallo: suave(tallo), cuerpo: suave(cuerpo), banda: ref.banda };
  }

  // Devuelve la geometría normalizada de cualquier producto
  function geometria(forma, variante) {
    var g = { tapa: forma.tapa || null, referencial: !!forma.referencial };
    if (forma.tipo === "vaso") {
      g.cuerpoAlto = forma.alto; g.base = 0;
      g.r = function (y) { return forma.fondo / 2 + (forma.boca / 2 - forma.fondo / 2) * (y / forma.alto); };
      g.ancho = Math.max(forma.fondo, forma.boca);
      g.banda = [Math.min(15, forma.alto * 0.18), forma.alto - Math.min(15, forma.alto * 0.18)];
      g.altoTotal = forma.alto + (g.tapa === "bombilla" ? 72 : g.tapa === "cafe" ? 12 : 0);
      g.medidas = "Alto " + forma.alto + " mm · Boca Ø " + forma.boca + " mm";
      g.etiquetaMedida = forma.alto;
    } else if (forma.tipo === "copa") {
      var pc = perfilCopa(forma);
      g.copa = forma.estilo; g.pie = forma.pie;
      g.base = pc.base; g.tallo = pc.tallo;
      g.cuerpoAlto = forma.alto - g.base;
      g.r = pc.cuerpo;
      var muestras = []; for (var i = 0; i <= 60; i++) muestras.push(g.r(g.cuerpoAlto * i / 60) * 2);
      g.ancho = Math.max(forma.pie, Math.max.apply(null, muestras));
      g.banda = [g.cuerpoAlto * pc.banda[0], g.cuerpoAlto * pc.banda[1]];
      g.altoTotal = forma.alto;
      g.medidas = "Alto " + forma.alto + " mm · Ø máx. " + Math.round(g.ancho) + " mm";
      g.etiquetaMedida = forma.alto;
    } else { // portavaso
      var d = variante && /500/.test(variante) ? 81 : 72;
      g.portavaso = true; g.cuerpoAlto = forma.alto; g.base = 0;
      g.r = function () { return d / 2; };
      g.ancho = d; g.banda = [5, forma.alto - 5];
      g.altoTotal = forma.alto + 14;
      g.medidas = "Ø " + d + " mm (referencial)";
      g.etiquetaMedida = forma.alto;
      g.referencial = true;
    }
    return g;
  }

  // ---------- regla en mm ----------
  function regla(ctx, x, piso, topMm, s) {
    ctx.save();
    ctx.strokeStyle = "rgba(38,43,36,.55)"; ctx.fillStyle = "rgba(38,43,36,.7)";
    ctx.lineWidth = 2; ctx.font = "600 20px Helvetica, Arial, sans-serif"; ctx.textAlign = "right"; ctx.textBaseline = "middle";
    ctx.beginPath(); ctx.moveTo(x, piso); ctx.lineTo(x, piso - topMm * s); ctx.stroke();
    for (var mm = 0; mm <= topMm + 0.1; mm += 10) {
      var y = piso - mm * s, largo = mm % 50 === 0 ? 18 : 9;
      ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + largo, y); ctx.stroke();
      if (mm % 50 === 0) ctx.fillText(mm === 0 ? "0" : String(mm), x - 8, y);
    }
    ctx.textAlign = "left"; ctx.fillText("mm", x - 10, piso - topMm * s - 26);
    ctx.restore();
  }

  // ---------- trazado del cuerpo a partir del perfil ----------
  function trazarCuerpo(ctx, g, cx, y0, s, n) {
    n = n || 48;
    var pts = [];
    for (var i = 0; i <= n; i++) { var y = g.cuerpoAlto * i / n; pts.push([y, g.r(y)]); }
    ctx.beginPath();
    pts.forEach(function (p, i) { var X = cx - p[1] * s, Y = y0 - p[0] * s; if (i) ctx.lineTo(X, Y); else ctx.moveTo(X, Y); });
    var rTop = pts[n][1];
    ctx.ellipse(cx, y0 - g.cuerpoAlto * s, rTop * s, rTop * s * K, 0, Math.PI, 0, true);
    for (var j = n; j >= 0; j--) { var q = pts[j]; ctx.lineTo(cx + q[1] * s, y0 - q[0] * s); }
    var rBot = pts[0][1];
    if (rBot > 0.5 && !g.copa) ctx.ellipse(cx, y0, rBot * s, rBot * s * K, 0, 0, Math.PI, false);
    ctx.closePath();
  }

  // ---------- logo envuelto sobre la superficie ----------
  function dibujarLogo(ctx, g, logo, cx, y0, s, ajuste, alfa) {
    var bandaAlto = g.banda[1] - g.banda[0];
    var rMin = Infinity;
    for (var k = 0; k <= 10; k++) rMin = Math.min(rMin, g.r(g.banda[0] + bandaAlto * k / 10));
    var aspecto = logo.width / logo.height;
    var maxAncho = rMin * 2.1;                        // arco máximo visible de frente (mm)
    var hMax = Math.min(bandaAlto, maxAncho / aspecto);
    var h = hMax * Math.max(0.15, Math.min(1, ajuste.escala));
    var w = h * aspecto;
    var yc = g.banda[0] + h / 2 + (ajuste.posY) * (bandaAlto - h);
    var N = 72, M = 24, sw = logo.width / N, sh = logo.height / M;
    for (var j = 0; j < M; j++) {
      var ya = yc + h / 2 - (j / M) * h, yb = yc + h / 2 - ((j + 1) / M) * h;
      var r = g.r((ya + yb) / 2);
      var Ya = y0 - ya * s, Yb = y0 - yb * s;
      for (var i = 0; i < N; i++) {
        var ta = ((i / N) - 0.5) * w / r, tb = (((i + 1) / N) - 0.5) * w / r, tm = (ta + tb) / 2;
        if (Math.abs(tm) > 1.48) continue;
        var xa = cx + r * s * Math.sin(ta), xb = cx + r * s * Math.sin(tb);
        // Lo que está al frente baja levemente (perspectiva de la elipse)
        var curva = r * s * K * Math.cos(tm) * 0.55;
        ctx.globalAlpha = alfa * (0.45 + 0.55 * Math.cos(tm));
        ctx.drawImage(logo, i * sw, j * sh, sw, sh, Math.min(xa, xb), Ya + curva, Math.abs(xb - xa) + 0.7, (Yb - Ya) + 0.7);
      }
    }
    ctx.globalAlpha = 1;
    return { anchoMm: w, altoMm: h };
  }

  function brillo(ctx, cx, r, y1, y2, claro) {
    var gr = ctx.createLinearGradient(cx - r, 0, cx + r, 0);
    if (claro) { // vidrio transparente: paredes vistas de canto (bordes oscuros) y reflejos finos
      gr.addColorStop(0, "rgba(60,74,78,.55)");
      gr.addColorStop(0.05, "rgba(60,74,78,.16)");
      gr.addColorStop(0.14, "rgba(255,255,255,0)");
      gr.addColorStop(0.2, "rgba(255,255,255,.55)");
      gr.addColorStop(0.25, "rgba(255,255,255,.05)");
      gr.addColorStop(0.7, "rgba(255,255,255,0)");
      gr.addColorStop(0.8, "rgba(255,255,255,.32)");
      gr.addColorStop(0.84, "rgba(255,255,255,0)");
      gr.addColorStop(0.95, "rgba(60,74,78,.18)");
      gr.addColorStop(1, "rgba(60,74,78,.6)");
      ctx.fillStyle = gr; ctx.fillRect(cx - r - 2, y1, 2 * r + 4, y2 - y1);
      return;
    }
    gr.addColorStop(0, "rgba(0,0,0,.20)");
    gr.addColorStop(0.12, "rgba(255,255,255,0)");
    gr.addColorStop(0.24, "rgba(255,255,255,.42)");
    gr.addColorStop(0.31, "rgba(255,255,255,.08)");
    gr.addColorStop(0.62, "rgba(255,255,255,0)");
    gr.addColorStop(0.86, "rgba(0,0,0,.10)");
    gr.addColorStop(1, "rgba(0,0,0,.26)");
    ctx.fillStyle = gr; ctx.fillRect(cx - r - 2, y1, 2 * r + 4, y2 - y1);
  }

  // ---------- productos con foto (tipos nuevos): foto del color + logo en el área de impresión ----------
  function dibujarFoto(canvas, o) {
    var ctx = canvas.getContext("2d"), f = o.forma, foto = o.foto;
    var fondo = ctx.createLinearGradient(0, 0, 0, H);
    fondo.addColorStop(0, "#F6F8F4"); fondo.addColorStop(1, "#E4EBE0");
    ctx.fillStyle = fondo; ctx.fillRect(0, 0, W, H);
    var caja = { x: 40, y: 40, w: W - 80, h: PISO - 20 };
    var r = caja;
    if (foto && foto.naturalWidth) {
      var esc = Math.min(caja.w / foto.naturalWidth, caja.h / foto.naturalHeight);
      var fw = foto.naturalWidth * esc, fh = foto.naturalHeight * esc;
      r = { x: caja.x + (caja.w - fw) / 2, y: caja.y + (caja.h - fh) / 2, w: fw, h: fh };
      ctx.drawImage(foto, r.x, r.y, r.w, r.h);
    } else {
      ctx.fillStyle = "#DCE4D8"; ctx.fillRect(r.x, r.y, r.w, r.h);
      ctx.fillStyle = "#5B6358"; ctx.font = "24px Helvetica, Arial, sans-serif"; ctx.textAlign = "center";
      ctx.fillText("Cargando imagen…", W / 2, H / 2);
    }
    var a = f.area || { x: 0.3, y: 0.35, w: 0.4, h: 0.3 };
    var zona = { x: r.x + a.x * r.w, y: r.y + a.y * r.h, w: a.w * r.w, h: a.h * r.h };
    if (o.mostrarArea) {
      ctx.save(); ctx.setLineDash([10, 8]); ctx.strokeStyle = "#4B873E"; ctx.lineWidth = 3; ctx.strokeRect(zona.x, zona.y, zona.w, zona.h); ctx.restore();
    }
    var medida = null;
    if (o.logo) {
      var aj = o.ajuste || { escala: 0.75, posY: 0.5 };
      var asp = o.logo.width / o.logo.height;
      var h = Math.min(zona.h, zona.w / asp) * Math.max(0.15, Math.min(1, aj.escala)), w = h * asp;
      var y = zona.y + (1 - aj.posY) * (zona.h - h), cx = zona.x + zona.w / 2;
      ctx.save(); ctx.beginPath(); ctx.rect(zona.x - 2, zona.y - 2, zona.w + 4, zona.h + 4); ctx.clip();
      if (f.curva) {
        var R = zona.w / 2, N = 80, sw = o.logo.width / N;
        for (var i = 0; i < N; i++) {
          var ta = ((i / N) - 0.5) * w / R, tb = (((i + 1) / N) - 0.5) * w / R, tm = (ta + tb) / 2;
          if (Math.abs(tm) > 1.48) continue;
          var xa = cx + R * Math.sin(ta), xb = cx + R * Math.sin(tb);
          ctx.globalAlpha = 0.45 + 0.55 * Math.cos(tm);
          ctx.drawImage(o.logo, i * sw, 0, sw, o.logo.height, Math.min(xa, xb), y, Math.abs(xb - xa) + 0.7, h);
        }
        ctx.globalAlpha = 1;
      } else {
        ctx.globalAlpha = 0.96; ctx.drawImage(o.logo, cx - w / 2, y, w, h); ctx.globalAlpha = 1;
      }
      ctx.restore();
      if (f.anchoMm) medida = { anchoMm: w / zona.w * f.anchoMm, altoMm: h / zona.w * f.anchoMm };
    }
    ctx.fillStyle = "#262B24"; ctx.textAlign = "center"; ctx.textBaseline = "alphabetic";
    ctx.font = "700 34px Helvetica, Arial, sans-serif"; ctx.fillText(o.nombre || "", W / 2, PISO + 92);
    ctx.font = "24px Helvetica, Arial, sans-serif"; ctx.fillStyle = "#5B6358";
    ctx.fillText([o.color && o.color.nombre, f.medidas].filter(Boolean).join(" · "), W / 2, PISO + 132);
    ctx.font = "italic 20px Helvetica, Arial, sans-serif"; ctx.fillStyle = "#7A8276";
    ctx.fillText("Vista previa referencial · mivaso.cl", W / 2, PISO + 170);
    if (medida) {
      ctx.textAlign = "right"; ctx.font = "600 20px Helvetica, Arial, sans-serif"; ctx.fillStyle = "#4B873E";
      ctx.fillText("Logo aprox. " + Math.round(medida.anchoMm) + " × " + Math.round(medida.altoMm) + " mm", W - 30, 44);
    }
    return { logo: medida };
  }

  // ---------- dibujo principal ----------
  function dibujar(canvas, o) {
    canvas.width = W; canvas.height = H;
    if (o.forma && o.forma.tipo === "foto") return dibujarFoto(canvas, o);
    var ctx = canvas.getContext("2d");
    var g = geometria(o.forma, o.variante);
    var col = o.color || { hex: "#DCE9EE", alfa: 0.3 };

    // Fondo
    var fondo = ctx.createLinearGradient(0, 0, 0, H);
    fondo.addColorStop(0, "#F6F8F4"); fondo.addColorStop(1, "#E4EBE0");
    ctx.fillStyle = fondo; ctx.fillRect(0, 0, W, H);

    // Escala: el producto llena el espacio disponible
    var s = Math.min((PISO - 110) / g.altoTotal, 640 / (g.ancho * 1.08));
    var cx = 500;
    regla(ctx, 96, PISO, Math.ceil(g.altoTotal / 10) * 10, s);

    // Sombra en el piso
    var sombra = ctx.createRadialGradient(cx, PISO, 4, cx, PISO, g.ancho * s * 0.75);
    sombra.addColorStop(0, "rgba(19,63,6,.22)"); sombra.addColorStop(1, "rgba(19,63,6,0)");
    ctx.save(); ctx.translate(cx, PISO); ctx.scale(1, K * 1.4); ctx.translate(-cx, -PISO);
    ctx.fillStyle = sombra; ctx.beginPath(); ctx.arc(cx, PISO, g.ancho * s * 0.75, 0, 2 * Math.PI); ctx.fill(); ctx.restore();

    var y0 = PISO - g.base * s;            // base del cuerpo (en copas, sobre el tallo)
    var yTop = y0 - g.cuerpoAlto * s;
    var rTop = g.r(g.cuerpoAlto);
    var vidrio = g.copa ? { hex: col.hex, alfa: col.alfa } : col;

    // Pie y tallo de las copas (con el perfil real del modelo)
    var claroVidrio = col.alfa < 0.5;
    if (g.copa) {
      var rp = g.pie / 2 * s, eP = 2.2 * s;
      var aPie = claroVidrio ? 0.22 : Math.min(0.95, vidrio.alfa + 0.08);
      // canto del pie
      ctx.fillStyle = rgba(claroVidrio ? "#B9C6C9" : oscuro(vidrio.hex, 0.15), aPie + 0.08);
      ctx.beginPath(); ctx.ellipse(cx, PISO, rp, rp * K, 0, 0, Math.PI); ctx.lineTo(cx - rp, PISO - eP);
      ctx.ellipse(cx, PISO - eP, rp, rp * K, 0, Math.PI, 2 * Math.PI); ctx.closePath(); ctx.fill();
      ctx.strokeStyle = rgba(oscuro(vidrio.hex, 0.5), 0.5); ctx.lineWidth = 2; ctx.stroke();
      // cara superior del pie
      ctx.fillStyle = rgba(vidrio.hex, aPie);
      ctx.beginPath(); ctx.ellipse(cx, PISO - eP, rp, rp * K, 0, 0, 2 * Math.PI); ctx.fill();
      ctx.strokeStyle = "rgba(255,255,255,.7)"; ctx.lineWidth = 2;
      ctx.beginPath(); ctx.ellipse(cx, PISO - eP, rp * 0.96, rp * K * 0.96, 0, Math.PI * 1.05, Math.PI * 1.95); ctx.stroke();
      // tallo (silueta con el perfil)
      var N = 80, lado = [];
      for (var it = 0; it <= N; it++) { var yy = 2.2 + (g.base - 2.2) * it / N; lado.push([yy, g.tallo(yy)]); }
      ctx.beginPath();
      lado.forEach(function (q, i) { var X = cx - q[1] * s, Y = PISO - q[0] * s; if (i) ctx.lineTo(X, Y); else ctx.moveTo(X, Y); });
      for (it = N; it >= 0; it--) ctx.lineTo(cx + lado[it][1] * s, PISO - lado[it][0] * s);
      ctx.closePath();
      var tw = 6 * s;
      var tallo = ctx.createLinearGradient(cx - tw, 0, cx + tw, 0);
      if (claroVidrio) {
        tallo.addColorStop(0, "rgba(70,84,86,.55)"); tallo.addColorStop(0.3, "rgba(255,255,255,.75)");
        tallo.addColorStop(0.55, "rgba(200,212,214,.35)"); tallo.addColorStop(1, "rgba(70,84,86,.6)");
      } else {
        tallo.addColorStop(0, rgba(oscuro(vidrio.hex, 0.35), Math.min(1, vidrio.alfa + 0.2)));
        tallo.addColorStop(0.3, rgba(claro(vidrio.hex, 0.45), Math.min(1, vidrio.alfa + 0.15)));
        tallo.addColorStop(1, rgba(oscuro(vidrio.hex, 0.4), Math.min(1, vidrio.alfa + 0.25)));
      }
      ctx.fillStyle = tallo; ctx.fill();
      // contorno solo por los lados (sin línea donde se une con la copa)
      ctx.strokeStyle = rgba(oscuro(vidrio.hex, 0.55), claroVidrio ? 0.45 : 0.35); ctx.lineWidth = 1.5;
      [-1, 1].forEach(function (sg) {
        ctx.beginPath();
        lado.forEach(function (q, i) { var X = cx + sg * q[1] * s, Y = PISO - q[0] * s; if (i) ctx.lineTo(X, Y); else ctx.moveTo(X, Y); });
        ctx.stroke();
      });
    }

    // Pared interior trasera (se ve por la boca)
    ctx.fillStyle = rgba(oscuro(col.hex, 0.25), Math.min(0.95, col.alfa + 0.12));
    ctx.beginPath(); ctx.ellipse(cx, yTop, rTop * s, rTop * s * K, 0, 0, 2 * Math.PI); ctx.fill();

    // Bombilla por dentro (se ve a través del vaso)
    if (g.tapa === "bombilla") {
      ctx.strokeStyle = "rgba(255,255,255,.5)"; ctx.lineWidth = 6 * s * 0.9; ctx.lineCap = "round";
      ctx.beginPath(); ctx.moveTo(cx + rTop * s * 0.18, yTop - 14 * s); ctx.lineTo(cx + rTop * s * 0.05, y0 - 6 * s); ctx.stroke();
    }

    // Cuerpo
    var cuerpo = function () { trazarCuerpo(ctx, g, cx, y0, s); };
    cuerpo();
    if (g.portavaso) {
      var pv = ctx.createLinearGradient(0, yTop, 0, y0);
      pv.addColorStop(0, claro(col.hex, 0.08)); pv.addColorStop(1, oscuro(col.hex, 0.15));
      ctx.fillStyle = pv;
    } else {
      ctx.fillStyle = rgba(col.hex, claroVidrio ? 0.16 : col.alfa);
    }
    ctx.fill();

    // Logo + brillos, recortados al cuerpo
    var medidaLogo = null;
    ctx.save(); cuerpo(); ctx.clip();
    if (o.logo) medidaLogo = dibujarLogo(ctx, g, o.logo, cx, y0, s, o.ajuste || { escala: 0.7, posY: 0.5 }, 0.97);
    var rMax = 0; for (var q = 0; q <= 20; q++) rMax = Math.max(rMax, g.r(g.cuerpoAlto * q / 20));
    brillo(ctx, cx, rMax * s, yTop - 10, y0 + 20, claroVidrio);
    ctx.restore();

    // Contornos
    if (g.copa) {
      // en las copas solo los costados: la unión con el tallo queda limpia
      [-1, 1].forEach(function (sg) {
        ctx.beginPath();
        for (var i = 0; i <= 48; i++) { var yy = g.cuerpoAlto * i / 48, X = cx + sg * g.r(yy) * s, Y = y0 - yy * s; if (i) ctx.lineTo(X, Y); else ctx.moveTo(X, Y); }
        ctx.strokeStyle = rgba(oscuro(col.hex, 0.5), 0.5); ctx.lineWidth = 2; ctx.stroke();
      });
    } else {
      cuerpo();
      ctx.strokeStyle = rgba(oscuro(col.hex, 0.5), g.portavaso ? 0.8 : 0.5); ctx.lineWidth = 2; ctx.stroke();
    }
    // Borde (labio)
    ctx.beginPath(); ctx.ellipse(cx, yTop, rTop * s, rTop * s * K, 0, 0, 2 * Math.PI);
    ctx.strokeStyle = g.portavaso ? rgba(claro(col.hex, 0.3), 0.9) : "rgba(255,255,255,.85)"; ctx.lineWidth = 4; ctx.stroke();
    ctx.beginPath(); ctx.ellipse(cx, yTop, rTop * s, rTop * s * K, 0, Math.PI, 0);
    ctx.strokeStyle = rgba(oscuro(col.hex, 0.5), 0.45); ctx.lineWidth = 1.5; ctx.stroke();
    // Fondo del vaso (base gruesa)
    if (!g.copa) {
      var rb = g.r(0) * s;
      ctx.beginPath(); ctx.ellipse(cx, y0 - 4 * s, rb * 0.98, rb * K, 0, 0, Math.PI);
      ctx.strokeStyle = "rgba(255,255,255,.55)"; ctx.lineWidth = 2; ctx.stroke();
    }

    // Argolla del portavaso
    if (g.portavaso) {
      ctx.fillStyle = oscuro(col.hex, 0.05);
      ctx.beginPath(); ctx.ellipse(cx, yTop - 6 * s, 7 * s, 9 * s, 0, 0, 2 * Math.PI); ctx.fill();
      ctx.fillStyle = "#E9EEE6"; ctx.beginPath(); ctx.arc(cx, yTop - 8 * s, 3 * s, 0, 2 * Math.PI); ctx.fill();
    }

    // Tapas
    if (g.tapa) {
      var rl = (rTop + 2) * s;
      var tapa = "rgba(235,242,244,.62)";
      if (g.tapa === "bombilla") {
        ctx.fillStyle = tapa;
        ctx.beginPath(); ctx.ellipse(cx, yTop, rl, rl * K, 0, 0, Math.PI); ctx.lineTo(cx - rl, yTop);
        ctx.quadraticCurveTo(cx, yTop - 34 * s, cx + rl, yTop); ctx.closePath(); ctx.fill();
        ctx.strokeStyle = "rgba(60,70,60,.45)"; ctx.lineWidth = 2; ctx.stroke();
        ctx.beginPath(); ctx.ellipse(cx, yTop, rl, rl * K, 0, 0, Math.PI); ctx.strokeStyle = "rgba(255,255,255,.9)"; ctx.lineWidth = 4; ctx.stroke();
        // bombilla
        ctx.lineCap = "round";
        ctx.strokeStyle = "rgba(90,100,95,.45)"; ctx.lineWidth = 7 * s;
        ctx.beginPath(); ctx.moveTo(cx + rTop * s * 0.18, yTop - 14 * s); ctx.lineTo(cx + rTop * s * 0.34, yTop - 72 * s); ctx.stroke();
        ctx.strokeStyle = "rgba(255,255,255,.75)"; ctx.lineWidth = 5 * s;
        ctx.beginPath(); ctx.moveTo(cx + rTop * s * 0.18, yTop - 14 * s); ctx.lineTo(cx + rTop * s * 0.34, yTop - 72 * s); ctx.stroke();
      } else {
        var alto = 9 * s;
        ctx.fillStyle = "rgba(240,244,245,.92)";
        ctx.beginPath(); ctx.ellipse(cx, yTop, rl, rl * K, 0, 0, Math.PI); ctx.lineTo(cx - rl, yTop - alto);
        ctx.ellipse(cx, yTop - alto, rl, rl * K, 0, Math.PI, 0); ctx.closePath(); ctx.fill();
        ctx.strokeStyle = "rgba(60,70,60,.4)"; ctx.lineWidth = 2; ctx.stroke();
        ctx.fillStyle = "rgba(250,252,252,.97)";
        ctx.beginPath(); ctx.ellipse(cx, yTop - alto, rl, rl * K, 0, 0, 2 * Math.PI); ctx.fill(); ctx.stroke();
        ctx.fillStyle = "rgba(40,48,40,.55)";
        ctx.beginPath(); ctx.ellipse(cx + rl * 0.42, yTop - alto + rl * K * 0.45, rl * 0.16, rl * K * 0.22, 0, 0, 2 * Math.PI); ctx.fill();
      }
    }

    // Textos
    ctx.fillStyle = "#262B24"; ctx.textAlign = "center"; ctx.textBaseline = "alphabetic";
    ctx.font = "700 34px Helvetica, Arial, sans-serif";
    ctx.fillText(o.nombre || "", W / 2, PISO + 92);
    ctx.font = "24px Helvetica, Arial, sans-serif"; ctx.fillStyle = "#5B6358";
    var linea = [col.nombre, g.medidas].filter(Boolean).join(" · ");
    ctx.fillText(linea, W / 2, PISO + 132);
    ctx.font = "italic 20px Helvetica, Arial, sans-serif"; ctx.fillStyle = "#7A8276";
    ctx.fillText("Vista previa referencial" + (g.referencial ? " · medidas aproximadas" : "") + " · mivaso.cl", W / 2, PISO + 170);
    if (medidaLogo) {
      ctx.textAlign = "right"; ctx.font = "600 20px Helvetica, Arial, sans-serif"; ctx.fillStyle = "#4B873E";
      ctx.fillText("Logo aprox. " + Math.round(medidaLogo.anchoMm) + " × " + Math.round(medidaLogo.altoMm) + " mm", W - 30, 44);
    }
    return { logo: medidaLogo };
  }

  // ---------- preparación del logo ----------
  function procesarLogo(fuente, op) {
    op = op || {};
    var max = 1000;
    var w = fuente.naturalWidth || fuente.width, h = fuente.naturalHeight || fuente.height;
    var f = Math.min(1, max / Math.max(w, h));
    var c = document.createElement("canvas");
    c.width = Math.max(1, Math.round(w * f)); c.height = Math.max(1, Math.round(h * f));
    var x = c.getContext("2d", { willReadFrequently: true });
    x.drawImage(fuente, 0, 0, c.width, c.height);
    var d = x.getImageData(0, 0, c.width, c.height), p = d.data;
    var tinta = op.tinta ? rgb(op.tinta) : null;
    var minX = c.width, minY = c.height, maxX = -1, maxY = -1;
    for (var i = 0; i < p.length; i += 4) {
      var r = p[i], gg = p[i + 1], b = p[i + 2], a = p[i + 3];
      if (op.quitarFondo && a > 0) {
        var mx = Math.max(r, gg, b), mn = Math.min(r, gg, b);
        var lum = (0.299 * r + 0.587 * gg + 0.114 * b) / 255, sat = mx ? (mx - mn) / mx : 0;
        if (sat < 0.14) {
          if (lum > 0.93) a = 0;
          else if (lum > 0.84) a = Math.round(a * (0.93 - lum) / 0.09);
        }
      }
      if (tinta && a > 0) { p[i] = tinta[0]; p[i + 1] = tinta[1]; p[i + 2] = tinta[2]; }
      p[i + 3] = a;
      if (a > 12) {
        var px = (i / 4) % c.width, py = Math.floor(i / 4 / c.width);
        if (px < minX) minX = px; if (px > maxX) maxX = px; if (py < minY) minY = py; if (py > maxY) maxY = py;
      }
    }
    x.putImageData(d, 0, 0);
    if (maxX < 0) return null;            // quedó vacío
    var t = document.createElement("canvas");
    t.width = maxX - minX + 1; t.height = maxY - minY + 1;
    t.getContext("2d").drawImage(c, minX, minY, t.width, t.height, 0, 0, t.width, t.height);
    return t;
  }

  // ¿La imagen ya trae transparencia?
  function tieneTransparencia(fuente) {
    var c = document.createElement("canvas"), w = Math.min(200, fuente.naturalWidth || fuente.width);
    var h = Math.max(1, Math.round(w * (fuente.naturalHeight || fuente.height) / (fuente.naturalWidth || fuente.width)));
    c.width = w; c.height = h;
    var x = c.getContext("2d", { willReadFrequently: true }); x.drawImage(fuente, 0, 0, w, h);
    var p = x.getImageData(0, 0, w, h).data;
    for (var i = 3; i < p.length; i += 16) if (p[i] < 250) return true;
    return false;
  }

  // ---------- lectura de archivos (imágenes, SVG y PDF/AI con pdf.js) ----------
  var PDFJS = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/";
  var cargaPdf = null;
  function pdfjs() {
    if (window.pdfjsLib) return Promise.resolve(window.pdfjsLib);
    if (!cargaPdf) cargaPdf = new Promise(function (ok, mal) {
      var sc = document.createElement("script");
      sc.src = PDFJS + "pdf.min.js"; sc.onload = function () {
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS + "pdf.worker.min.js"; ok(window.pdfjsLib);
      }; sc.onerror = function () { cargaPdf = null; mal(new Error("pdfjs")); };
      document.head.appendChild(sc);
    });
    return cargaPdf;
  }
  function leerImagen(archivo) {
    var nombre = (archivo.name || "").toLowerCase();
    if (/\.(pdf|ai)$/.test(nombre)) {
      return archivo.arrayBuffer().then(function (buf) {
        return pdfjs().then(function (lib) { return lib.getDocument({ data: buf }).promise; });
      }).then(function (doc) { return doc.getPage(1); }).then(function (pag) {
        var v = pag.getViewport({ scale: 1 }), esc = 1200 / Math.max(v.width, v.height);
        var vp = pag.getViewport({ scale: esc }), c = document.createElement("canvas");
        c.width = Math.round(vp.width); c.height = Math.round(vp.height);
        return pag.render({ canvasContext: c.getContext("2d"), viewport: vp }).promise.then(function () { return c; });
      });
    }
    if (/\.eps$/.test(nombre)) return Promise.reject(new Error("eps"));
    return new Promise(function (ok, mal) {
      var url = URL.createObjectURL(archivo), im = new Image();
      im.onload = function () { ok(im); };
      im.onerror = function () { URL.revokeObjectURL(url); mal(new Error("imagen")); };
      im.src = url;
    });
  }

  window.MiVasoVista = {
    dibujar: dibujar, procesarLogo: procesarLogo, leerImagen: leerImagen,
    tieneTransparencia: tieneTransparencia, geometria: geometria, ANCHO: W, ALTO: H
  };
})();
