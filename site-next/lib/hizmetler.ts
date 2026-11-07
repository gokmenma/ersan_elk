import { personelDosyaUrl, siteApiGetir } from "@/lib/personel";

export type Hizmet = {
  slug: string;
  baslik: string;
  kisa_aciklama: string | null;
  aciklama: string | null;
  ozellikler: string[];
  adimlar: string[];
  gorsel: string | null;
  sira: number;
};

export type HizmetSonuc = { hizmetler: Hizmet[]; hata: boolean };

export async function hizmetleriGetir(): Promise<HizmetSonuc> {
  const veri = await siteApiGetir<{ hizmetler: Hizmet[] }>("site-hizmetler.php");
  if (!veri || !Array.isArray(veri.hizmetler)) return { hizmetler: [], hata: true };
  return { hizmetler: veri.hizmetler, hata: false };
}

export async function hizmetGetir(slug: string): Promise<Hizmet | null> {
  const { hizmetler } = await hizmetleriGetir();
  return hizmetler.find((h) => h.slug === slug) ?? null;
}

export function hizmetGorseli(hizmet: Hizmet): string | null {
  return personelDosyaUrl(hizmet.gorsel);
}
