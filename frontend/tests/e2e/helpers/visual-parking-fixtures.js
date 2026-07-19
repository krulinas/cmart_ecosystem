import { spawnSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const backendRoot = resolve(__dirname, '../../../../backend');

function run(action) {
  const result = spawnSync(
    'php',
    [
      'artisan',
      'e2e:visual-parking-layout-fixtures',
      action,
      '--json',
      '--env=e2e',
    ],
    {
      cwd: backendRoot,
      encoding: 'utf-8',
      shell: process.platform === 'win32',
      env: {
        ...process.env,
        APP_ENV: 'e2e',
        DB_DATABASE: 'cmart_e2e_db',
      },
    },
  );

  if (result.status !== 0) {
    throw new Error(
      `Visual parking fixture ${action} failed (exit ${result.status}): ${result.stderr || result.stdout}`,
    );
  }

  const jsonLine = result.stdout
    .split(/\r?\n/)
    .map((line) => line.trim())
    .find((line) => line.startsWith('{'));

  if (!jsonLine) {
    throw new Error(`Could not parse visual parking fixture output:\n${result.stdout}`);
  }

  const payload = JSON.parse(jsonLine);
  if (payload.database !== 'cmart_e2e_db') {
    throw new Error(`Unsafe visual parking fixture database: ${payload.database || '(missing)'}`);
  }

  return payload;
}

export function createVisualParkingFixtures() {
  return run('create');
}

export function cleanupVisualParkingFixtures() {
  return run('cleanup');
}
