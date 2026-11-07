"use client";

import { useEffect, useState } from "react";

export type YorumOgesi = { kurum: string; kisi: string | null; unvan: string | null; yorum: string };

export default function Testimonials({ items }: { items: YorumOgesi[] }) {
  const [index, setIndex] = useState(0);

  useEffect(() => {
    if (items.length < 2) return;
    const timer = setInterval(() => setIndex((i) => (i + 1) % items.length), 7000);
    return () => clearInterval(timer);
  }, [items.length]);

  if (items.length === 0) return null;
  const current = items[index % items.length];
  const altSatir = [current.kisi, current.unvan].filter(Boolean).join(" · ");

  return (
    <div className="mx-auto max-w-3xl text-center">
      <svg viewBox="0 0 48 48" className="mx-auto h-12 w-12 text-brand" fill="currentColor" aria-hidden="true">
        <path d="M6 28c0-9 5-15 13-17l1 4c-5 2-7 5-7 9h6v13H6V28zm22 0c0-9 5-15 13-17l1 4c-5 2-7 5-7 9h6v13H28V28z" />
      </svg>
      <blockquote key={index} className="rise mt-6 text-2xl font-medium leading-relaxed text-ink sm:text-3xl">
        {current.yorum}
      </blockquote>
      <div className="mt-8">
        <div className="font-display text-xl font-bold uppercase tracking-wide text-ink">{current.kurum}</div>
        {altSatir && <div className="text-sm text-neutral-500">{altSatir}</div>}
      </div>
      {items.length > 1 && (
        <div className="mt-8 flex justify-center gap-3">
          {items.map((item, i) => (
            <button
              key={`${item.kurum}-${i}`}
              type="button"
              onClick={() => setIndex(i)}
              aria-label={`Yorum ${i + 1}`}
              aria-current={i === index}
              className={`h-1.5 transition-all ${i === index ? "w-12 bg-brand" : "w-6 bg-neutral-300"}`}
            />
          ))}
        </div>
      )}
    </div>
  );
}
