(function (global) {
  var SAYFA_ARASI = 24;

  var CSS =
    "*{box-sizing:border-box}" +
    "html,body{margin:0;padding:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}" +
    "body{background:#e9edf2;padding:28px 16px;font-family:'Times New Roman',Times,serif;font-size:10pt;line-height:1.4;color:#000}" +
    "#kok{width:210mm;margin:0 auto;padding:1cm 1.5cm;min-height:297mm;position:relative;overflow-wrap:break-word}#kok li{list-style-position:inside}" +
    "#kok p{margin:0}#kok h1{margin:0 0 8px;font-size:18px;font-weight:700;text-align:center}#kok h2{margin:0 0 6px;font-size:14px;font-weight:600;text-align:center}#kok h3{margin:0 0 8px;font-size:22.75px;line-height:1.2;font-weight:600}#kok ol,#kok ul{margin:0 0 16px}" +
    "#kok table{border-collapse:collapse;width:100%;margin-bottom:0}" +
    "#kok table.table-bordered td,#kok table.table-bordered th,#kok table.has-borders td,#kok table.has-borders th{border:1px solid #000;padding:4px 8px}" +
    "#kok table.no-borders td,#kok table.no-borders th,#kok table.signatures-table td,#kok table.signatures-table th{border:0}" +
    "#kok img{max-width:100%;height:auto;vertical-align:middle}" +
    "#kok .ql-align-center{text-align:center}#kok .ql-align-right{text-align:right}#kok .ql-align-justify{text-align:justify}" +
    "#kok .note-float-left{float:left;margin-right:10px}#kok .note-float-right{float:right;margin-left:10px}" +
    "@page{size:A4 portrait;margin:1cm 1.5cm}" +
    "@media print{body{background:#fff;padding:0}#kok{width:auto;margin:0;padding:0;min-height:0!important;background:none!important}" +
    "#kok [data-sbp]{padding-top:0!important;margin-top:0!important;break-before:page;page-break-before:always}" +
    "#kok table,#kok tr{break-inside:avoid}" +
    ".cerceve{position:fixed;top:-.2cm;left:-.5cm;right:-.5cm;bottom:-.2cm;border:2px solid #000;pointer-events:none}}";

  function belgeHtml(icerik, cerceve) {
    return (
      '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Hizmet Sözleşmesi</title><style>' + CSS + "</style></head><body>" +
      (cerceve ? '<div class="cerceve"></div>' : "") +
      '<div id="kok" data-cerceve="' + (cerceve ? "1" : "0") + '">' + icerik + "</div></body></html>"
    );
  }

  function birim(doc, css) {
    var o = doc.createElement("div");
    o.style.cssText = "position:absolute;visibility:hidden;width:1px;height:" + css;
    doc.body.appendChild(o);
    var h = o.offsetHeight;
    o.remove();
    return h;
  }

  function baslikMi(b) {
    var metin = (b.textContent || "").trim();
    if (!metin || metin.length > 90) return false;
    if (/^H[1-6]$/.test(b.tagName)) return true;
    var kalin = Array.prototype.map.call(b.querySelectorAll("strong, b"), function (x) { return x.textContent; }).join("").trim();
    return kalin.length >= metin.length - 1;
  }

  function sayfala(doc) {
    var kok = doc.getElementById("kok");
    if (!kok) return;
    var cerceve = kok.getAttribute("data-cerceve") === "1";
    var H = birim(doc, "297mm") || 1122.52;
    var m = birim(doc, "1cm") || 37.8;
    var W = Math.round(birim(doc, "210mm") || 794);
    var P = H + SAYFA_ARASI;
    var C = H - 2 * m;
    var bloklar = Array.prototype.slice.call(kok.children);
    bloklar.forEach(function (b) {
      if (b.hasAttribute("data-sbp")) {
        b.style.marginTop = "";
        b.style.paddingTop = "";
        b.removeAttribute("data-sbp");
        if (!b.getAttribute("style")) b.removeAttribute("style");
      }
    });
    var ust = kok.getBoundingClientRect().top + kok.clientTop + parseFloat(doc.defaultView.getComputedStyle(kok).paddingTop || "0");
    var olcum = bloklar.map(function (b) {
      var r = b.getBoundingClientRect();
      return { t: r.top - ust, h: r.height, tt: 0 };
    });
    var kayma = 0;
    var son = 0;
    olcum.forEach(function (o, i) {
      var t = o.t + kayma;
      var k = Math.floor(t / P);
      if (t + o.h > k * P + C + 0.5 && o.h <= C && t > k * P + 0.5) {
        var j = i;
        var hedefT = t;
        if (i > 0 && baslikMi(bloklar[i - 1]) && Math.floor(olcum[i - 1].tt / P) === k && olcum[i - 1].tt > k * P + 0.5) {
          j = i - 1;
          hedefT = olcum[i - 1].tt;
        }
        var gerek = (k + 1) * P - hedefT;
        if (bloklar[j].tagName === "TABLE") bloklar[j].style.marginTop = gerek + "px";
        else bloklar[j].style.paddingTop = gerek + "px";
        bloklar[j].setAttribute("data-sbp", "1");
        kayma += gerek;
        t += gerek;
      }
      o.tt = t;
      son = Math.max(son, t + o.h);
    });
    var adet = Math.max(1, Math.floor((son - 0.5) / P) + 1);
    var ic = cerceve ? '<rect x="38" y="30" width="' + (W - 76) + '" height="' + (H - 60) + '" fill="none" stroke="#000" stroke-width="2"/>' : "";
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' + W + '" height="' + P + '" viewBox="0 0 ' + W + " " + P + '"><rect x=".5" y=".5" width="' + (W - 1) + '" height="' + (H - 1) + '" fill="#fff" stroke="#d5dbe3"/>' + ic + "</svg>";
    kok.style.minHeight = adet * P - SAYFA_ARASI + "px";
    kok.style.backgroundImage = 'url("data:image/svg+xml;utf8,' + encodeURIComponent(svg) + '")';
    kok.style.backgroundSize = "100% " + P + "px";
    kok.style.backgroundRepeat = "repeat-y";
  }

  function yaz(doc, icerik, cerceve, tamam) {
    doc.open();
    doc.write(belgeHtml(icerik, cerceve));
    doc.close();
    var calistir = function () { sayfala(doc); };
    calistir();
    setTimeout(calistir, 150);
    setTimeout(calistir, 600);
    if (doc.fonts && doc.fonts.ready) doc.fonts.ready.then(function () { calistir(); if (tamam) tamam(); });
    else if (tamam) tamam();
  }

  global.SozlesmeBelge = { belgeHtml: belgeHtml, sayfala: sayfala, yaz: yaz };
})(window);
