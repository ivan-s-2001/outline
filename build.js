/* oxlint-disable no-console */
/* oxlint-disable @typescript-oxlint/no-var-requires */
/* oxlint-disable no-undef */

const { exec } = require("child_process");
const {
  readdirSync,
  existsSync,
  rmSync,
  mkdirSync,
  copyFileSync,
} = require("fs");
const path = require("path");

const getDirectories = (source) =>
  readdirSync(source, { withFileTypes: true })
    .filter((dirent) => dirent.isDirectory())
    .map((dirent) => dirent.name);

function execAsync(cmd) {
  return new Promise((resolve, reject) => {
    exec(cmd, (error, stdout, stderr) => {
      if (error) {
        reject(error);
      } else {
        resolve(stdout || stderr);
      }
    });
  });
}

function copyFile(source, destination) {
  mkdirSync(path.dirname(destination), { recursive: true });
  copyFileSync(source, destination);
}

async function build() {
  console.log("Clean previous build...");

  rmSync("./build/server", { recursive: true, force: true });
  rmSync("./build/plugins", { recursive: true, force: true });

  const plugins = getDirectories("./plugins");

  console.log("Compiling...");

  const swc = (source, destination) =>
    execAsync(
      `corepack yarn swc "${source}" -d "${destination}" ` +
        `--strip-leading-paths ` +
        `--extensions .ts,.tsx ` +
        `--ignore "**/*.test.ts,**/*.test.tsx,**/__mocks__/**" ` +
        `--quiet`
    );

  await Promise.all([
    swc("./server", "./build/server"),
    swc("./shared", "./build/shared"),
  ]);

  for (const plugin of plugins) {
    const serverDirectory = `./plugins/${plugin}/server`;
    if (existsSync(serverDirectory)) {
      await swc(serverDirectory, "./build/plugins");
    }

    const sharedDirectory = `./plugins/${plugin}/shared`;
    if (existsSync(sharedDirectory)) {
      await swc(sharedDirectory, "./build/plugins");
    }
  }

  console.log("Copying static files...");

  copyFile(
    "./server/collaboration/Procfile",
    "./build/server/collaboration/Procfile"
  );
  copyFile(
    "./server/static/error.dev.html",
    "./build/server/error.dev.html"
  );
  copyFile(
    "./server/static/error.prod.html",
    "./build/server/error.prod.html"
  );
  copyFile("./package.json", "./build/package.json");

  for (const plugin of plugins) {
    const source = `./plugins/${plugin}/plugin.json`;
    if (existsSync(source)) {
      copyFile(source, `./build/plugins/${plugin}/plugin.json`);
    }
  }

  console.log("Done!");
}

void build();
