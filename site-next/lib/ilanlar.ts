export type Ilan = {
  baslik: string;
  departman: string | null;
  gorev: string | null;
  calisma_yeri: string | null;
  aciklama: string | null;
  aranan_nitelikler: string | null;
  token: string;
  yayin_tarihi: string;
};

export type IlanSonuc = { ilanlar: Ilan[]; hata: boolean };

function baseUrl(): string {
  return (process.env.PERSONEL_URL ?? "").replace(/\/+$/, "");
}

export type IlanDetay = Ilan & {
  gecerlilik_tarihi: string | null;
  firma_adi: string;
  sorular: string[];
};

export const TOKEN_DESENI = /^[a-f0-9]{48}$/;

export function basvuruUrl(token: string): string {
  return `/kariyer/${encodeURIComponent(token)}`;
}

export async function ilanGetir(token: string): Promise<IlanDetay | null> {
  const url = baseUrl();
  const anahtar = process.env.PERSONEL_API_KEY ?? "";
  if (!url || !anahtar || !TOKEN_DESENI.test(token)) return null;

  try {
    const yanit = await fetch(`${url}/api/external/kariyer-ilanlar.php?token=${token}`, {
      headers: { "X-Api-Key": anahtar, Accept: "application/json" },
      next: { revalidate: 60 },
      signal: AbortSignal.timeout(5000),
    });
    if (!yanit.ok) return null;
    const veri = (await yanit.json()) as { ok?: boolean; ilan?: IlanDetay };
    return veri.ok === true && veri.ilan ? veri.ilan : null;
  } catch (e) {
    console.error("kariyer: ilan isteği başarısız", e);
    return null;
  }
}

export async function ilanlariGetir(): Promise<IlanSonuc> {
  const url = baseUrl();
  const anahtar = process.env.PERSONEL_API_KEY ?? "";
  if (!url || !anahtar) {
    console.error("kariyer: PERSONEL_URL veya PERSONEL_API_KEY tanımlı değil");
    return { ilanlar: [], hata: true };
  }

  try {
    const yanit = await fetch(`${url}/api/external/kariyer-ilanlar.php`, {
      headers: { "X-Api-Key": anahtar, Accept: "application/json" },
      next: { revalidate: 60 },
      signal: AbortSignal.timeout(5000),
    });
    if (!yanit.ok) {
      console.error(`kariyer: API ${yanit.status} döndü`);
      return { ilanlar: [], hata: true };
    }
    const veri = (await yanit.json()) as { ok?: boolean; ilanlar?: Ilan[] };
    if (veri.ok !== true || !Array.isArray(veri.ilanlar)) {
      return { ilanlar: [], hata: true };
    }
    const gecerli = veri.ilanlar.filter((i) => TOKEN_DESENI.test(i.token));
    return { ilanlar: gecerli, hata: false };
  } catch (e) {
    console.error("kariyer: istek başarısız", e);
    return { ilanlar: [], hata: true };
  }
}
