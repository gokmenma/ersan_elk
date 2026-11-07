import type { Metadata } from "next";
import Link from "next/link";
import PageHero from "@/components/PageHero";
import { basvuruUrl, ilanlariGetir, type Ilan } from "@/lib/ilanlar";
import { siteAyarlari } from "@/lib/ayarlar";

export const revalidate = 60;

export const metadata: Metadata = {
  title: "Kariyer",
  description: "Ersan Ltd. Şti. açık pozisyonları ve kariyer fırsatları.",
};

function tarih(deger: string): string {
  const d = new Date(deger.replace(" ", "T"));
  return Number.isNaN(d.getTime())
    ? ""
    : d.toLocaleDateString("tr-TR", { day: "numeric", month: "long", year: "numeric" });
}

function satirlar(metin: string | null): string[] {
  return (metin ?? "")
    .split(/\r?\n/)
    .map((s) => s.trim())
    .filter(Boolean);
}

function Etiket({ children }: { children: React.ReactNode }) {
  return (
    <span className="inline-flex items-center gap-2 bg-ink/5 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-ink/70">
      {children}
    </span>
  );
}

function IlanKarti({ ilan }: { ilan: Ilan }) {
  const nitelikler = satirlar(ilan.aranan_nitelikler);
  const yayin = tarih(ilan.yayin_tarihi);

  return (
    <article className="group border-l-4 border-brand bg-white p-8 shadow-sm transition hover:shadow-xl rounded-ui">
      <div className="flex flex-wrap items-start justify-between gap-6">
        <div className="min-w-0 flex-1">
          <h2 className="font-display text-3xl font-bold uppercase leading-tight text-ink">{ilan.baslik}</h2>
          <div className="mt-3 flex flex-wrap gap-2">
            {ilan.calisma_yeri && (
              <Etiket>
                <svg viewBox="0 0 20 20" className="h-3.5 w-3.5 text-brand-deep" fill="currentColor" aria-hidden="true">
                  <path d="M10 1a6 6 0 0 0-6 6c0 4.5 6 12 6 12s6-7.5 6-12a6 6 0 0 0-6-6zm0 8.2A2.2 2.2 0 1 1 10 4.8a2.2 2.2 0 0 1 0 4.4z" />
                </svg>
                {ilan.calisma_yeri}
              </Etiket>
            )}
            {ilan.departman && <Etiket>{ilan.departman}</Etiket>}
            {ilan.gorev && ilan.gorev !== ilan.departman && ilan.gorev !== ilan.baslik && <Etiket>{ilan.gorev}</Etiket>}
            {yayin && <Etiket>{yayin}</Etiket>}
          </div>
        </div>
        <Link
          href={basvuruUrl(ilan.token)}
          className="rounded-ui bg-ink px-8 py-4 font-display text-lg font-bold uppercase tracking-wide text-white transition hover:bg-brand hover:text-ink"
        >
          Başvur
        </Link>
      </div>

      {ilan.aciklama && (
        <p className="mt-6 whitespace-pre-line text-base leading-relaxed text-neutral-600">{ilan.aciklama}</p>
      )}

      {nitelikler.length > 0 && (
        <div className="mt-6 border-t border-neutral-200 pt-6">
          <h3 className="font-display text-xl font-bold uppercase tracking-wide text-ink">Aranan Nitelikler</h3>
          <ul className="mt-4 grid gap-3 sm:grid-cols-2">
            {nitelikler.map((satir) => (
              <li key={satir} className="flex items-start gap-3 text-sm text-neutral-700">
                <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center bg-brand text-ink">
                  <svg viewBox="0 0 20 20" className="h-3 w-3" fill="currentColor" aria-hidden="true">
                    <path d="M7.7 14.3 3.4 10l1.4-1.4 2.9 2.9 7.5-7.5L16.6 5z" />
                  </svg>
                </span>
                {satir}
              </li>
            ))}
          </ul>
        </div>
      )}
    </article>
  );
}

export default async function KariyerPage() {
  const { site } = await siteAyarlari();
  const { ilanlar, hata } = await ilanlariGetir();

  return (
    <>
      <PageHero
        title="Kariyer"
        kicker="Bizimle Çalışın"
        text="Sahada birlikte büyüyen, disiplinli ve güvenilir bir ekibin parçası olun."
      />

      <section className="bg-paper py-20">
        <div className="mx-auto max-w-5xl px-6">
          {hata ? (
            <div className="border border-neutral-200 bg-white p-12 text-center rounded-ui">
              <h2 className="font-display text-3xl font-bold uppercase text-ink">İlanlar şu anda görüntülenemiyor</h2>
              <p className="mt-3 text-neutral-600">
                Lütfen biraz sonra tekrar deneyin ya da bizimle doğrudan iletişime geçin: {site.phone}
              </p>
            </div>
          ) : ilanlar.length === 0 ? (
            <div className="border border-neutral-200 bg-white p-12 text-center rounded-ui">
              <h2 className="font-display text-3xl font-bold uppercase text-ink">
                Şu anda açık pozisyon bulunmamaktadır
              </h2>
              <p className="mt-3 text-neutral-600">Gelecekteki fırsatlar için bizi takip etmeye devam edin.</p>
              <Link
                href="/iletisim"
                className="rounded-ui mt-8 inline-block bg-brand px-8 py-4 font-display text-lg font-bold uppercase tracking-wide text-ink transition hover:bg-ink hover:text-white"
              >
                İletişime Geçin
              </Link>
            </div>
          ) : (
            <>
              <p className="mb-8 font-display text-xl font-semibold uppercase tracking-wide text-neutral-500">
                {ilanlar.length} açık pozisyon
              </p>
              <div className="space-y-6">
                {ilanlar.map((ilan) => (
                  <IlanKarti key={ilan.token} ilan={ilan} />
                ))}
              </div>
              <p className="mt-10 text-center text-sm text-neutral-500">
                Başvurular güvenli başvuru formumuz üzerinden alınır ve KVKK kapsamında değerlendirilir.
              </p>
            </>
          )}
        </div>
      </section>
    </>
  );
}
