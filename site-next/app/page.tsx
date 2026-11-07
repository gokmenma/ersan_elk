import Image from "next/image";
import Link from "next/link";
import Hero from "@/components/Hero";
import Counter from "@/components/Counter";
import Testimonials from "@/components/Testimonials";
import { siteAyarlari } from "@/lib/ayarlar";
import { hizmetGorseli, hizmetleriGetir } from "@/lib/hizmetler";
import { icerikGetir, referansLogosu } from "@/lib/icerik";
import { projeGorseli, projeleriGetir } from "@/lib/projeler";

export const revalidate = 60;

function SectionTitle({ kicker, title, light = false, center = false }: { kicker: string; title: string; light?: boolean; center?: boolean }) {
  return (
    <div className={`max-w-2xl ${center ? "mx-auto text-center" : ""}`}>
      <span className="inline-block border-l-4 border-brand pl-3 font-display text-lg font-semibold uppercase tracking-[0.2em] text-brand-deep">
        {kicker}
      </span>
      <h2
        className={`mt-3 font-display text-4xl font-extrabold uppercase leading-none sm:text-5xl ${light ? "text-white" : "text-ink"}`}
      >
        {title}
      </h2>
    </div>
  );
}

export default async function Home() {
  const [{ hizmetler }, { projeler }, { referanslar, sss, istatistikler }, { site, about, steps, slides }] = await Promise.all([hizmetleriGetir(), projeleriGetir(), icerikGetir(), siteAyarlari()]);
  return (
    <>
      <Hero slides={slides} />

      <section className="bg-paper py-24">
        <div className="mx-auto grid max-w-7xl items-center gap-16 px-6 lg:grid-cols-2">
          <div className="relative">
            <div className="relative aspect-[4/5] overflow-hidden rounded-ui">
              <Image src="/images/hero-1.jpg" alt="Ersan saha ekibi" fill sizes="(min-width:1024px) 600px, 100vw" className="object-cover" />
            </div>
            <div className="absolute -bottom-8 -right-4 bg-brand px-8 py-6 text-ink shadow-xl sm:right-8">
              <div className="font-display text-6xl font-extrabold leading-none">15+</div>
              <div className="font-display text-lg font-semibold uppercase tracking-wide">Yıllık Tecrübe</div>
            </div>
          </div>

          <div>
            <SectionTitle kicker="Hakkımızda" title={about.title} />
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
            <div className="mt-10 grid gap-5 sm:grid-cols-2">
              <div className="border-t-4 border-brand bg-white p-6 shadow-sm rounded-ui">
                <h3 className="font-display text-2xl font-bold uppercase text-ink">Misyonumuz</h3>
                <p className="mt-2 text-sm leading-relaxed text-neutral-600">{about.mission}</p>
              </div>
              <div className="border-t-4 border-ink bg-white p-6 shadow-sm rounded-ui">
                <h3 className="font-display text-2xl font-bold uppercase text-ink">Vizyonumuz</h3>
                <p className="mt-2 text-sm leading-relaxed text-neutral-600">{about.vision}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="bg-ink py-24">
        <div className="mx-auto max-w-7xl px-6">
          <div className="flex flex-wrap items-end justify-between gap-6">
            <SectionTitle kicker="Hizmetlerimiz" title="Saha Çözümlerimiz" light />
            <Link
              href="/hizmetlerimiz"
              className="rounded-ui border border-white/30 px-7 py-3 font-display text-base font-bold uppercase tracking-wide text-white transition hover:border-brand hover:text-brand"
            >
              Tüm Hizmetler
            </Link>
          </div>
          <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {hizmetler.slice(0, 4).map((service, i) => {
              const gorsel = hizmetGorseli(service);
              return (
                <Link
                  key={service.slug}
                  href={`/hizmetlerimiz/${service.slug}`}
                  className="group relative block overflow-hidden rounded-ui bg-ink-soft"
                >
                  <div className="relative aspect-[3/4]">
                    {gorsel && (
                      <Image
                        src={gorsel}
                        alt={service.baslik}
                        fill
                        sizes="(min-width:1024px) 25vw, 50vw"
                        className="object-cover transition duration-700 group-hover:scale-110"
                      />
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-ink via-ink/50 to-transparent" />
                  </div>
                  <div className="absolute inset-x-0 bottom-0 p-6">
                    <span className="font-display text-sm font-bold tracking-[0.2em] text-brand">0{i + 1}</span>
                    <h3 className="mt-1 font-display text-2xl font-bold uppercase leading-tight text-white">{service.baslik}</h3>
                    {service.kisa_aciklama && (
                      <p className="mt-2 max-h-0 overflow-hidden text-sm leading-relaxed text-white/70 opacity-0 transition-all duration-500 group-hover:max-h-24 group-hover:opacity-100">
                        {service.kisa_aciklama}
                      </p>
                    )}
                  </div>
                  <span className="absolute left-0 top-0 h-1 w-0 bg-brand transition-all duration-500 group-hover:w-full" />
                </Link>
              );
            })}
          </div>
        </div>
      </section>

      <section className="bg-paper py-24">
        <div className="mx-auto max-w-7xl px-6">
          <SectionTitle kicker="Çalışma Sürecimiz" title="İşi Nasıl Yürütüyoruz" />
          <div className="mt-14 grid gap-px bg-neutral-300 sm:grid-cols-2 lg:grid-cols-4">
            {steps.map((step) => (
              <div key={step.no} className="bg-paper p-8 transition hover:bg-white rounded-ui">
                <div className="font-display text-6xl font-extrabold leading-none text-brand/40">{step.no}</div>
                <h3 className="mt-4 font-display text-2xl font-bold uppercase text-ink">{step.title}</h3>
                <p className="mt-3 text-sm leading-relaxed text-neutral-600">{step.text}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {istatistikler.length > 0 && (
<section className="relative overflow-hidden bg-brand py-20">
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
          <div className="flex flex-wrap items-end justify-between gap-6">
            <SectionTitle kicker="Son Projeler" title="Tamamladığımız İşler" />
            <Link
              href="/projeler"
              className="rounded-ui border border-ink/30 px-7 py-3 font-display text-base font-bold uppercase tracking-wide text-ink transition hover:border-brand-deep hover:text-brand-deep"
            >
              Tüm Projeler
            </Link>
          </div>
          <div className="mt-14 grid gap-8 md:grid-cols-3">
            {projeler.slice(0, 3).map((project) => {
              const gorsel = projeGorseli(project);
              return (
                <Link key={project.slug} href={`/projeler/${project.slug}`} className="group block">
                  <div className="relative aspect-[4/3] overflow-hidden rounded-ui bg-ink-soft">
                    {gorsel && (
                      <Image
                        src={gorsel}
                        alt={project.baslik}
                        fill
                        sizes="(min-width:768px) 33vw, 100vw"
                        className="object-cover transition duration-700 group-hover:scale-105"
                      />
                    )}
                    {project.kategori && (
                      <span className="absolute left-3 top-3 rounded-ui bg-brand px-4 py-2 font-display text-sm font-bold uppercase tracking-wide text-ink">
                        {project.kategori}
                      </span>
                    )}
                  </div>
                  <h3 className="mt-5 font-display text-2xl font-bold uppercase text-ink transition group-hover:text-brand-deep">
                    {project.baslik}
                  </h3>
                </Link>
              );
            })}
          </div>
        </div>
      </section>

      {referanslar.length > 0 && (
<section className="bg-white py-24">
        <div className="mx-auto max-w-7xl px-6">
          <div className="mb-14">
            <SectionTitle kicker="Referanslar" title="Müşterilerimiz Ne Diyor" center />
          </div>
          <Testimonials items={referanslar} />
        </div>
      </section>
)}

      {sss.length > 0 && (
<section className="bg-paper py-24">
        <div className="mx-auto grid max-w-7xl gap-16 px-6 lg:grid-cols-[1fr_1.4fr]">
          <div>
            <SectionTitle kicker="Sık Sorulanlar" title="Merak Edilenler" />
            <p className="mt-6 text-neutral-600">Aklınıza takılan başka bir şey varsa bize doğrudan ulaşabilirsiniz.</p>
            <Link
              href="/iletisim"
              className="rounded-ui mt-8 inline-block bg-ink px-8 py-4 font-display text-lg font-bold uppercase tracking-wide text-white transition hover:bg-brand hover:text-ink"
            >
              Soru Sorun
            </Link>
          </div>
          <div className="divide-y divide-neutral-300 border-y border-neutral-300">
            {sss.map((item) => (
              <details key={item.soru} className="group py-5">
                <summary className="flex cursor-pointer items-center justify-between gap-4 font-display text-xl font-bold uppercase tracking-wide text-ink">
                  {item.soru}
                  <span className="faq-icon flex h-8 w-8 shrink-0 items-center justify-center bg-brand text-2xl leading-none text-ink transition">
                    +
                  </span>
                </summary>
                <p className="mt-3 max-w-xl text-sm leading-relaxed text-neutral-600">{item.cevap}</p>
              </details>
            ))}
          </div>
        </div>
      </section>
)}

      {referanslar.length > 0 && (
<section className="overflow-hidden border-y border-neutral-200 bg-white py-10">
        <div className="marquee-track flex w-max gap-16">
          {[0, 1, 2, 3].flatMap((tur) =>
            referanslar.map((r, i) => {
              const logo = referansLogosu(r);
              return logo ? (
                <Image key={`${tur}-${i}`} src={logo} alt={r.kurum} width={160} height={56} className="h-14 w-auto object-contain opacity-60 grayscale transition hover:opacity-100 hover:grayscale-0" />
              ) : (
                <span key={`${tur}-${i}`} className="font-display text-3xl font-bold uppercase tracking-widest text-neutral-300">
                  {r.kurum}
                </span>
              );
            }),
          )}
        </div>
      </section>
)}

      <section className="border-b border-white/10 bg-ink py-20">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-8 px-6">
          <div>
            <h2 className="font-display text-4xl font-extrabold uppercase leading-none text-white sm:text-5xl">
              Projeniz İçin Teklif Alın
            </h2>
            <p className="mt-3 text-white/60">Ekibimiz en kısa sürede sizinle iletişime geçsin.</p>
          </div>
          <div className="flex flex-wrap gap-4">
            <Link
              href="/iletisim"
              className="rounded-ui bg-brand px-8 py-4 font-display text-lg font-bold uppercase tracking-wide text-ink transition hover:bg-white"
            >
              İletişime Geçin
            </Link>
            <a
              href={`tel:${site.phone.replace(/\s/g, "")}`}
              className="rounded-ui border border-white/40 px-8 py-4 font-display text-lg font-bold uppercase tracking-wide text-white transition hover:border-brand hover:text-brand"
            >
              {site.phone}
            </a>
          </div>
        </div>
      </section>
    </>
  );
}
