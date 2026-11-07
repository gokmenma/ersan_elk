"use server";

import { headers } from "next/headers";
import { personelUrl } from "@/lib/personel";

export type FormDurum = {
  ok: boolean;
  mesaj: string;
  hatalar?: Record<string, string>;
  degerler?: Record<string, string>;
} | null;

const ALANLAR = ["ad_soyad", "telefon", "email", "konu", "mesaj"] as const;

async function istemciIp(): Promise<string> {
  const h = await headers();
  const ilk = (h.get("x-forwarded-for") ?? "").split(",")[0]?.trim();
  return ilk || h.get("x-real-ip") || "";
}

export async function mesajGonder(_onceki: FormDurum, form: FormData): Promise<FormDurum> {
  const degerler: Record<string, string> = {};
  for (const alan of ALANLAR) degerler[alan] = String(form.get(alan) ?? "").trim();

  if (String(form.get("web_sitesi") ?? "") !== "") {
    return { ok: true, mesaj: "Mesajınız bize ulaştı. En kısa sürede sizinle iletişime geçeceğiz." };
  }

  const baslangic = Number(form.get("t") ?? 0);
  const gecen = Date.now() - baslangic;
  if (!baslangic || gecen < 3000 || gecen > 6 * 60 * 60 * 1000) {
    return { ok: false, mesaj: "Lütfen formu kontrol edip tekrar gönderin.", degerler };
  }

  const taban = personelUrl();
  const anahtar = process.env.SITE_API_KEY ?? "";
  if (!taban || !anahtar) {
    console.error("iletisim: PERSONEL_URL veya SITE_API_KEY tanımlı değil");
    return { ok: false, mesaj: "Mesajınız şu anda gönderilemedi. Lütfen bizi telefonla arayın.", degerler };
  }

  try {
    const yanit = await fetch(`${taban}/api/external/site-iletisim.php`, {
      method: "POST",
      headers: { "X-Api-Key": anahtar, "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ ...degerler, kvkk: form.get("kvkk") === "on", ip: await istemciIp() }),
      cache: "no-store",
      signal: AbortSignal.timeout(8000),
    });
    const veri = (await yanit.json().catch(() => null)) as { ok?: boolean; mesaj?: string; hatalar?: Record<string, string> } | null;

    if (yanit.ok && veri?.ok === true) {
      return { ok: true, mesaj: veri.mesaj ?? "Mesajınız bize ulaştı." };
    }
    if (yanit.status === 422 && veri?.hatalar) {
      return { ok: false, mesaj: veri.mesaj ?? "Lütfen işaretli alanları kontrol edin.", hatalar: veri.hatalar, degerler };
    }
    if (yanit.status === 429) {
      return { ok: false, mesaj: veri?.mesaj ?? "Çok fazla deneme yaptınız. Lütfen daha sonra tekrar deneyin.", degerler };
    }
    console.error(`iletisim: API ${yanit.status} döndü`);
  } catch (e) {
    console.error("iletisim: istek başarısız", e);
  }
  return { ok: false, mesaj: "Mesajınız şu anda gönderilemedi. Lütfen daha sonra tekrar deneyin veya bizi arayın.", degerler };
}
