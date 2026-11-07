import Image from "next/image";
import Link from "next/link";
import { siteAyarlari } from "@/lib/ayarlar";
import { nav } from "@/lib/content";
import { hizmetleriGetir } from "@/lib/hizmetler";

export default async function Footer() {
  const [{ hizmetler }, { site }] = await Promise.all([hizmetleriGetir(), siteAyarlari()]);
  return (
    <footer className="bg-ink text-white/70">
      <div className="mx-auto grid max-w-7xl gap-12 px-6 py-20 md:grid-cols-2 lg:grid-cols-4">
        <div>
          <Image src="/images/logo.png" alt={site.name} width={217} height={50} className="h-11 w-auto rounded-md bg-white px-3 py-1.5" />
          <p className="mt-5 text-sm leading-relaxed">
            Elektrik, su ve doğalgaz dağıtım şirketleri için profesyonel saha hizmetleri.
          </p>
        </div>

        <div>
          <h3 className="font-display text-xl font-bold uppercase tracking-wide text-white">Hızlı Bağlantılar</h3>
          <ul className="mt-5 space-y-3 text-sm">
            {nav.map((item) => (
              <li key={item.href}>
                <Link href={item.href} className="transition hover:text-brand">
                  {item.label}
                </Link>
              </li>
            ))}
          </ul>
        </div>

        <div>
          <h3 className="font-display text-xl font-bold uppercase tracking-wide text-white">Hizmetlerimiz</h3>
          <ul className="mt-5 space-y-3 text-sm">
            {hizmetler.map((item) => (
              <li key={item.slug}>
                <Link href={`/hizmetlerimiz/${item.slug}`} className="transition hover:text-brand">
                  {item.baslik}
                </Link>
              </li>
            ))}
          </ul>
        </div>

        <div>
          <h3 className="font-display text-xl font-bold uppercase tracking-wide text-white">İletişim</h3>
          <ul className="mt-5 space-y-3 text-sm">
            <li>{site.address}</li>
            <li>
              <a href={`tel:${site.phone.replace(/\s/g, "")}`} className="hover:text-brand">
                {site.phone}
              </a>
            </li>
            <li>
              <a href={`mailto:${site.email}`} className="hover:text-brand">
                {site.email}
              </a>
            </li>
            <li>{site.hours}</li>
          </ul>
          {site.social.length > 0 && (
            <ul className="mt-5 flex flex-wrap gap-3 text-sm">
              {site.social.map((s) => (
                <li key={s.ad}>
                  <a href={s.href} target="_blank" rel="noopener noreferrer" className="border border-white/20 px-3 py-1.5 transition hover:border-brand hover:text-brand">
                    {s.ad}
                  </a>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
      <div className="border-t border-white/10 py-6 text-center text-xs">
        © {new Date().getFullYear()} {site.name} Tüm hakları saklıdır.
      </div>
    </footer>
  );
}
