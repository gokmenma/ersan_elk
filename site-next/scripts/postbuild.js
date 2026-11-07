const fs = require("node:fs");
const path = require("node:path");

const kok = path.join(__dirname, "..");
const hedef = path.join(kok, ".next", "standalone");

fs.cpSync(path.join(kok, "public"), path.join(hedef, "public"), { recursive: true });
fs.cpSync(path.join(kok, ".next", "static"), path.join(hedef, ".next", "static"), { recursive: true });

for (const ad of [".env.production", ".env.local"]) {
  const kaynak = path.join(kok, ad);
  if (fs.existsSync(kaynak)) fs.copyFileSync(kaynak, path.join(hedef, ad));
}
