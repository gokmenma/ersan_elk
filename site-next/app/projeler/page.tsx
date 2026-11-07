import type { Metadata } from "next";
import PageHero from "@/components/PageHero";
import ProjeListesi from "@/components/ProjeListesi";
import { projeGorseli, projeleriGetir } from "@/lib/projeler";

export const revalidate = 60;

export const metadata: Metadata = {
  title: "Projeler",
  description: "Tamamladığımız sayaç okuma, kesme-açma ve değişim projeleri.",
};

export default async function ProjelerPage() {
  const { projeler, hata } = await projeleriGetir();

  return (
    <>
      <PageHero title="Projeler" kicker="Tamamladığımız İşler" text="Sahada yürüttüğümüz projelerden örnekler." />
      <section className="bg-paper py-24">
        <div className="mx-auto max-w-7xl px-6">
          {hata || projeler.length === 0 ? (
            <div className="border border-neutral-200 bg-white p-12 text-center">
              <h2 className="font-display text-3xl font-bold uppercase text-ink">
                {hata ? "Projeler şu anda görüntülenemiyor" : "Henüz yayınlanmış proje bulunmuyor"}
              </h2>
              <p className="mt-3 text-neutral-600">Lütfen biraz sonra tekrar deneyin veya bizimle iletişime geçin.</p>
            </div>
          ) : (
            <ProjeListesi
              projeler={projeler.map((p) => ({
                slug: p.slug,
                baslik: p.baslik,
                kategori: p.kategori,
                konum: p.konum,
                kisa_aciklama: p.kisa_aciklama,
                gorsel: projeGorseli(p),
              }))}
            />
          )}
        </div>
      </section>
    </>
  );
}
