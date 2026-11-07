"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";

const EGITIMLER = ["İlkokul", "Ortaokul", "Lise", "Meslek Lisesi", "Ön Lisans", "Lisans", "Yüksek Lisans"];
const EHLIYETLER = ["Yok", "B", "C", "D", "E", "A2", "B ve C", "B ve E"];
const MAKS_DOSYA = 8 * 1024 * 1024;

const alanSinifi =
  "w-full rounded-ui border-2 border-neutral-200 bg-white px-4 py-3 text-base text-ink outline-none transition focus:border-brand data-[hata=true]:border-red-500 data-[hata=true]:bg-red-50";

function Alan({
  etiket,
  zorunlu,
  ipucu,
  children,
}: {
  etiket: string;
  zorunlu?: boolean;
  ipucu?: string;
  children: React.ReactNode;
}) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-sm font-semibold text-neutral-600">
        {etiket}
        {zorunlu && <b className="ml-1 text-red-600">*</b>}
      </span>
      {children}
      {ipucu && <span className="mt-1 block text-xs text-neutral-500">{ipucu}</span>}
    </label>
  );
}

function Bolum({ baslik, children }: { baslik: string; children: React.ReactNode }) {
  return (
    <section className="rounded-ui border-l-4 border-brand bg-white p-6 shadow-sm sm:p-8">
      <h2 className="mb-6 font-display text-2xl font-bold uppercase text-ink">{baslik}</h2>
      <div className="space-y-5">{children}</div>
    </section>
  );
}

export default function BasvuruFormu({ token, sorular }: { token: string; sorular: string[] }) {
  const formRef = useRef<HTMLFormElement>(null);
  const acilis = useRef(0);
  const [hata, setHata] = useState("");
  const [gonderiliyor, setGonderiliyor] = useState(false);
  const [tamam, setTamam] = useState(false);
  const [fotoOnizleme, setFotoOnizleme] = useState<string | null>(null);
  const [cvAd, setCvAd] = useState("");

  useEffect(() => {
    acilis.current = Date.now();
  }, []);

  useEffect(() => {
    return () => {
      if (fotoOnizleme) URL.revokeObjectURL(fotoOnizleme);
    };
  }, [fotoOnizleme]);

  function dosyaSec(ev: React.ChangeEvent<HTMLInputElement>, tur: "foto" | "cv") {
    const dosya = ev.target.files?.[0];
    if (dosya && dosya.size > MAKS_DOSYA) {
      setHata(tur === "foto" ? "Fotoğraf en fazla 8 MB olabilir." : "CV dosyası en fazla 8 MB olabilir.");
      ev.target.value = "";
      return;
    }
    if (tur === "foto") setFotoOnizleme(dosya ? URL.createObjectURL(dosya) : null);
    else setCvAd(dosya?.name ?? "");
  }

  function dosyaKaldir(ad: "foto" | "cv") {
    const girdi = formRef.current?.elements.namedItem(ad) as HTMLInputElement | null;
    if (girdi) girdi.value = "";
    if (ad === "foto") setFotoOnizleme(null);
    else setCvAd("");
  }

  async function gonder(ev: React.FormEvent<HTMLFormElement>) {
    ev.preventDefault();
    const form = ev.currentTarget;
    setHata("");

    const gecersiz = Array.from(form.querySelectorAll<HTMLInputElement>(":invalid"));
    form.querySelectorAll("[data-hata]").forEach((el) => el.removeAttribute("data-hata"));
    if (gecersiz.length > 0) {
      gecersiz.forEach((el) => el.setAttribute("data-hata", "true"));
      gecersiz[0].scrollIntoView({ behavior: "smooth", block: "center" });
      gecersiz[0].focus({ preventScroll: true });
      setHata("Lütfen işaretli zorunlu alanları doldurun.");
      return;
    }

    const veri = new FormData(form);
    veri.set("kvkk", "1");
    veri.set("acilis", String(acilis.current));
    setGonderiliyor(true);
    try {
      const yanit = await fetch("/api/kariyer/basvuru", { method: "POST", body: veri });
      const sonuc = (await yanit.json().catch(() => null)) as { ok?: boolean; mesaj?: string } | null;
      if (sonuc?.ok) {
        setTamam(true);
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
      }
      setHata(sonuc?.mesaj ?? "Başvuru gönderilemedi.");
    } catch {
      setHata("Bağlantı hatası. Lütfen tekrar deneyin.");
    } finally {
      setGonderiliyor(false);
    }
  }

  if (tamam) {
    return (
      <div className="border-l-4 border-brand bg-white p-12 text-center shadow-sm rounded-ui" role="status">
        <div className="mx-auto flex h-16 w-16 items-center justify-center bg-brand text-3xl font-bold text-ink">✓</div>
        <h2 className="mt-6 font-display text-3xl font-bold uppercase text-ink">Başvurunuz alındı</h2>
        <p className="mx-auto mt-3 max-w-md text-neutral-600">
          Teşekkür ederiz. Başvurunuz değerlendirildikten sonra uygun görülmeniz halinde sizinle iletişime geçilecektir.
        </p>
        <Link
          href="/kariyer"
          className="rounded-ui mt-8 inline-block bg-ink px-8 py-4 font-display text-lg font-bold uppercase tracking-wide text-white transition hover:bg-brand hover:text-ink"
        >
          Açık Pozisyonlara Dön
        </Link>
      </div>
    );
  }

  return (
    <form
      ref={formRef}
      onSubmit={gonder}
      onInput={(e) => (e.target as HTMLElement).removeAttribute("data-hata")}
      noValidate
      className="space-y-6"
    >
      <input type="hidden" name="i" value={token} />
      <div aria-hidden="true" className="absolute -left-[9999px] h-px w-px overflow-hidden">
        <label>
          Web sitesi
          <input type="text" name="website" tabIndex={-1} autoComplete="off" />
        </label>
      </div>

      <Bolum baslik="Kişisel Bilgiler">
        <Alan etiket="Ad Soyad" zorunlu>
          <input className={alanSinifi} type="text" name="ad_soyad" maxLength={150} autoComplete="name" required />
        </Alan>
        <div className="grid gap-5 sm:grid-cols-2">
          <Alan etiket="Cep Telefonu" zorunlu>
            <input
              className={alanSinifi}
              type="tel"
              name="telefon"
              inputMode="tel"
              maxLength={20}
              autoComplete="tel"
              placeholder="05xx xxx xx xx"
              required
            />
          </Alan>
          <Alan etiket="E-posta">
            <input className={alanSinifi} type="email" name="email" maxLength={190} autoComplete="email" />
          </Alan>
          <Alan etiket="Doğum Tarihi">
            <input className={alanSinifi} type="date" name="dogum_tarihi" />
          </Alan>
          <Alan etiket="Yaşadığınız Şehir" zorunlu>
            <input
              className={alanSinifi}
              type="text"
              name="sehir"
              maxLength={100}
              autoComplete="address-level2"
              required
            />
          </Alan>
        </div>
        <div>
          <span className="mb-1.5 block text-sm font-semibold text-neutral-600">Vesikalık fotoğraf</span>
          <div className="flex items-center gap-5 border-2 border-dashed border-neutral-300 bg-paper p-4 rounded-ui">
            <div className="flex h-24 w-20 shrink-0 items-center justify-center overflow-hidden rounded-ui bg-brand/15 text-3xl">
              {fotoOnizleme ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={fotoOnizleme} alt="Fotoğraf önizleme" className="h-full w-full object-cover" />
              ) : (
                <span aria-hidden="true">📷</span>
              )}
            </div>
            <div className="min-w-0">
              <p className="text-sm text-neutral-600">Yüzünüzün net göründüğü, düz fonlu bir fotoğraf (JPG/PNG)</p>
              <div className="mt-3 flex flex-wrap items-center gap-4">
                <label className="cursor-pointer border-2 border-ink px-4 py-2 text-sm font-bold uppercase text-ink transition hover:bg-ink hover:text-white rounded-ui">
                  Fotoğraf seç
                  <input
                    type="file"
                    name="foto"
                    accept="image/jpeg,image/png"
                    className="sr-only"
                    onChange={(e) => dosyaSec(e, "foto")}
                  />
                </label>
                {fotoOnizleme && (
                  <button
                    type="button"
                    onClick={() => dosyaKaldir("foto")}
                    className="text-sm font-semibold text-red-600 hover:underline"
                  >
                    Kaldır
                  </button>
                )}
              </div>
            </div>
          </div>
        </div>
      </Bolum>

      <Bolum baslik="Eğitim ve Deneyim">
        <div className="grid gap-5 sm:grid-cols-2">
          <Alan etiket="Eğitim Durumu" zorunlu>
            <select className={alanSinifi} name="egitim" required defaultValue="">
              <option value="">Seçiniz</option>
              {EGITIMLER.map((g) => (
                <option key={g} value={g}>
                  {g}
                </option>
              ))}
            </select>
          </Alan>
          <Alan etiket="Ehliyet">
            <select className={alanSinifi} name="ehliyet" defaultValue="">
              <option value="">Seçiniz</option>
              {EHLIYETLER.map((g) => (
                <option key={g} value={g}>
                  {g}
                </option>
              ))}
            </select>
          </Alan>
          <Alan etiket="Toplam Deneyim (yıl)">
            <input className={alanSinifi} type="number" name="deneyim_yili" inputMode="decimal" min={0} max={60} step={0.5} />
          </Alan>
          <Alan etiket="Net Maaş Beklentisi (₺)">
            <input className={alanSinifi} type="number" name="maas_beklentisi" inputMode="numeric" min={0} step={100} />
          </Alan>
        </div>
        <Alan
          etiket="Deneyiminizi kısaca anlatın"
          zorunlu
          ipucu="Daha önce çalıştığınız yerler, yaptığınız işler, sahip olduğunuz belgeler."
        >
          <textarea className={`${alanSinifi} min-h-28 resize-y`} name="deneyim_ozet" maxLength={3000} required />
        </Alan>
        <Alan etiket="Ne zaman işe başlayabilirsiniz?">
          <input
            className={alanSinifi}
            type="text"
            name="ise_baslama"
            maxLength={100}
            placeholder="Hemen / 15 gün sonra ..."
          />
        </Alan>
      </Bolum>

      {sorular.length > 0 && (
        <Bolum baslik="Pozisyona Özel Sorular">
          {sorular.map((soru, i) => (
            <Alan key={i} etiket={soru} zorunlu>
              <textarea className={`${alanSinifi} min-h-24 resize-y`} name={`soru[${i}]`} maxLength={1500} required />
            </Alan>
          ))}
        </Bolum>
      )}

      <Bolum baslik="Özgeçmiş (CV)">
        <div className="flex flex-wrap items-center gap-4 border-2 border-dashed border-neutral-300 bg-paper p-4 rounded-ui">
          <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-ui bg-brand/15 text-sm font-bold text-brand-deep">
            PDF
          </div>
          <div className="min-w-0 flex-1">
            <p className="truncate font-semibold text-ink">{cvAd || "CV yükleyin (isteğe bağlı)"}</p>
            <p className="text-sm text-neutral-500">PDF, en fazla 8 MB</p>
          </div>
          <label className="cursor-pointer border-2 border-ink px-4 py-2 text-sm font-bold uppercase text-ink transition hover:bg-ink hover:text-white rounded-ui">
            Dosya seç
            <input
              type="file"
              name="cv"
              accept="application/pdf"
              className="sr-only"
              onChange={(e) => dosyaSec(e, "cv")}
            />
          </label>
          {cvAd && (
            <button
              type="button"
              onClick={() => dosyaKaldir("cv")}
              className="text-sm font-semibold text-red-600 hover:underline"
            >
              Kaldır
            </button>
          )}
        </div>
      </Bolum>

      <Bolum baslik="Kişisel Verilerin Korunması">
        <div className="max-h-40 overflow-auto bg-paper p-4 text-sm leading-relaxed text-neutral-600 rounded-ui">
          Paylaştığınız kişisel veriler, 6698 sayılı KVKK kapsamında yalnızca işe alım sürecinin yürütülmesi,
          başvurunuzun değerlendirilmesi ve sizinle iletişime geçilmesi amacıyla firmamız tarafından işlenir.
          Başvurunuz, ilan kriterlerine göre ön değerlendirme için yapay zekâ destekli bir araçla analiz edilebilir; bu
          analiz yalnızca insan kaynakları çalışanlarına yol gösterir ve nihai karar insan tarafından verilir.
          Verileriniz süreç sonunda makul bir süre saklanır ve yetkisiz kişilerle paylaşılmaz. Haklarınız için
          firmamızla iletişime geçebilirsiniz.
        </div>
        <label className="flex cursor-pointer items-start gap-3 text-sm text-neutral-700">
          <input type="checkbox" name="kvkk_onay" required className="mt-0.5 h-5 w-5 shrink-0 accent-[#c8a84e]" />
          <span>
            Aydınlatma metnini okudum; kişisel verilerimin başvurum kapsamında işlenmesini ve yapay zekâ destekli ön
            değerlendirmeyi kabul ediyorum.
          </span>
        </label>
      </Bolum>

      {hata && (
        <div role="alert" className="rounded-ui border-l-4 border-red-600 bg-red-50 px-5 py-4 text-sm font-semibold text-red-700">
          {hata}
        </div>
      )}

      <button
        type="submit"
        disabled={gonderiliyor}
        className="w-full rounded-ui bg-ink px-8 py-5 font-display text-xl font-bold uppercase tracking-wide text-white transition hover:bg-brand hover:text-ink disabled:cursor-progress disabled:opacity-60"
      >
        {gonderiliyor ? "Gönderiliyor..." : "Başvuruyu Gönder"}
      </button>
      <p className="text-center text-xs text-neutral-500">Başvurunuz güvenli bağlantı üzerinden iletilir.</p>
    </form>
  );
}
