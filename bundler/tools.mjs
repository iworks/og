/**
 * In-process tool calls — prettier, terser and clean-css APIs.
 * All dist/source writes are atomic (temp + rename); tool failures are
 * rethrown as friendly one-line errors.
 */

import { dirname, join, relative, resolve } from "node:path";
import {
  mkdirSync,
  renameSync,
  writeFileSync,
  existsSync,
  readdirSync,
  rmSync,
} from "node:fs";
import { fileURLToPath } from "node:url";
import * as prettier from "prettier";
import { log } from "./consoleHelpers.mjs";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");

/**
 * Atomic write: temp file + rename.
 *
 * @param {string} file    Destination path.
 * @param {string} content Text content.
 */
export function writeFileAtomic(file, content) {
  mkdirSync(dirname(file), { recursive: true });
  const tmp = `${file}.tmp`;
  writeFileSync(tmp, content);
  renameSync(tmp, file);
}

/**
 * Friendly error: tool — file\nmessage (no stack trace noise).
 *
 * @param {string} tool Tool name.
 * @param {string} file File being processed.
 * @param {*}      error Thrown error.
 */
function friendly(tool, file, error) {
  const message =
    error && error.formatted
      ? error.formatted
      : error?.message || String(error);
  const err = new Error(
    `${tool} — ${relative(root, file) || file}\n${message}`,
  );
  err.friendly = true;
  throw err;
}

/**
 * Prettier — format a single file in place.
 *
 * @param {string} file File path.
 */
export async function prettierFile(file) {
  try {
    const source = await Bun.file(file).text();
    const formatted = await prettier.format(source, { filepath: file });
    if (formatted !== source) {
      writeFileAtomic(file, formatted);
      log(`prettier — ${relative(root, file)}`, "i");
    }
  } catch (error) {
    friendly("prettier", file, error);
  }
}

/**
 * Prettier — format every JS source file (glob pattern, relative to root).
 *
 * @param {string[]} patterns Glob patterns.
 */
export async function prettierSources(patterns) {
  for (const pattern of patterns) {
    const glob = new Bun.Glob(pattern);
    for await (const file of glob.scan({ cwd: root, onlyFiles: true })) {
      await prettierFile(join(root, file));
    }
  }
}

export async function cleanGlob(pattern) {
  const glob = new Bun.Glob(pattern);
  for await (const file of glob.scan({ cwd: root, onlyFiles: true })) {
    rmSync(join(root, file));
  }
}
