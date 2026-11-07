const fs = require("node:fs");
const path = require("node:path");

const envDosyasi = path.join(__dirname, ".env.production");
if (fs.existsSync(envDosyasi)) process.loadEnvFile(envDosyasi);

const dist = path.join(__dirname, "dist");
process.chdir(dist);
require(path.join(dist, "server.js"));
