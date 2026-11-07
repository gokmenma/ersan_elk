export function siteUrl(): string | null {
  const ham = (process.env.SITE_URL ?? "").trim().replace(/\/+$/, "");
  if (!ham) return null;
  try {
    const u = new URL(ham);
    return u.protocol === "http:" || u.protocol === "https:" ? ham : null;
  } catch {
    return null;
  }
}
