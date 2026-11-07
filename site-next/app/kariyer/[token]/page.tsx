import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import BasvuruFormu from "@/components/BasvuruFormu";
import IlanGoruntulemeSayaci from "@/components/IlanGoruntulemeSayaci";
import PageHero from "@/components/PageHero";
import { ilanGetir } from "@/lib/ilanlar";

export const revalidate = 60;

type Params = { params: Promise<{ token: string }> };

export async function generateMetadata({ params }: Params): Promise<Metadata> {
  const { token } = await params;
  const ilan = await ilanGetir(token);
  return {
    title: ilan ? `${ilan.baslik} - İş Başvurusu` : "Başvuru Bağlantısı Geçersiz",
    robots: { index: false, follow: false },
  };
}

function satirlar(metin: string | null): string[] {
  return (metin ?? "")
    .split(/\r?\n/)
    .map((s) => s.trim())
    .filter(Boolean);
}

export default async function BasvuruPage({ params }: Params) {
  const { token } = await params;
  const ilan = await ilanGetir(token);
  if (!ilan) notFound();

  const nitelikler = satirlar(ilan.aranan_nitelikler);
  const sonTarih = ilan.gecerlilik_tarihi ? new Date(ilan.gecerlilik_tarihi.replace(" ", "T")) : null;

  return (
    <>
      <IlanGoruntulemeSayaci token={token} />
      <PageHero title={ilan.baslik} kicker="İş Başvurusu" text={ilan.aciklama ?? undefined} />

      <section className="bg-paper py-16">
        <div className="mx-auto max-w-3xl px-6 lg:max-w-6xl">
          <Link href="/kariyer" className="mb-6 inline-block text-sm font-semibold text-brand-deep hover:underline">
            ← Tüm açık pozisyonlar
          </Link>

          <div className="grid items-start gap-6 lg:grid-cols-[320px_minmax(0,1fr)] lg:gap-8">
            <aside className="rounded-ui border-l-4 border-brand bg-white p-6 shadow-sm lg:sticky lg:top-32 lg:max-h-[calc(100vh-9rem)] lg:overflow-y-auto">
              <div className="hidden lg:block">
                <span className="text-xs font-semibold uppercase tracking-[0.2em] text-brand-deep">İş Başvurusu</span>
                <h2 className="mt-2 font-display text-2xl font-extrabold uppercase leading-tight text-ink">
                  {ilan.baslik}
                </h2>
              </div>

              <div className="flex flex-wrap gap-2 lg:mt-4">
                {[ilan.departman, ilan.calisma_yeri].filter(Boolean).map((e) => (
                  <span
                    key={e}
                    className="rounded-ui bg-ink/5 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-ink/70"
                  >
                    {e}
                  </span>
                ))}
                {sonTarih && !Number.isNaN(sonTarih.getTime()) && (
                  <span className="rounded-ui bg-ink/5 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-ink/70">
                    Son başvuru:{" "}
                    {sonTarih.toLocaleDateString("tr-TR", { day: "numeric", month: "long", year: "numeric" })}
                  </span>
                )}
              </div>

              {nitelikler.length > 0 && (
                <div className="mt-6 border-t border-neutral-200 pt-5">
                  <h3 className="font-display text-lg font-bold uppercase text-ink">Aranan Nitelikler</h3>
                  <ul className="mt-3 space-y-2">
                    {nitelikler.map((satir) => (
                      <li key={satir} className="flex items-start gap-3 text-sm text-neutral-700">
                        <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-ui bg-brand text-ink">
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
            </aside>

            <BasvuruFormu token={token} sorular={ilan.sorular} />
          </div>
        </div>
      </section>
    </>
  );
}
