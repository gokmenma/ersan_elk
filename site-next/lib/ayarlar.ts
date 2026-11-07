import { about as aboutVarsayilan, site as siteVarsayilan, slides as slaytVarsayilan, steps as adimVarsayilan } from "@/lib/content";
import { icerikGetir } from "@/lib/icerik";

export type SiteBilgi = {
  name: string;
  phone: string;
  email: string;
  address: string;
  hours: string;
  social: { ad: string; href: string }[];
};

export type Hakkimizda = { title: string; text: string; checks: string[]; mission: string; vision: string };
export type Adim = { no: string; title: string; text: string };
export type Slayt = { image: string; kicker: string; title: string; text: string };

export type SeoBilgi = { title: string; description: string };

export type SiteAyarlari = { site: SiteBilgi; about: Hakkimizda; steps: Adim[]; slides: Slayt[]; seo: SeoBilgi };

function satirlar(metin: string | undefined): string[] {
  return (metin ?? "")
    .split(/\r?\n/)
    .map((s) => s.trim())
    .filter(Boolean);
}

function sosyalBaglantilar(a: Record<string, string>): { ad: string; href: string }[] {
  const liste: { ad: string; href: string }[] = [];
  const tanim: [string, string][] = [
    ["sosyal_facebook", "Facebook"],
    ["sosyal_instagram", "Instagram"],
    ["sosyal_linkedin", "LinkedIn"],
    ["sosyal_youtube", "YouTube"],
  ];
  for (const [anahtar, ad] of tanim) {
    const href = a[anahtar];
    if (href && /^https:\/\//.test(href)) liste.push({ ad, href });
  }
  const wa = (a.sosyal_whatsapp ?? "").replace(/\D/g, "");
  if (wa.length >= 10) liste.push({ ad: "WhatsApp", href: `https://wa.me/${wa}` });
  return liste;
}

export async function siteAyarlari(): Promise<SiteAyarlari> {
  const { ayarlar } = await icerikGetir();
  const a = ayarlar;

  const adimlar: Adim[] = satirlar(a.surec_adimlari)
    .map((satir) => {
      const [baslik, ...kalan] = satir.split("|");
      return { title: (baslik ?? "").trim(), text: kalan.join("|").trim() };
    })
    .filter((x) => x.title !== "")
    .map((x, i) => ({ no: String(i + 1).padStart(2, "0"), ...x }));

  const maddeler = satirlar(a.hakkimizda_maddeler);

  return {
    site: {
      name: a.site_adi || siteVarsayilan.name,
      phone: a.telefon || siteVarsayilan.phone,
      email: a.eposta || siteVarsayilan.email,
      address: a.adres || siteVarsayilan.address,
      hours: a.calisma_saatleri || siteVarsayilan.hours,
      social: sosyalBaglantilar(a),
    },
    about: {
      title: a.hakkimizda_baslik || aboutVarsayilan.title,
      text: a.hakkimizda_metin || aboutVarsayilan.text,
      checks: maddeler.length > 0 ? maddeler : aboutVarsayilan.checks,
      mission: a.misyon || aboutVarsayilan.mission,
      vision: a.vizyon || aboutVarsayilan.vision,
    },
    steps: adimlar.length > 0 ? adimlar : adimVarsayilan,
    seo: {
      title: a.seo_baslik || `${a.site_adi || siteVarsayilan.name} | Sayaç Okuma, Kesme-Açma ve Saha Hizmetleri`,
      description:
        a.seo_aciklama ||
        "Elektrik, su ve doğalgaz sayaç okuma, enerji kesme/açma ve sayaç değişimi alanlarında profesyonel saha hizmetleri.",
    },
    slides: slaytVarsayilan.map((v, i) => ({
      image: v.image,
      kicker: a[`slayt${i + 1}_kicker`] || v.kicker,
      title: a[`slayt${i + 1}_baslik`] || v.title,
      text: a[`slayt${i + 1}_metin`] || v.text,
    })),
  };
}
