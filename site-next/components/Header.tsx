"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import type { SiteBilgi } from "@/lib/ayarlar";
import { nav } from "@/lib/content";

function aktifMi(pathname: string, href: string): boolean {
  if (href === "/") return pathname === "/";
  return pathname === href || pathname.startsWith(`${href}/`);
}

export default function Header({ site }: { site: SiteBilgi }) {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 40);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  return (
    <header className="fixed inset-x-0 top-0 z-50">
      <div className="hidden bg-ink text-xs text-white/70 lg:block">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-2">
          <div className="flex items-center gap-6">
            <span>{site.address}</span>
            <span>{site.hours}</span>
          </div>
          <div className="flex items-center gap-6">
            <a href={`tel:${site.phone.replace(/\s/g, "")}`} className="hover:text-brand">
              {site.phone}
            </a>
            <a href={`mailto:${site.email}`} className="hover:text-brand">
              {site.email}
            </a>
          </div>
        </div>
      </div>

      <div
        className={`transition-all duration-300 ${
          scrolled ? "bg-ink/95 shadow-lg backdrop-blur" : "bg-ink/40 backdrop-blur-sm"
        }`}
      >
        <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-6">
          <Link href="/" className="flex items-center gap-3">
            <Image src="/images/logo.png" alt={site.name} width={217} height={50} className="h-11 w-auto rounded-md bg-white px-3 py-1.5" priority />
          </Link>

          <nav className="hidden items-center gap-8 lg:flex">
            {nav.map((item) => {
              const aktif = aktifMi(pathname, item.href);
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  aria-current={aktif ? "page" : undefined}
                  className={`relative py-1 font-display text-[17px] font-semibold uppercase tracking-wide transition hover:text-brand after:absolute after:inset-x-0 after:-bottom-1 after:h-0.5 after:origin-left after:bg-brand after:transition-transform after:duration-300 ${
                    aktif ? "text-brand after:scale-x-100" : "text-white/90 after:scale-x-0 hover:after:scale-x-100"
                  }`}
                >
                  {item.label}
                </Link>
              );
            })}
          </nav>

          <div className="flex items-center gap-3">
            <Link
              href="/iletisim"
              className="rounded-ui hidden bg-brand px-6 py-3 font-display text-base font-bold uppercase tracking-wide text-ink transition hover:bg-white sm:block"
            >
              Teklif Al
            </Link>
            <button
              type="button"
              aria-label="Menüyü aç/kapat"
              aria-expanded={open}
              onClick={() => setOpen((v) => !v)}
              className="flex h-11 w-11 flex-col items-center justify-center gap-1.5 border border-white/30 lg:hidden"
            >
              <span className={`h-0.5 w-5 bg-white transition ${open ? "translate-y-2 rotate-45" : ""}`} />
              <span className={`h-0.5 w-5 bg-white transition ${open ? "opacity-0" : ""}`} />
              <span className={`h-0.5 w-5 bg-white transition ${open ? "-translate-y-2 -rotate-45" : ""}`} />
            </button>
          </div>
        </div>

        {open && (
          <nav className="border-t border-white/10 bg-ink lg:hidden">
            <div className="mx-auto flex max-w-7xl flex-col px-6 py-3">
              {nav.map((item) => {
                const aktif = aktifMi(pathname, item.href);
                return (
                  <Link
                    key={item.href}
                    href={item.href}
                    onClick={() => setOpen(false)}
                    aria-current={aktif ? "page" : undefined}
                    className={`border-b border-white/5 py-3 font-display text-lg font-semibold uppercase tracking-wide ${
                      aktif ? "border-l-4 border-l-brand pl-3 text-brand" : "text-white/90"
                    }`}
                  >
                    {item.label}
                  </Link>
                );
              })}
            </div>
          </nav>
        )}
      </div>
    </header>
  );
}
