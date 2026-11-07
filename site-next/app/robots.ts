import type { MetadataRoute } from "next";
import { siteUrl } from "@/lib/siteurl";

export default function robots(): MetadataRoute.Robots {
  const taban = siteUrl();
  return {
    rules: [{ userAgent: "*", allow: "/", disallow: ["/api/", "/kariyer/"] }],
    ...(taban ? { sitemap: `${taban}/sitemap.xml`, host: taban } : {}),
  };
}
