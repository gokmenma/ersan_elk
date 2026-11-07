import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import { notFound } from "next/navigation";
import PageHero from "@/components/PageHero";
import { siteAyarlari } from "@/lib/ayarlar";
import { projeGetir, projeGorseli, projeleriGetir } from "@/lib/projeler";

export const revalidate = 60;

type Params = { params: Promise<{ slug: string }> };

export async function generateStaticParams() {
  const { projeler } = await projeleriGetir();
  return projeler.map((p) => ({ slug: p.slug }));
}

export async function generateMetadata({ params }: Params): Promise<Metadata> {
  const { slug } = await params;
  const proje = await projeGetir(slug);
  return proje ? { title: proje.baslik, description: proje.kisa_aciklama ?? undefined } : {};
}

export default async function ProjeDetayPage({ params }: Params) {
  const { site } = await siteAyarlari();
  const { slug } = await params;
  const [proje, { projeler }] = await Promise.all([projeGetir(slug), projeleriGetir()]);
  if (!proje) notFound();
  const gorsel = projeGorseli(proje);

  const bilgiler = [
    { etiket: "Kategori", deger: proje.kategori },
    { etiket: "Müşteri", deger: proje.musteri },
    { etiket: "Konum", deger: proje.konum },
    { etiket: "Yıl", deger: proje.proje_yili ? String(proje.proje_yili) : null },
  ].filter((b): b is { etiket: string; deger: string } => !!b.deger);

  const digerleri = projeler.filter((p) => p.slug !== proje.slug).slice(0, 4);

  return (
    <>
      <PageHero title={proje.baslik} kicker="Proje Detayı" text={proje.kisa_aciklama ?? undefined} />
      <section className="bg-paper py-24">
        <div className="mx-auto grid max-w-7xl gap-14 px-6 lg:grid-cols-[1.6fr_1fr]">
          <div>
            {gorsel && (
              <div className="relative aspect-[16/9] overflow-hidden rounded-ui bg-ink-soft">
                <Image src={gorsel} alt={proje.baslik} fill sizes="(min-width:1024px) 800px, 100vw" className="object-cover" priority />
              </div>
            )}
            {proje.aciklama && <p className="mt-8 whitespace-pre-line text-lg leading-relaxed text-neutral-600">{proje.aciklama}</p>}
          </div>

          <aside className="space-y-8 lg:sticky lg:top-32 lg:self-start">
            {bilgiler.length > 0 && (
              <div className="rounded-ui bg-white p-6 shadow-sm">
                <h2 className="font-display text-2xl font-bold uppercase text-ink">Proje Bilgileri</h2>
                <dl className="mt-4 divide-y divide-neutral-200">
                  {bilgiler.map((b) => (
                    <div key={b.etiket} className="flex justify-between gap-4 py-3 text-sm">
                      <dt className="font-semibold text-neutral-500">{b.etiket}</dt>
                      <dd className="text-right font-medium text-ink">{b.deger}</dd>
                    </div>
                  ))}
                </dl>
              </div>
            )}

            {digerleri.length > 0 && (
              <div className="rounded-ui bg-white p-6 shadow-sm">
                <h2 className="font-display text-2xl font-bold uppercase text-ink">Diğer Projeler</h2>
                <ul className="mt-4 divide-y divide-neutral-200">
                  {digerleri.map((p) => (
                    <li key={p.slug}>
                      <Link href={`/projeler/${p.slug}`} className="block py-3 text-sm font-semibold text-ink transition hover:text-brand-deep">
                        {p.baslik}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            <div className="bg-ink p-8 text-white">
              <h2 className="font-display text-3xl font-bold uppercase leading-tight">Benzer Bir İşiniz mi Var?</h2>
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
