import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const __dirname = dirname(fileURLToPath(import.meta.url));
const frontendRoot = resolve(__dirname, '../..');
const mochaBin = require.resolve('mocha/bin/mocha.js');
const args = process.argv.slice(2);

if (args.includes('--headless')) {
  process.env.E2E_HEADLESS = 'true';
} else if (args.includes('--headed')) {
  process.env.E2E_HEADLESS = 'false';
}

const mochaArgs = [
  'tests/e2e/specs/**/*.spec.js',
  '--timeout',
  '60000',
  '--file',
  'tests/e2e/setup.js',
];

const result = spawnSync(process.execPath, [mochaBin, ...mochaArgs], {
  cwd: frontendRoot,
  stdio: 'inherit',
  env: process.env,
});

process.exit(result.status ?? 1);
