import type { NextConfig } from "next";
import path from "node:path";

function personelHost() {
  try {
    const url = new URL(process.env.PERSONEL_URL ?? "");
    return {
      protocol: url.protocol.replace(":", "") as "http" | "https",
      hostname: url.hostname,
      port: url.port,
      pathname: `${url.pathname.replace(/\/+$/, "")}/uploads/site/**`,
    };
  } catch {
    return null;
  }
}

const host = personelHost();

const nextConfig: NextConfig = {
  output: "standalone",
  outputFileTracingRoot: path.join(__dirname),
  images: {
    remotePatterns: host ? [host] : [],
  },
};

export default nextConfig;
