import { NextResponse, type NextRequest } from "next/server";
import { TOKEN_DESENI } from "@/lib/ilanlar";
import { personelUrl } from "@/lib/personel";

export const runtime = "nodejs";

const MAKS_DOSYA = 8 * 1024 * 1024;
const MAKS_GOVDE = 20 * 1024 * 1024;
const PENCERE_MS = 10 * 60 * 1000;
const PENCERE_LIMIT = 6;
const MIN_FORM_SURESI_MS = 4000;

const denemeler = new Map<string, number[]>();

function yanit(ok: boolean, mesaj: string, durum: number) {
  return NextResponse.json({ ok, mesaj }, { status: durum, headers: { "Cache-Control": "no-store" } });
}

function istemciIp(istek: NextRequest): string {
  const gercek = istek.headers.get("x-real-ip");
  if (gercek) return gercek.trim();
  const zincir = istek.headers.get("x-forwarded-for");
  if (zincir) return zincir.split(",")[0].trim();
  return "bilinmiyor";
}

function sinirAsildi(ip: string): boolean {
  const simdi = Date.now();
  const kayitlar = (denemeler.get(ip) ?? []).filter((t) => simdi - t < PENCERE_MS);
  kayitlar.push(simdi);
  denemeler.set(ip, kayitlar);
  if (denemeler.size > 5000) {
    for (const [anahtar, zamanlar] of denemeler) {
      if (zamanlar.every((t) => simdi - t >= PENCERE_MS)) denemeler.delete(anahtar);
    }
  }
  return kayitlar.length > PENCERE_LIMIT;
}

export async function POST(istek: NextRequest) {
  const origin = istek.headers.get("origin");
  if (origin) {
    try {
      if (new URL(origin).host !== istek.headers.get("host")) return yanit(false, "Geçersiz istek.", 403);
    } catch {
      return yanit(false, "Geçersiz istek.", 403);
    }
  }

  const uzunluk = Number(istek.headers.get("content-length") ?? 0);
  if (uzunluk > MAKS_GOVDE) return yanit(false, "Gönderilen dosyalar çok büyük.", 413);

  const ip = istemciIp(istek);
  if (sinirAsildi(ip)) return yanit(false, "Çok fazla deneme yapıldı. Lütfen bir süre sonra tekrar deneyin.", 429);

  let form: FormData;
  try {
    form = await istek.formData();
  } catch {
    return yanit(false, "Form okunamadı.", 400);
  }

  if (String(form.get("website") ?? "").trim() !== "") return yanit(true, "Başvurunuz alındı.", 200);

  const acilis = Number(form.get("acilis") ?? 0);
  if (!Number.isFinite(acilis) || Date.now() - acilis < MIN_FORM_SURESI_MS) {
    return yanit(false, "Lütfen formu doldurduktan sonra tekrar gönderin.", 429);
  }
  form.delete("acilis");

  const token = String(form.get("i") ?? "").toLowerCase();
  if (!TOKEN_DESENI.test(token)) return yanit(false, "Başvuru bağlantısı geçersiz veya kapatılmış.", 404);

  for (const alan of ["cv", "foto"]) {
    const dosya = form.get(alan);
    if (dosya instanceof File) {
      if (dosya.size === 0) form.delete(alan);
      else if (dosya.size > MAKS_DOSYA) return yanit(false, "Dosya en fazla 8 MB olabilir.", 413);
    }
  }

  const taban = personelUrl();
  const anahtar = process.env.PERSONEL_API_KEY ?? "";
  if (!taban || !anahtar) {
    console.error("kariyer başvuru: PERSONEL_URL veya PERSONEL_API_KEY tanımlı değil");
    return yanit(false, "Başvuru şu anda alınamıyor. Lütfen daha sonra tekrar deneyin.", 503);
  }

  try {
    const sonuc = await fetch(`${taban}/api/external/kariyer-basvuru.php`, {
      method: "POST",
      headers: { "X-Api-Key": anahtar, "X-Client-Ip": ip, Accept: "application/json" },
      body: form,
      cache: "no-store",
      signal: AbortSignal.timeout(30000),
    });
    const veri = (await sonuc.json().catch(() => null)) as { ok?: boolean; mesaj?: string } | null;
    if (!veri || typeof veri.mesaj !== "string") {
      console.error(`kariyer başvuru: beklenmeyen yanıt ${sonuc.status}`);
      return yanit(false, "Başvuru şu anda alınamıyor. Lütfen daha sonra tekrar deneyin.", 502);
    }
    if (sonuc.status === 401 || sonuc.status === 503) {
      console.error(`kariyer başvuru: servis ${sonuc.status} döndü`);
      return yanit(false, "Başvuru şu anda alınamıyor. Lütfen daha sonra tekrar deneyin.", 502);
    }
    return yanit(veri.ok === true, veri.mesaj, sonuc.status);
  } catch (e) {
    console.error("kariyer başvuru: istek başarısız", e);
    return yanit(false, "Başvuru şu anda alınamıyor. Lütfen daha sonra tekrar deneyin.", 502);
  }
}
