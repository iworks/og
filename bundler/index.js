#!/usr/bin/env bun
/**
 * Bundler entry point — replaces the old Grunt pipeline.
 *
 * Modes:
 *   dev (default) — watcher, prettier on JS source change (no ZIP, no i18n)
 *   compile       — one-shot JS bundle build (old grunt `default`/`js` parity)
 *   build         — both flavors: build:github + build:wporg (+ Plugin Check)
 *   build:wporg   — production build → release/wporg/{name}.zip
 *   build:github  — production build + .mo → release/github/{name}.zip
 *   make-pot      — translations only (.pot + .po update)
 *   test          — PHPUnit (tests/php/phpunit.xml, not set up in this repo)
 *   update-libs   — update the vendored iWorks libraries (git submodules)
 */

import { dirname, join, resolve } from "node:path";
import { existsSync, watch } from "node:fs";
import { executionTime, log } from "./consoleHelpers.mjs";
import { ProgressBar } from "./progressBar.mjs";
import { spawnAsync } from "./spawnAsync.mjs";
import { config } from "./config.mjs";
import { formatSources, cleanCompiled, buildJs } from "./pipelines.mjs";
import { prettierFile } from "./tools.mjs";
import { makeZip } from "./makeZip.mjs";
import { makePot } from "./makePot.mjs";
import { pluginCheck } from "./pluginCheck.mjs";

const root = resolve(dirname(new URL(import.meta.url).pathname), "..");

/**
 * Friendly error printer — one-liners, no stack traces.
 *
 * @param {*} error Thrown error.
 */
function printError(error) {
  const message = error?.friendly
    ? error.message
    : error?.stack || String(error);
  log(message, "e");
}

/**
 * Production build for one flavor.
 *
 * @param {string} flavor "wporg" or "github".
 */
async function build(flavor) {
  const start = Date.now();
  try {
    const steps = flavor === "wporg" ? 7 : 6;
    const bar = new ProgressBar(steps);
    bar.tick("clean");
    await cleanCompiled();
    bar.tick("prettier");
    await formatSources();
    bar.tick("js");
    await buildJs();
    bar.tick("i18n");
    await makePot(flavor === "github");
    bar.tick("zip");
    await makeZip(flavor);
    if (flavor === "wporg") {
      bar.tick("plugin-check");
      await pluginCheck(config.name, config.version);
    }
    bar.tick("done");
    log(
      `BUILD OK (${flavor}) in ${((Date.now() - start) / 1000).toFixed(2)}s`,
      "s",
    );
  } catch (error) {
    log("BUILD FAILED", "e");
    printError(error);
    process.exit(1);
  }
}

/**
 * One-shot JS bundle build (grunt `default` parity).
 */
async function compile() {
  await executionTime("compile", async () => {
    await formatSources();
    await buildJs();
  });
}

/**
 * Dev watcher — prettier on JS source change, no ZIP, no i18n.
 */
function dev() {
  const exitAfterMs = Bun.env.DEV_EXIT_AFTER_MS || "";
  if (exitAfterMs) {
    setTimeout(() => {
      log(`dev — exiting after ${exitAfterMs} ms (DEV_EXIT_AFTER_MS)`, "i");
      process.exit(0);
    }, Number(exitAfterMs));
  }
  log(
    "dev — watching for changes (no auto browser reload, refresh manually)",
    "i",
  );

  let timer = null;
  const queue = new Set();

  const processQueue = async () => {
    timer = null;
    const changed = [...queue];
    queue.clear();
    const js = changed.some(
      (f) => f.startsWith("assets/scripts/src/") && f.endsWith(".js"),
    );
    if (!js) {
      return;
    }
    try {
      for (const file of changed.filter(
        (f) => f.startsWith("assets/scripts/src/") && f.endsWith(".js"),
      )) {
        await prettierFile(join(root, file));
      }
      log(`dev — formatted (${changed.length} changed)`, "s");
    } catch (error) {
      printError(error);
    }
  };

  watch(root, { recursive: true }, (event, filename) => {
    if (!filename) {
      return;
    }
    const rel = filename.replace(/\\/g, "/");
    const relevant =
      rel.startsWith("assets/scripts/src/") && rel.endsWith(".js");
    if (!relevant) {
      return;
    }
    queue.add(rel);
    if (!timer) {
      timer = setTimeout(processQueue, 200);
    }
  });
}

/**
 * PHPUnit (grunt `test` parity, jshint dropped).
 */
async function test() {
  const result = await spawnAsync(
    "phpunit",
    ["--configuration", "tests/php/phpunit.xml", "--colors=always"],
    "PHPUnit",
    false,
  );
  if (result.stdout.trim()) {
    console.log(result.stdout.trim());
  }
  if (result.code !== 0) {
    log(
      "If phpunit is not installed, set up a WordPress test environment (see tests/php/bootstrap.php).",
      "w",
    );
    process.exit(result.code);
  }
  log("PHPUnit passed", "s");
}

/**
 * Update the vendored iWorks libraries (git submodules) — parity with the
 * ~/bin/update-iworks-libriares.sh script: git pull inside each submodule.
 */
async function updateLibraries() {
  const submodules = [{ path: "includes/iworks/rate", label: "iWorks Rate" }];
  let failed = false;
  for (const { path, label } of submodules) {
    if (!existsSync(join(root, path))) {
      log(`update-libs — ${path} not found, skipped`, "w");
      continue;
    }
    log(`update-libs — updating ${label} (${path})`, "i");
    const result = await spawnAsync("git", ["-C", path, "pull"], label, false);
    if (result.stdout.trim()) {
      console.log(result.stdout.trim());
    }
    if (result.stderr.trim()) {
      console.log(result.stderr.trim());
    }
    if (result.code !== 0) {
      failed = true;
    }
  }
  if (failed) {
    log("update-libs — one or more submodules failed to update", "e");
    process.exit(1);
  }
  log("update-libs — all iWorks libraries updated", "s");
}

// ─── Mode dispatch ────────────────────────────────────────────────────────────

const mode = process.argv[2] || "dev";

const modes = {
  dev: () => dev(),
  compile: () => compile(),
  build: async () => {
    await build("github");
    await build("wporg");
  },
  "build:wporg": () => build("wporg"),
  "build:github": () => build("github"),
  "make-pot": () => executionTime("make-pot", () => makePot(false)),
  test: () => test(),
  "update-libs": () => updateLibraries(),
};

if (!modes[mode]) {
  log(
    `Unknown mode "${mode}". Available: ${Object.keys(modes).join(", ")}`,
    "e",
  );
  process.exit(1);
}

try {
  await modes[mode]();
} catch (error) {
  printError(error);
  process.exit(1);
}
