const path = require("node:path");

const standalone = path.join(__dirname, ".next", "standalone");
process.chdir(standalone);
require(path.join(standalone, "server.js"));
