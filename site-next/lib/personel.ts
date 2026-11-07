export function personelUrl(): string {
  return (process.env.PERSONEL_URL ?? "").replace(/\/+$/, "");
}

export async function siteApiGetir<T>(yol: string): Promise<T | null> {
  const taban = personelUrl();
  const anahtar = process.env.SITE_API_KEY ?? "";
  if (!taban || !anahtar) {
    console.error("site api: PERSONEL_URL veya SITE_API_KEY tanımlı değil");
    return null;
  }

  try {
    const yanit = await fetch(`${taban}/api/external/${yol}`, {
      headers: { "X-Api-Key": anahtar, Accept: "application/json" },
      next: { revalidate: 60 },
      signal: AbortSignal.timeout(5000),
    });
    if (!yanit.ok) {
      console.error(`site api: ${yol} ${yanit.status} döndü`);
      return null;
    }
    const veri = (await yanit.json()) as { ok?: boolean } & T;
    return veri.ok === true ? veri : null;
  } catch (e) {
    console.error(`site api: ${yol} isteği başarısız`, e);
    return null;
  }
}

export function personelDosyaUrl(yol: string | null | undefined): string | null {
  if (!yol || !/^uploads\/site\/[a-zA-Z0-9_\-/]+\.(jpg|jpeg|png)$/.test(yol)) return null;
  const taban = personelUrl();
  return taban ? `${taban}/${yol}` : null;
}
