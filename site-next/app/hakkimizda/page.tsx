import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import PageHero from "@/components/PageHero";
import Counter from "@/components/Counter";
import { siteAyarlari } from "@/lib/ayarlar";
import { icerikGetir } from "@/lib/icerik";

export const metadata: Metadata = {
  title: "Hakkımızda",
  description: "Ersan Ltd. Şti. hakkında, misyonumuz, vizyonumuz ve çalışma yaklaşımımız.",
};

export const revalidate = 60;

export default async function HakkimizdaPage() {
  const [{ istatistikler }, { about, steps }] = await Promise.all([icerikGetir(), siteAyarlari()]);
  return (
    <>
      <PageHero title="Hakkımızda" kicker="Biz Kimiz" text="Sahada güven, raporlamada şeffaflık." />

      <section className="bg-paper py-24">
        <div className="mx-auto grid max-w-7xl items-center gap-16 px-6 lg:grid-cols-2">
          <div className="relative aspect-[4/3] overflow-hidden rounded-ui">
            <Image src="/images/hero-1.jpg" alt="Ersan saha ekibi" fill sizes="(min-width:1024px) 600px, 100vw" className="object-cover" />
          </div>
          <div>
            <h2 className="font-display text-4xl font-extrabold uppercase leading-none text-ink sm:text-5xl">{about.title}</h2>
            <p className="mt-6 text-lg leading-relaxed text-neutral-600">{about.text}</p>
            <ul className="mt-8 grid gap-3 sm:grid-cols-2">
              {about.checks.map((item) => (
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
          </div>
        </div>
      </section>

      <section className="bg-ink py-20">
        <div className="mx-auto grid max-w-7xl gap-8 px-6 md:grid-cols-2">
          <div className="rounded-ui border-t-4 border-brand bg-ink-soft p-10">
            <h3 className="font-display text-3xl font-bold uppercase text-white">Misyonumuz</h3>
            <p className="mt-4 leading-relaxed text-white/70">{about.mission}</p>
          </div>
          <div className="rounded-ui border-t-4 border-white/40 bg-ink-soft p-10">
            <h3 className="font-display text-3xl font-bold uppercase text-white">Vizyonumuz</h3>
            <p className="mt-4 leading-relaxed text-white/70">{about.vision}</p>
          </div>
        </div>
      </section>

      {istatistikler.length > 0 && (
<section className="bg-brand py-20">
        <div className="mx-auto grid max-w-7xl gap-10 px-6 sm:grid-cols-2 lg:grid-cols-4">
          {istatistikler.map((stat) => (
            <div key={stat.etiket} className="border-l-4 border-ink pl-6">
              <div className="font-display text-6xl font-extrabold leading-none text-ink">
                <Counter value={stat.deger} suffix={stat.sonek} />
              </div>
              <div className="mt-2 font-display text-lg font-semibold uppercase tracking-wide text-ink/80">{stat.etiket}</div>
            </div>
          ))}
        </div>
      </section>
)}

      <section className="bg-paper py-24">
        <div className="mx-auto max-w-7xl px-6">
          <h2 className="font-display text-4xl font-extrabold uppercase leading-none text-ink sm:text-5xl">Çalışma Sürecimiz</h2>
          <div className="mt-12 grid gap-px bg-neutral-300 sm:grid-cols-2 lg:grid-cols-4">
            {steps.map((step) => (
              <div key={step.no} className="bg-paper p-8 rounded-ui">
                <div className="font-display text-6xl font-extrabold leading-none text-brand/40">{step.no}</div>
                <h3 className="mt-4 font-display text-2xl font-bold uppercase text-ink">{step.title}</h3>
                <p className="mt-3 text-sm leading-relaxed text-neutral-600">{step.text}</p>
              </div>
            ))}
          </div>
          <div className="mt-14 text-center">
            <Link href="/iletisim" className="rounded-ui inline-block bg-ink px-8 py-4 font-display text-lg font-bold uppercase tracking-wide text-white transition hover:bg-brand hover:text-ink">
              Bizimle İletişime Geçin
            </Link>
          </div>
        </div>
      </section>
    </>
  );
}
