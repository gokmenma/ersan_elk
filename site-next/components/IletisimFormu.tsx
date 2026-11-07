"use client";

import { useActionState, useEffect, useState } from "react";
import { mesajGonder, type FormDurum } from "@/app/iletisim/actions";

const girdi =
  "w-full rounded-ui border border-neutral-300 bg-white px-4 py-3 text-base text-ink outline-none transition focus:border-brand-deep focus:ring-2 focus:ring-brand/30";

function Alan({ etiket, hata, children }: { etiket: string; hata?: string; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-sm font-semibold text-ink">{etiket}</span>
      {children}
      {hata && (
        <span role="alert" className="mt-1 block text-sm text-red-600">
          {hata}
        </span>
      )}
    </label>
  );
}

export default function IletisimFormu() {
  const [durum, eylem, bekliyor] = useActionState<FormDurum, FormData>(mesajGonder, null);
  const [t, setT] = useState("0");

  useEffect(() => {
    setT(String(Date.now()));
  }, []);

  if (durum?.ok) {
    return (
      <div role="status" className="rounded-ui border-t-4 border-brand bg-white p-10 text-center shadow-sm">
        <div className="mx-auto flex h-14 w-14 items-center justify-center bg-brand text-ink">
          <svg viewBox="0 0 20 20" className="h-7 w-7" fill="currentColor" aria-hidden="true">
            <path d="M7.7 14.3 3.4 10l1.4-1.4 2.9 2.9 7.5-7.5L16.6 5z" />
          </svg>
        </div>
        <h2 className="mt-5 font-display text-3xl font-bold uppercase text-ink">Teşekkürler</h2>
        <p className="mt-3 text-neutral-600">{durum.mesaj}</p>
      </div>
    );
  }

  const hatalar = durum?.hatalar ?? {};
  const v = durum?.degerler ?? {};

  return (
    <form action={eylem} className="space-y-5 rounded-ui border-t-4 border-brand bg-white p-8 shadow-sm" noValidate>
      <h2 className="font-display text-3xl font-bold uppercase text-ink">Bize Yazın</h2>

      {durum && !durum.ok && (
        <div role="alert" className="rounded-ui border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {durum.mesaj}
        </div>
      )}

      <input type="hidden" name="t" value={t} />
      <div aria-hidden="true" className="absolute left-[-9999px] h-0 w-0 overflow-hidden">
        <label>
          Web siteniz
          <input type="text" name="web_sitesi" tabIndex={-1} autoComplete="off" />
        </label>
      </div>

      <div className="grid gap-5 sm:grid-cols-2">
        <Alan etiket="Ad Soyad *" hata={hatalar.ad_soyad}>
          <input name="ad_soyad" type="text" autoComplete="name" maxLength={150} defaultValue={v.ad_soyad} className={girdi} required />
        </Alan>
        <Alan etiket="Telefon" hata={hatalar.telefon}>
          <input name="telefon" type="tel" autoComplete="tel" maxLength={30} defaultValue={v.telefon} className={girdi} />
        </Alan>
        <Alan etiket="E-posta" hata={hatalar.email}>
          <input name="email" type="email" autoComplete="email" maxLength={190} defaultValue={v.email} className={girdi} />
        </Alan>
        <Alan etiket="Konu">
          <input name="konu" type="text" maxLength={200} defaultValue={v.konu} className={girdi} />
        </Alan>
      </div>

      <Alan etiket="Mesajınız *" hata={hatalar.mesaj}>
        <textarea name="mesaj" rows={6} maxLength={3000} defaultValue={v.mesaj} className={girdi} required />
      </Alan>

      <div>
        <label className="flex items-start gap-3 text-sm text-neutral-600">
          <input type="checkbox" name="kvkk" className="mt-1 h-4 w-4 accent-[var(--brand)]" />
          <span>
            Kişisel verilerimin, talebimin değerlendirilmesi amacıyla 6698 sayılı KVKK kapsamında işlenmesini kabul ediyorum. *
          </span>
        </label>
        {hatalar.kvkk && (
          <span role="alert" className="mt-1 block text-sm text-red-600">
            {hatalar.kvkk}
          </span>
        )}
      </div>

      <button
        type="submit"
        disabled={bekliyor}
        className="rounded-ui bg-ink px-10 py-4 font-display text-lg font-bold uppercase tracking-wide text-white transition hover:bg-brand hover:text-ink disabled:cursor-not-allowed disabled:opacity-60"
      >
        {bekliyor ? "Gönderiliyor..." : "Mesajı Gönder"}
      </button>
      <p className="text-xs text-neutral-500">* ile işaretli alanlar zorunludur. Telefon veya e-postadan en az birini yazın.</p>
    </form>
  );
}
