import type { Metadata } from "next";
import IletisimFormu from "@/components/IletisimFormu";
import PageHero from "@/components/PageHero";
import { siteAyarlari } from "@/lib/ayarlar";

export const metadata: Metadata = {
  title: "İletişim",
  description: "Ersan Ltd. Şti. iletişim bilgileri ve iletişim formu.",
};

export default async function IletisimPage() {
  const { site } = await siteAyarlari();
  const kartlar = [
    { baslik: "Adres", deger: site.address, href: undefined },
    { baslik: "Telefon", deger: site.phone, href: `tel:${site.phone.replace(/\s/g, "")}` },
    { baslik: "E-posta", deger: site.email, href: `mailto:${site.email}` },
    { baslik: "Çalışma Saatleri", deger: site.hours, href: undefined },
  ];

  return (
    <>
      <PageHero title="İletişim" kicker="Bize Ulaşın" text="Projeniz için bizimle iletişime geçin, size en kısa sürede dönelim." />
      <section className="bg-paper py-20">
        <div className="mx-auto grid max-w-7xl gap-10 px-6 lg:grid-cols-[1fr_1.5fr]">
          <div className="space-y-5">
            {kartlar.map((kart) => (
              <div key={kart.baslik} className="rounded-ui border-l-4 border-brand bg-white p-6 shadow-sm">
                <h2 className="font-display text-xl font-bold uppercase text-ink">{kart.baslik}</h2>
                {kart.href ? (
                  <a href={kart.href} className="mt-2 block break-words text-neutral-600 transition hover:text-brand-deep">
                    {kart.deger}
                  </a>
                ) : (
                  <p className="mt-2 text-neutral-600">{kart.deger}</p>
                )}
              </div>
            ))}
          </div>
          <IletisimFormu />
        </div>
      </section>
    </>
  );
}
