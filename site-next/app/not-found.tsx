import Link from "next/link";

export default function NotFound() {
  return (
    <section className="flex min-h-[80svh] items-center bg-ink pt-32">
      <div className="mx-auto max-w-3xl px-6 text-center">
        <div className="font-display text-9xl font-extrabold leading-none text-brand">404</div>
        <h1 className="mt-4 font-display text-4xl font-bold uppercase text-white">Sayfa Bulunamadı</h1>
        <p className="mt-4 text-white/60">Aradığınız sayfa taşınmış veya kaldırılmış olabilir.</p>
        <Link href="/" className="rounded-ui mt-8 inline-block bg-brand px-8 py-4 font-display text-lg font-bold uppercase tracking-wide text-ink transition hover:bg-white">
          Ana Sayfaya Dön
        </Link>
      </div>
    </section>
  );
}
