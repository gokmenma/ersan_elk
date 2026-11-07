import type { Metadata } from "next";
import Image from "next/image";
import PageHero from "@/components/PageHero";
import { icerikGetir, referansLogosu } from "@/lib/icerik";

export const revalidate = 60;

export const metadata: Metadata = {
  title: "Referanslar",
  description: "Müşterilerimizin Ersan Ltd. Şti. hakkındaki görüşleri.",
};

export default async function ReferanslarPage() {
  const { referanslar } = await icerikGetir();
  const logolular = referanslar.filter((r) => referansLogosu(r));

  return (
    <>
      <PageHero title="Referanslar" kicker="Müşteri Görüşleri" text="Birlikte çalıştığımız kurumların bizim hakkımızda söyledikleri." />
      <section className="bg-paper py-24">
        <div className="mx-auto max-w-7xl px-6">
          {referanslar.length === 0 ? (
            <div className="border border-neutral-200 bg-white p-12 text-center">
              <h2 className="font-display text-3xl font-bold uppercase text-ink">Henüz yayınlanmış referans bulunmuyor</h2>
              <p className="mt-3 text-neutral-600">Lütfen daha sonra tekrar ziyaret edin.</p>
            </div>
          ) : (
            <div className="grid gap-8 md:grid-cols-3">
              {referanslar.map((item, i) => {
                const altSatir = [item.kisi, item.unvan].filter(Boolean).join(" · ");
                return (
                  <figure key={`${item.kurum}-${i}`} className="border-t-4 border-brand bg-white p-8 shadow-sm">
                    <svg viewBox="0 0 48 48" className="h-9 w-9 text-brand" fill="currentColor" aria-hidden="true">
                      <path d="M6 28c0-9 5-15 13-17l1 4c-5 2-7 5-7 9h6v13H6V28zm22 0c0-9 5-15 13-17l1 4c-5 2-7 5-7 9h6v13H28V28z" />
                    </svg>
                    <blockquote className="mt-4 leading-relaxed text-neutral-700">{item.yorum}</blockquote>
                    <figcaption className="mt-6">
                      <div className="font-display text-xl font-bold uppercase text-ink">{item.kurum}</div>
                      {altSatir && <div className="text-sm text-neutral-500">{altSatir}</div>}
                    </figcaption>
                  </figure>
                );
              })}
            </div>
          )}
        </div>
      </section>
      {logolular.length > 0 && (
        <section className="border-t border-neutral-200 bg-white py-16">
          <div className="mx-auto grid max-w-7xl grid-cols-2 gap-6 px-6 md:grid-cols-3 lg:grid-cols-6">
            {logolular.map((r, i) => (
              <div key={`${r.kurum}-${i}`} className="flex h-24 items-center justify-center border border-neutral-200 p-4">
                <Image src={referansLogosu(r) as string} alt={r.kurum} width={160} height={64} className="h-full w-auto object-contain" />
              </div>
            ))}
          </div>
        </section>
      )}
    </>
  );
}
