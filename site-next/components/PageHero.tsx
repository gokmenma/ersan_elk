import Link from "next/link";

export default function PageHero({ title, kicker, text }: { title: string; kicker: string; text?: string }) {
  return (
    <section className="relative overflow-hidden bg-ink pb-10 pt-32 lg:pt-36">
      <div className="absolute inset-0 bg-gradient-to-r from-brand/10 via-transparent to-transparent" />
      <div className="absolute inset-x-0 bottom-0 h-px bg-brand/30" />
      <div className="relative mx-auto max-w-7xl px-6">
        <nav aria-label="Konum" className="flex items-center gap-2 text-xs text-white/50">
          <Link href="/" className="transition hover:text-brand">
            Ana Sayfa
          </Link>
          <span>/</span>
          <span className="text-brand">{kicker}</span>
        </nav>
        <h1 className="rise mt-3 font-display text-3xl font-extrabold uppercase leading-tight text-white sm:text-4xl">
          {title}
        </h1>
        {text && <p className="rise rise-2 mt-2 line-clamp-2 max-w-2xl text-sm text-white/60">{text}</p>}
      </div>
    </section>
  );
}
