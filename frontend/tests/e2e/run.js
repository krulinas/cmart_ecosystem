import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { requiresBookingData } from './helpers/preflight.js';

const require = createRequire(import.meta.url);
const __dirname = dirname(fileURLToPath(import.meta.url));
const frontendRoot = resolve(__dirname, '../..');
const mochaBin = require.resolve('mocha/bin/mocha.js');
const rawArgs = process.argv.slice(2);
const modeFlags = new Set(['--headless', '--headed']);
const passthroughArgs = [];
const specFiles = [];

for (const arg of rawArgs) {
  if (modeFlags.has(arg)) continue;
  if (arg.endsWith('.spec.js')) {
    specFiles.push(arg.startsWith('tests/') ? arg : `tests/e2e/specs/${arg}`);
    continue;
  }
  passthroughArgs.push(arg);
}

if (rawArgs.includes('--headless')) {
  process.env.E2E_HEADLESS = 'true';
} else if (rawArgs.includes('--headed')) {
  process.env.E2E_HEADLESS = 'false';
}

process.env.E2E_REQUIRES_BOOKING_DATA = requiresBookingData(specFiles) ? 'true' : 'false';

const mochaArgs = [
  ...(specFiles.length ? specFiles : ['tests/e2e/specs/**/*.spec.js']),
  '--timeout',
  '120000',
  '--file',
  'tests/e2e/setup.js',
  ...passthroughArgs,
];

const result = spawnSync(process.execPath, [mochaBin, ...mochaArgs], {
  cwd: frontendRoot,
  stdio: 'inherit',
  env: process.env,
});

process.exit(result.status ?? 1);
