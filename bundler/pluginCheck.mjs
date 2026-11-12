/**
 * WordPress.org Plugin Check (PCP) step for the wporg release build.
 *
 * Copies the staged wporg release directory into the local WordPress
 * plugins directory under the real plugin slug and runs
 * `wp plugin check <slug>` against it, so the results match what
 * WordPress.org's Plugin Checker reports for the released code.
 */

import { dirname, join, resolve } from "node:path";
import {
  cpSync,
  existsSync,
  rmSync,
  symlinkSync,
  writeFileSync,
} from "node:fs";
import { relative } from "node:path";
import { log } from "./consoleHelpers.mjs";
import { spawnAsync } from "./spawnAsync.mjs";

const root = resolve(dirname(new URL(import.meta.url).pathname), "..");

/**
 * Run Plugin Check on the staged wporg release.
 *
 * Requires wp-cli.phar (downloaded by postinstall) and the Plugin Check
 * plugin installed in the local WordPress installation. The build fails
 * when any ERROR result is reported; warnings are informational.
 * Set PLUGIN_CHECK_SKIP=1 to skip the check entirely.
 *
 * @param {string} slug Plugin slug (directory name in the release).
 * @param {string} version Plugin version (staging subdirectory).
 * @return {Promise<{errors: number, warnings: number}>} Result counts.
 */
export async function pluginCheck(slug, version) {
  if (Bun.env.PLUGIN_CHECK_SKIP === "1") {
    log("plugin-check — skipped (PLUGIN_CHECK_SKIP=1)", "w");
    return { errors: 0, warnings: 0 };
  }
  const wpCli = join(root, "wp-cli.phar");
  if (!existsSync(wpCli)) {
    log("plugin-check — wp-cli.phar not found, skipped (run bun install)", "w");
    return { errors: 0, warnings: 0 };
  }
  const pluginsDir = Bun.env.PLUGIN_CHECK_PLUGINS_DIR || dirname(root);
  const checkPluginDir = join(pluginsDir, "plugin-check");
  if (!existsSync(checkPluginDir)) {
    log(
      "plugin-check — the Plugin Check plugin is not installed, skipped (https://wordpress.org/plugins/plugin-check/)",
      "w",
    );
    return { errors: 0, warnings: 0 };
  }
  const stagedDir = join(root, "release", "wporg", version, slug);
  if (!existsSync(stagedDir)) {
    const err = new Error(
      `plugin-check — staged directory not found: ${stagedDir}`,
    );
    err.friendly = true;
    throw err;
  }
  // Install the staged release under the real slug, check it, remove it.
  const target = join(pluginsDir, slug);
  rmSync(target, { recursive: true, force: true });
  cpSync(stagedDir, target, { recursive: true });
  let errors = 0;
  let warnings = 0;
  try {
    const result = await spawnAsync(
      "php",
      [
        "-d",
        "error_reporting=225",
        wpCli,
        "plugin",
        "check",
        slug,
        "--exclude-directories=includes/iworks/rate",
      ],
      "wp plugin check",
      false,
    );
    if (result.code !== 0 && !result.stdout.trim()) {
      const err = new Error(
        `plugin-check — wp plugin check failed with no output (exit ${result.code}):\n${result.stderr.split("\n").filter(Boolean).slice(0, 5).join("\n")}`,
      );
      err.friendly = true;
      throw err;
    }
    const lines = result.stdout.split("\n");
    const errorLines = [];
    for (const line of lines) {
      if (line.includes("\tERROR\t")) {
        errors++;
        errorLines.push(line);
      } else if (line.includes("\tWARNING\t")) {
        warnings++;
      }
    }
    if (errorLines.length) {
      log(`plugin-check — ${errors} error(s):`, "e");
      for (const line of errorLines) {
        console.log("  " + line);
      }
    }
    if (warnings) {
      log(`plugin-check — ${warnings} warning(s)`, "w");
    }
    // Full results to a log file (gitignored).
    const logFile = join(root, "plugin-check.log");
    const header = `# plugin-check — ${new Date().toISOString()} — ${slug} ${version}\n`;
    writeFileSync(
      logFile,
      header + (result.stdout.trim() || "(no output)") + "\n",
    );
    log(`plugin-check — full output written to plugin-check.log`, "i");
  } finally {
    rmSync(target, { recursive: true, force: true });
  }
  symlinkRelease(pluginsDir, slug, version);
  if (errors) {
    const err = new Error(
      `plugin-check — ${errors} Plugin Check error(s) (PLUGIN_CHECK_SKIP=1 to skip)`,
    );
    err.friendly = true;
    throw err;
  }
  log("plugin-check — passed", "s");
  return { errors, warnings };
}

/**
 * Symlink the just-built wporg release into the WordPress plugins
 * directory, so the local site loads the actual built version.
 *
 * ⚠️ In this environment the dev plugin lives at `dev-og/` and may be the
 * active copy — the symlink would REPLACE it with the release build
 * (placeholders replaced, dev files gone). Only enable via
 * PLUGIN_SYMLINK=1.
 *
 * @param {string} pluginsDir Local WordPress plugins directory.
 * @param {string} slug Plugin slug.
 * @param {string} version Plugin version (staging subdirectory).
 * @return {void}
 */
function symlinkRelease(pluginsDir, slug, version) {
  if (Bun.env.PLUGIN_SYMLINK !== "1") {
    return;
  }
  if (process.platform !== "linux" && process.platform !== "darwin") {
    log(
      `plugin-check — symlink skipped (unsupported platform: ${process.platform})`,
      "w",
    );
    return;
  }
  const stagedDir = join("release", "wporg", version, slug);
  const link = join(pluginsDir, slug);
  try {
    rmSync(link, { recursive: true, force: true });
    symlinkSync(relative(pluginsDir, join(root, stagedDir)), link);
    log(`plugin-check — ${link} → ${stagedDir}`, "s");
  } catch (error) {
    log(`plugin-check — symlink failed: ${error.message}`, "w");
  }
}
