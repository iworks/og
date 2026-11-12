/**
 * Asset pipelines and cleanup.
 *
 * JS pipeline (grunt parity): concat sources → bundle (+ banner) → terser
 * → `.min.js` (+ min banner). This plugin has no CSS — the old grunt
 * sass/cssmin tasks were no-ops and are not ported.
 */

import { join, resolve, dirname } from "node:path";
import { existsSync, readFileSync, rmSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { log } from "./consoleHelpers.mjs";
import { cleanGlob, prettierSources, writeFileAtomic } from "./tools.mjs";
import { jsBundles, banner } from "./config.mjs";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");

/**
 * Build the JS bundles defined in `jsBundles` (concat + terser).
 */
export async function buildJs() {
  const { minify } = await import("terser");
  for (const bundle of jsBundles) {
    const missing = bundle.sources.filter(
      (src) => !existsSync(join(root, src)),
    );
    if (missing.length) {
      log(
        `js — missing sources for ${bundle.target}: ${missing.join(", ")}`,
        "w",
      );
    }
    const parts = bundle.sources
      .filter((src) => existsSync(join(root, src)))
      .map((src) => readFileSync(join(root, src), "utf8"));
    if (!parts.length) {
      log(`js — no sources for ${bundle.target}, skipped`, "w");
      continue;
    }
    writeFileAtomic(join(root, bundle.target), banner() + parts.join("\n"));
    const code = readFileSync(join(root, bundle.target), "utf8");
    const result = await minify(code, {
      mangle: { reserved: ["jQuery"] },
      format: { comments: "some" },
    });
    if (result.code) {
      writeFileAtomic(
        join(root, bundle.target.replace(/\.js$/, ".min.js")),
        banner({ minified: true }) + result.code,
      );
      log(`js — ${bundle.target} (+ .min.js) built`, "s");
    } else {
      throw new Error(`terser returned no output for ${bundle.target}`);
    }
  }
}

/**
 * Prettier pass over the JS sources (build pipeline runs it first).
 */
export async function formatSources() {
  await prettierSources([
    "assets/scripts/src/**/*.js",
    "bundler/**/*.js",
    "bundler/**/*.mjs",
    "*.js",
  ]);
  log("prettier — sources formatted", "s");
}

/**
 * Remove built files (js bundles + i18n).
 */
export async function cleanCompiled() {
  await cleanGlob("languages/*.mo");
  for (const bundle of jsBundles) {
    rmSync(join(root, bundle.target), { force: true });
    rmSync(join(root, bundle.target.replace(/\.js$/, ".min.js")), {
      force: true,
    });
  }
  log("clean — bundles + languages/*.mo removed", "i");
}
