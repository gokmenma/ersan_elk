import { personelDosyaUrl, siteApiGetir } from "@/lib/personel";

export type Referans = {
  kurum: string;
  kisi: string | null;
  unvan: string | null;
  yorum: string;
  logo: string | null;
};

export type Soru = { soru: string; cevap: string };

export type Istatistik = { etiket: string; deger: number; sonek: string };

export type Icerik = { referanslar: Referans[]; sss: Soru[]; istatistikler: Istatistik[]; ayarlar: Record<string, string> };

export async function icerikGetir(): Promise<Icerik> {
  const veri = await siteApiGetir<Icerik>("site-icerik.php");
  return {
    referanslar: Array.isArray(veri?.referanslar) ? veri.referanslar : [],
    sss: Array.isArray(veri?.sss) ? veri.sss : [],
    istatistikler: Array.isArray(veri?.istatistikler) ? veri.istatistikler : [],
    ayarlar: veri?.ayarlar && typeof veri.ayarlar === "object" && !Array.isArray(veri.ayarlar) ? veri.ayarlar : {},
  };
}

export function referansLogosu(referans: Referans): string | null {
  return personelDosyaUrl(referans.logo);
}
