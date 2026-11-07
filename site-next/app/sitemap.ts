import type { MetadataRoute } from "next";
import { hizmetleriGetir } from "@/lib/hizmetler";
import { projeleriGetir } from "@/lib/projeler";
import { siteUrl } from "@/lib/siteurl";

export const revalidate = 3600;

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const taban = siteUrl();
  if (!taban) return [];

  const [{ hizmetler }, { projeler }] = await Promise.all([hizmetleriGetir(), projeleriGetir()]);
  const sabit = ["", "/hakkimizda", "/hizmetlerimiz", "/projeler", "/referanslar", "/kariyer", "/iletisim"];

  return [
    ...sabit.map((yol) => ({ url: `${taban}${yol}`, changeFrequency: "weekly" as const, priority: yol === "" ? 1 : 0.7 })),
    ...hizmetler.map((h) => ({ url: `${taban}/hizmetlerimiz/${h.slug}`, changeFrequency: "monthly" as const, priority: 0.6 })),
    ...projeler.map((p) => ({ url: `${taban}/projeler/${p.slug}`, changeFrequency: "monthly" as const, priority: 0.5 })),
  ];
}
