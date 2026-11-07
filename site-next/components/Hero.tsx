"use client";

import Image from "next/image";
import Link from "next/link";
import { useEffect, useState } from "react";
import type { Slayt } from "@/lib/ayarlar";

export default function Hero({ slides }: { slides: Slayt[] }) {
  const [index, setIndex] = useState(0);

  useEffect(() => {
    const adet = slides.length;
    const timer = setInterval(() => setIndex((i) => (i + 1) % adet), 6500);
    return () => clearInterval(timer);
  }, [slides.length]);

  return (
    <section className="relative min-h-[640px] overflow-hidden bg-ink pt-20 lg:h-[min(100svh,860px)] lg:pt-28">
      {slides.map((slide, i) => (
        <div
          key={slide.title}
          aria-hidden={i !== index}
          className={`absolute inset-0 transition-opacity duration-1000 ${i === index ? "opacity-100" : "opacity-0"}`}
        >
          <Image
            src={slide.image}
            alt=""
            fill
            sizes="100vw"
            priority={i === 0}
            className={`object-cover object-center ${i === index ? "kenburns" : ""}`}
          />
          <div className="absolute inset-0 bg-gradient-to-r from-ink via-ink/80 to-ink/20" />
        </div>
      ))}

      <div className="relative mx-auto flex h-full max-w-7xl items-center px-6 py-24">
        <div key={index} className="max-w-2xl">
          <span className="rise inline-block border-l-4 border-brand pl-3 font-display text-lg font-semibold uppercase tracking-[0.2em] text-brand">
            {slides[index].kicker}
          </span>
          <h1 className="rise rise-2 mt-5 font-display text-5xl font-extrabold uppercase leading-[0.95] text-white sm:text-6xl lg:text-7xl">
            {slides[index].title}
          </h1>
          <p className="rise rise-3 mt-6 max-w-xl text-lg leading-relaxed text-white/75">{slides[index].text}</p>
          <div className="rise rise-3 mt-9 flex flex-wrap gap-4">
            <Link
              href="/hizmetlerimiz"
              className="rounded-ui bg-brand px-8 py-4 font-display text-lg font-bold uppercase tracking-wide text-ink transition hover:bg-white"
            >
              Hizmetlerimiz
            </Link>
            <Link
              href="/iletisim"
              className="rounded-ui border border-white/40 px-8 py-4 font-display text-lg font-bold uppercase tracking-wide text-white transition hover:border-brand hover:text-brand"
            >
              İletişime Geçin
            </Link>
          </div>
        </div>
      </div>

      <div className="absolute bottom-8 left-0 right-0">
        <div className="mx-auto flex max-w-7xl items-center gap-3 px-6">
          {slides.map((slide, i) => (
            <button
              key={slide.title}
              type="button"
              onClick={() => setIndex(i)}
              aria-label={`Slayt ${i + 1}`}
              aria-current={i === index}
              className="group flex items-center gap-3"
            >
              <span
                className={`font-display text-xl font-bold transition ${i === index ? "text-brand" : "text-white/40 group-hover:text-white"}`}
              >
                0{i + 1}
              </span>
              <span className={`h-0.5 transition-all ${i === index ? "w-16 bg-brand" : "w-8 bg-white/30"}`} />
            </button>
          ))}
        </div>
      </div>
    </section>
  );
}
