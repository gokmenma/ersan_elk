"use client";

import Image from "next/image";
import Link from "next/link";
import { useMemo, useState } from "react";

export type ProjeKarti = {
  slug: string;
  baslik: string;
  kategori: string | null;
  konum: string | null;
  kisa_aciklama: string | null;
  gorsel: string | null;
};

export default function ProjeListesi({ projeler }: { projeler: ProjeKarti[] }) {
  const [secili, setSecili] = useState("Tümü");
  const kategoriler = useMemo(
    () => ["Tümü", ...Array.from(new Set(projeler.map((p) => p.kategori).filter((k): k is string => !!k)))],
    [projeler],
  );
  const gorunen = secili === "Tümü" ? projeler : projeler.filter((p) => p.kategori === secili);

  return (
    <>
      {kategoriler.length > 2 && (
        <div className="mb-10 flex flex-wrap gap-3" role="tablist" aria-label="Proje kategorileri">
          {kategoriler.map((k) => (
            <button
              key={k}
              type="button"
              role="tab"
              aria-selected={secili === k}
              onClick={() => setSecili(k)}
              className={`rounded-ui px-5 py-2.5 font-display text-base font-bold uppercase tracking-wide transition ${
                secili === k ? "bg-ink text-white" : "bg-white text-ink hover:bg-brand"
              }`}
            >
              {k}
            </button>
          ))}
        </div>
      )}

      <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
        {gorunen.map((proje) => (
          <Link key={proje.slug} href={`/projeler/${proje.slug}`} className="group block overflow-hidden rounded-ui bg-white shadow-sm transition hover:shadow-xl">
            <div className="relative aspect-[4/3] overflow-hidden rounded-ui bg-ink-soft">
              {proje.gorsel && (
                <Image
                  src={proje.gorsel}
                  alt={proje.baslik}
                  fill
                  sizes="(min-width:1024px) 33vw, (min-width:640px) 50vw, 100vw"
                  className="object-cover transition duration-700 group-hover:scale-105"
                />
              )}
              {proje.kategori && (
                <span className="absolute left-3 top-3 rounded-ui bg-brand px-4 py-2 font-display text-sm font-bold uppercase tracking-wide text-ink">
                  {proje.kategori}
                </span>
              )}
            </div>
            <div className="p-6">
              <h2 className="font-display text-2xl font-bold uppercase leading-tight text-ink transition group-hover:text-brand-deep">
                {proje.baslik}
              </h2>
              {proje.konum && <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-neutral-400">{proje.konum}</p>}
              {proje.kisa_aciklama && <p className="mt-2 text-sm leading-relaxed text-neutral-600">{proje.kisa_aciklama}</p>}
            </div>
          </Link>
        ))}
      </div>
    </>
  );
}
