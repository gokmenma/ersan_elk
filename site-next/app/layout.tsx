import type { Metadata } from "next";
import { Roboto_Condensed } from "next/font/google";
import "./globals.css";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { siteAyarlari } from "@/lib/ayarlar";
import { siteUrl } from "@/lib/siteurl";

const roboto = Roboto_Condensed({
  variable: "--font-roboto",
  subsets: ["latin", "latin-ext"],
  weight: ["300", "400", "500", "600", "700", "800"],
});

export async function generateMetadata(): Promise<Metadata> {
  const { site, seo } = await siteAyarlari();
  const taban = siteUrl();
  return {
    metadataBase: taban ? new URL(taban) : undefined,
    title: { default: seo.title, template: `%s | ${site.name}` },
    description: seo.description,
    openGraph: {
      type: "website",
      locale: "tr_TR",
      siteName: site.name,
      title: seo.title,
      description: seo.description,
      images: [{ url: "/images/hero-1.jpg", width: 1024, height: 1024, alt: site.name }],
    },
    twitter: { card: "summary_large_image", title: seo.title, description: seo.description, images: ["/images/hero-1.jpg"] },
  };
}

export default async function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  const { site } = await siteAyarlari();
  const taban = siteUrl();
  const yapilandirilmis = {
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    name: site.name,
    telephone: site.phone,
    email: site.email,
    address: site.address,
    openingHours: site.hours,
    ...(taban ? { url: taban, image: `${taban}/images/logo.png` } : {}),
    ...(site.social.length > 0 ? { sameAs: site.social.map((x) => x.href) } : {}),
  };
  return (
    <html lang="tr">
      <body className={`${roboto.variable} antialiased`}>
        <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(yapilandirilmis).replace(/</g, "\\u003c") }} />
        <Header site={site} />
        <main>{children}</main>
        <Footer />
      </body>
    </html>
  );
}
