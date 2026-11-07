const fs = require("node:fs");
const path = require("node:path");

const kok = path.join(__dirname, "..");
const standalone = path.join(kok, ".next", "standalone");
const dist = path.join(kok, "dist");

fs.cpSync(path.join(kok, "public"), path.join(standalone, "public"), { recursive: true });
fs.cpSync(path.join(kok, ".next", "static"), path.join(standalone, ".next", "static"), { recursive: true });

fs.rmSync(dist, { recursive: true, force: true });
fs.cpSync(standalone, dist, {
  recursive: true,
  filter: (kaynak) => !path.basename(kaynak).startsWith(".env"),
});
