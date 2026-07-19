import { defineConfig, globalIgnores } from 'eslint/config'
import globals from 'globals'
import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import pluginOxlint from 'eslint-plugin-oxlint'
import skipFormatting from 'eslint-config-prettier/flat'

export default defineConfig([
  {
    name: 'app/files-to-lint',
    files: ['**/*.{vue,js,mjs,jsx}'],
  },

  globalIgnores(['**/dist/**', '**/dist-ssr/**', '**/coverage/**']),

  {
    languageOptions: {
      globals: {
        ...globals.browser,
      },
    },
  },

  {
    files: ['tests/e2e/**/*.js'],
    languageOptions: {
      globals: {
        ...globals.node,
      },
    },
  },

  {
    files: [
      'tests/e2e/specs/access.cmart-management-boundary.spec.js',
      'tests/e2e/specs/access.destructive-action-protection.spec.js',
      'tests/e2e/specs/access.guest-protection.spec.js',
      'tests/e2e/specs/access.vendor-ownership-guard.spec.js',
      'tests/e2e/specs/auth.cmart-management-login.spec.js',
      'tests/e2e/specs/auth.login.spec.js',
      'tests/e2e/specs/auth.organizer-login.spec.js',
      'tests/e2e/specs/open-vendor-onboarding-flow.spec.js',
      'tests/e2e/specs/organizer.booking-approval.spec.js',
      'tests/e2e/specs/organizer.booking-revision.spec.js',
      'tests/e2e/specs/public.public-route-safety.spec.js',
      'tests/e2e/specs/vendor.booking-approved.spec.js',
      'tests/e2e/specs/vendor.booking-withdraw.spec.js',
      'tests/e2e/specs/vendor.invoice-visible-after-approval.spec.js',
      'tests/e2e/specs/vendor.payment-submit.spec.js',
      'tests/e2e/specs/vendor.payment-verification-pass-unlock.spec.js',
      'tests/e2e/specs/vendor.receipt-pass-after-paid.spec.js',
      'tests/e2e/specs/vendor.withdrawal.spec.js',
    ],
    languageOptions: {
      globals: {
        ...globals.mocha,
      },
    },
  },

  js.configs.recommended,
  ...pluginVue.configs['flat/essential'],

  ...pluginOxlint.buildFromOxlintConfigFile('.oxlintrc.json'),

  skipFormatting,
])
