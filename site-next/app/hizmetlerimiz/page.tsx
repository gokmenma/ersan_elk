import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import PageHero from "@/components/PageHero";
import { hizmetGorseli, hizmetleriGetir } from "@/lib/hizmetler";

export const revalidate = 60;

export const metadata: Metadata = {
  title: "Hizmetlerimiz",
  description: "Sayaç okuma, kesme-açma ve sayaç değişimi hizmetlerimiz.",
};

export default async function HizmetlerPage() {
  const { hizmetler, hata } = await hizmetleriGetir();

  return (
    <>
      <PageHero title="Hizmetlerimiz" kicker="Saha Çözümlerimiz" text="Elektrik, su ve doğalgaz alanında uçtan uca saha hizmetleri." />
      <section className="bg-paper py-24">
        <div className="mx-auto max-w-7xl px-6">
          {hata || hizmetler.length === 0 ? (
            <div className="border border-neutral-200 bg-white p-12 text-center rounded-ui">
              <h2 className="font-display text-3xl font-bold uppercase text-ink">
                {hata ? "Hizmetler şu anda görüntülenemiyor" : "Henüz yayınlanmış hizmet bulunmuyor"}
              </h2>
              <p className="mt-3 text-neutral-600">Lütfen biraz sonra tekrar deneyin veya bizimle iletişime geçin.</p>
            </div>
          ) : (
            <div className="grid gap-8 md:grid-cols-2">
              {hizmetler.map((hizmet) => {
                const gorsel = hizmetGorseli(hizmet);
                return (
                  <Link
                    key={hizmet.slug}
                    href={`/hizmetlerimiz/${hizmet.slug}`}
                    className="group grid overflow-hidden bg-white shadow-sm transition hover:shadow-xl sm:grid-cols-[220px_1fr] rounded-ui"
                  >
                    <div className="relative min-h-52 overflow-hidden bg-ink-soft">
                      {gorsel && (
                        <Image
                          src={gorsel}
                          alt={hizmet.baslik}
                          fill
                          sizes="(min-width:640px) 220px, 100vw"
                          className="object-cover transition duration-700 group-hover:scale-110"
                        />
                      )}
                    </div>
                    <div className="p-8">
                      <h2 className="font-display text-2xl font-bold uppercase leading-tight text-ink transition group-hover:text-brand-deep">
                        {hizmet.baslik}
                      </h2>
                      {hizmet.kisa_aciklama && <p className="mt-3 text-sm leading-relaxed text-neutral-600">{hizmet.kisa_aciklama}</p>}
                      <span className="mt-5 inline-block font-display text-base font-bold uppercase tracking-wide text-brand-deep">Detaylar →</span>
                    </div>
                  </Link>
                );
              })}
            </div>
          )}
        </div>
      </section>
    </>
  );
}
