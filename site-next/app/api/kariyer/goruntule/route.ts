import { createHash } from "node:crypto";
import { NextResponse, type NextRequest } from "next/server";
import { TOKEN_DESENI } from "@/lib/ilanlar";
import { personelUrl } from "@/lib/personel";

export const runtime = "nodejs";

const BOT_DESENI = /bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|curl|wget|headless/i;

function yanit(ok: boolean, durum: number) {
  return NextResponse.json({ ok }, { status: durum, headers: { "Cache-Control": "no-store" } });
}

function istemciIp(istek: NextRequest): string {
  const gercek = istek.headers.get("x-real-ip");
  if (gercek) return gercek.trim();
  const zincir = istek.headers.get("x-forwarded-for");
  if (zincir) return zincir.split(",")[0].trim();
  return "bilinmiyor";
}

export async function POST(istek: NextRequest) {
  const origin = istek.headers.get("origin");
  if (origin) {
    try {
      if (new URL(origin).host !== istek.headers.get("host")) return yanit(false, 403);
    } catch {
      return yanit(false, 403);
    }
  }

  const ua = istek.headers.get("user-agent") ?? "";
  if (!ua || BOT_DESENI.test(ua)) return yanit(true, 200);

  const govde = (await istek.json().catch(() => null)) as { token?: string } | null;
  const token = String(govde?.token ?? "").toLowerCase();
  if (!TOKEN_DESENI.test(token)) return yanit(false, 404);

  const taban = personelUrl();
  const anahtar = process.env.PERSONEL_API_KEY ?? "";
  if (!taban || !anahtar) return yanit(false, 503);

  const ziyaretci = createHash("sha256").update(`${istemciIp(istek)}|${ua}`).digest("hex");

  try {
    const sonuc = await fetch(`${taban}/api/external/kariyer-goruntule.php`, {
      method: "POST",
      headers: { "X-Api-Key": anahtar, "Content-Type": "application/x-www-form-urlencoded", Accept: "application/json" },
      body: new URLSearchParams({ token, ziyaretci }),
      cache: "no-store",
      signal: AbortSignal.timeout(5000),
    });
    return yanit(sonuc.ok, sonuc.ok ? 200 : 502);
  } catch (e) {
    console.error("kariyer görüntüleme: istek başarısız", e);
    return yanit(false, 502);
  }
}
