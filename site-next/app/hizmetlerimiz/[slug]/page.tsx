import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import { notFound } from "next/navigation";
import PageHero from "@/components/PageHero";
import { siteAyarlari } from "@/lib/ayarlar";
import { hizmetGetir, hizmetGorseli, hizmetleriGetir } from "@/lib/hizmetler";

export const revalidate = 60;

type Params = { params: Promise<{ slug: string }> };

export async function generateStaticParams() {
  const { hizmetler } = await hizmetleriGetir();
  return hizmetler.map((h) => ({ slug: h.slug }));
}

export async function generateMetadata({ params }: Params): Promise<Metadata> {
  const { slug } = await params;
  const hizmet = await hizmetGetir(slug);
  return hizmet
    ? { title: hizmet.baslik, description: hizmet.kisa_aciklama ?? undefined }
    : {};
}

export default async function HizmetDetayPage({ params }: Params) {
  const { site } = await siteAyarlari();
  const { slug } = await params;
  const [hizmet, { hizmetler }] = await Promise.all([hizmetGetir(slug), hizmetleriGetir()]);
  if (!hizmet) notFound();
  const gorsel = hizmetGorseli(hizmet);

  return (
    <>
      <PageHero title={hizmet.baslik} kicker="Hizmet Detayı" text={hizmet.kisa_aciklama ?? undefined} />
      <section className="bg-paper py-24">
        <div className="mx-auto grid max-w-7xl gap-14 px-6 lg:grid-cols-[1.6fr_1fr]">
          <div>
            {gorsel && (
              <div className="relative aspect-[16/9] overflow-hidden rounded-ui">
                <Image src={gorsel} alt={hizmet.baslik} fill sizes="(min-width:1024px) 800px, 100vw" className="object-cover" priority />
              </div>
            )}
            {hizmet.aciklama && <p className="mt-8 whitespace-pre-line text-lg leading-relaxed text-neutral-600">{hizmet.aciklama}</p>}

            {hizmet.ozellikler.length > 0 && (
              <>
                <h2 className="mt-12 font-display text-3xl font-bold uppercase text-ink">Neler Sunuyoruz</h2>
                <ul className="mt-5 grid gap-3 sm:grid-cols-2">
                  {hizmet.ozellikler.map((item) => (
                    <li key={item} className="flex items-start gap-3 text-sm font-medium text-ink">
                      <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center bg-brand text-ink">
                        <svg viewBox="0 0 20 20" className="h-3 w-3" fill="currentColor" aria-hidden="true">
                          <path d="M7.7 14.3 3.4 10l1.4-1.4 2.9 2.9 7.5-7.5L16.6 5z" />
                        </svg>
                      </span>
                      {item}
                    </li>
                  ))}
                </ul>
              </>
            )}

            {hizmet.adimlar.length > 0 && (
              <>
                <h2 className="mt-12 font-display text-3xl font-bold uppercase text-ink">Nasıl Çalışıyoruz</h2>
                <ol className="mt-5 space-y-4">
                  {hizmet.adimlar.map((item, i) => (
                    <li key={item} className="flex items-center gap-5 border-l-4 border-brand bg-white p-5 shadow-sm rounded-ui">
                      <span className="font-display text-3xl font-extrabold text-brand/60">0{i + 1}</span>
                      <span className="font-medium text-ink">{item}</span>
                    </li>
                  ))}
                </ol>
              </>
            )}
          </div>

          <aside className="space-y-8 lg:sticky lg:top-32 lg:self-start">
            <div className="bg-white p-6 shadow-sm rounded-ui">
              <h3 className="font-display text-2xl font-bold uppercase text-ink">Diğer Hizmetler</h3>
              <ul className="mt-4 divide-y divide-neutral-200">
                {hizmetler.map((s) => (
                  <li key={s.slug}>
                    <Link
                      href={`/hizmetlerimiz/${s.slug}`}
                      className={`block py-3 text-sm font-semibold transition hover:text-brand-deep ${s.slug === slug ? "text-brand-deep" : "text-ink"}`}
                    >
                      {s.baslik}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
            <div className="bg-ink p-8 text-white rounded-ui">
              <h3 className="font-display text-3xl font-bold uppercase leading-tight">Teklif Almak İster misiniz?</h3>
              <a href={`tel:${site.phone.replace(/\s/g, "")}`} className="mt-4 block font-display text-2xl font-bold text-brand">
                {site.phone}
              </a>
              <Link
                href="/iletisim"
                className="rounded-ui mt-6 inline-block bg-brand px-7 py-3 font-display text-base font-bold uppercase tracking-wide text-ink transition hover:bg-white"
              >
                İletişime Geçin
              </Link>
            </div>
          </aside>
        </div>
      </section>
    </>
  );
}
