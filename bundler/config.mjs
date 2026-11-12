/**
 * Central build configuration — paths, names, placeholder patterns.
 * Everything is derived from package.json.
 */

import { readFileSync } from "node:fs";

export const pkg = JSON.parse(readFileSync("package.json", "utf8"));

export const config = {
  // Plugin identity (single source of truth: package.json).
  name: pkg.name,
  title: pkg.title,
  version: pkg.version,
  license: pkg.license,
  homepage: pkg.homepage || pkg.uri || "",
  website: pkg.repository?.website || "",
  author: pkg.author?.[0] || pkg.contributors?.[0] || {},

  // Sources.
  sassDir: "assets/sass",
  jsSrcDir: "assets/scripts/src",

  // i18n.
  languagesDir: "languages",
  textdomain: pkg.name,

  // Release.
  releaseDir: "release",
  buildTimestamp: Date.now(),
  buildTime: new Date().toISOString(),
  buildYear: new Date().getFullYear(),

  // wp-cli.phar (downloaded by `postinstall`).
  wpCli: "wp-cli.phar",
};

/**
 * JS bundles: concat sources → target, then terser → `.min.js`.
 * (Grunt parity: `js_files_concat` + uglify.)
 */
export const jsBundles = [
  {
    target: "assets/scripts/og-admin.js",
    sources: ["assets/scripts/src/admin/featured-image-warning-classic.js"],
  },
  {
    target: "assets/scripts/og-admin-gutenberg.js",
    sources: ["assets/scripts/src/admin/featured-image-warning-gutenberg.js"],
  },
];

/**
 * Concat/uglify banner (grunt parity).
 */
export function banner({ minified = false } = {}) {
  return (
    `/*! ${config.title} - ${config.version}\n` +
    ` * ${config.homepage}\n` +
    ` * Copyright (c) ${config.buildYear}${minified ? ";" : ""}` +
    ` * Licensed ${config.license}` +
    ` */\n`
  );
}

/**
 * Grunt-parity replacement patterns, applied to the release copies only.
 * `CURRENT_YEAR` is intentionally NOT here — the scaffolding script bakes it in.
 *
 * @return {Array<{match: RegExp, replace: string}>}
 */
export function replacePatterns() {
  const {
    name,
    title,
    tagline,
    description,
    tags = [],
    requires = {},
    tested = {},
  } = pkg;
  return [
    { match: /AUTHOR_NAME/g, replace: config.author.name || "" },
    { match: /AUTHOR_URI/g, replace: config.author.uri || "" },
    { match: /BUILDTIME/g, replace: config.buildTime },
    { match: /IWORKS_RATE_TEXTDOMAIN/g, replace: name },
    { match: /IWORKS_OPTIONS_TEXTDOMAIN/g, replace: name },
    { match: /PLUGIN_DESCRIPTION/g, replace: description || "" },
    { match: /PLUGIN_GITHUB_WEBSITE/g, replace: config.website },
    { match: /PLUGIN_NAME/g, replace: name },
    { match: /PLUGIN_REQUIRES_PHP/g, replace: requires.PHP || "" },
    { match: /PLUGIN_REQUIRES_WORDPRESS/g, replace: requires.WordPress || "" },
    { match: /PLUGIN_TESTED_WORDPRESS/g, replace: tested.WordPress || "" },
    { match: /PLUGIN_TAGLINE/g, replace: tagline || "" },
    { match: /PLUGIN_TAGS/g, replace: tags.join(", ") },
    { match: /PLUGIN_TILL_YEAR/g, replace: String(config.buildYear) },
    { match: /PLUGIN_TITLE/g, replace: title || "" },
    // `\b` anchors keep longer tokens containing these words intact.
    { match: /\bPLUGIN_URI\b/g, replace: config.homepage },
    { match: /\bPLUGIN_VERSION\b/g, replace: config.version },
    { match: /^Version: .*$/m, replace: `Version: ${config.version}` },
  ];
}

/**
 * Extensions never text-processed during the release replacement.
 */
export const BINARY_EXTENSIONS = new Set([
  "png",
  "jpg",
  "jpeg",
  "gif",
  "svg",
  "webp",
  "avif",
  "ico",
  "woff",
  "woff2",
  "ttf",
  "eot",
  "otf",
  "mo",
  "map",
  "zip",
  "phar",
]);

/**
 * Ordered copy rules for the release ZIPs (grunt-parity, first match wins).
 *
 * @param {string} flavor "wporg" or "github".
 * @return {Array<{glob: string, include: boolean}>}
 */
export function copyRules(flavor) {
  const base = [
    // Build tooling and repo meta never ship.
    { glob: "**/.git/**", include: false },
    { glob: "**/.git", include: false },
    { glob: ".gitignore", include: false },
    { glob: "**/.gitignore", include: false },
    { glob: ".gitmodules", include: false },
    { glob: ".github/**", include: false },
    { glob: ".editorconfig", include: false },
    { glob: "**/.editorconfig", include: false },
    { glob: ".env*", include: false },
    { glob: ".opencode/**", include: false },
    { glob: ".playwright-mcp/**", include: false },
    { glob: "AGENTS.md", include: false },
    { glob: "**/AGENTS.md", include: false },
    { glob: "bundler/**", include: false },
    { glob: "**/Gruntfile.js", include: false },
    { glob: "bun.lock", include: false },
    { glob: "bun.lockb", include: false },
    { glob: "wp-cli.phar", include: false },
    { glob: "plugin-check.log", include: false },
    { glob: "**/node_modules/**", include: false },
    { glob: "**/package.json", include: false },
    { glob: "package-lock.json", include: false },
    { glob: "postcss.config.js", include: false },
    { glob: "webpack.config.js", include: false },
    { glob: "release/**", include: false },
    { glob: ".sass-cache/**", include: false },
    { glob: "**/tests/**", include: false },
    { glob: "LICENSE", include: false },
    { glob: "**/LICENSE", include: false },
    { glob: "**/*.map", include: false },
    { glob: "**/README.md", include: false },
    { glob: "README.md", include: false },
    { glob: "contributing.md", include: false },
    { glob: "**/bitbucket-pipelines.yml", include: false },
    { glob: "o", include: false },
    { glob: "**/css/less/**", include: false },
    { glob: "**/css/sass/**", include: false },
    { glob: "**/css/src/**", include: false },
    { glob: "**/img/src/**", include: false },
    { glob: "**/js/src/**", include: false },
  ];
  const flavorRules =
    flavor === "github"
      ? // github: no readme.txt (pairs with the self-updater class), po kept.
        [{ glob: "readme.txt", include: false }]
      : // wporg: no asset sources, no self-updater, no po/mo.
        [
          { glob: "assets/sass/**", include: false },
          { glob: "assets/scripts/src/**", include: false },
          { glob: "assets/scss/**", include: false },
          { glob: "assets/styles/frontend/**", include: false },
          {
            glob: "includes/iworks/opengraph/class-iworks-opengraph-github.php",
            include: false,
          },
          { glob: "languages/*.mo", include: false },
          { glob: "languages/*.po", include: false },
        ];
  // The catch-all include must come LAST (first match wins, like grunt).
  return [...base, ...flavorRules, { glob: "**/*", include: true }];
}
