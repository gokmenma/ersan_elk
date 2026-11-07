import { personelDosyaUrl, siteApiGetir } from "@/lib/personel";

export type Proje = {
  slug: string;
  baslik: string;
  kategori: string | null;
  musteri: string | null;
  konum: string | null;
  proje_yili: number | null;
  kisa_aciklama: string | null;
  aciklama: string | null;
  gorsel: string | null;
  sira: number;
};

export type ProjeSonuc = { projeler: Proje[]; hata: boolean };

export async function projeleriGetir(): Promise<ProjeSonuc> {
  const veri = await siteApiGetir<{ projeler: Proje[] }>("site-projeler.php");
  if (!veri || !Array.isArray(veri.projeler)) return { projeler: [], hata: true };
  return { projeler: veri.projeler, hata: false };
}

export async function projeGetir(slug: string): Promise<Proje | null> {
  const { projeler } = await projeleriGetir();
  return projeler.find((p) => p.slug === slug) ?? null;
}

export function projeGorseli(proje: Proje): string | null {
  return personelDosyaUrl(proje.gorsel);
}
