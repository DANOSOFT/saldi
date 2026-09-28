import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    testMatch: '**/*.spec.mjs',
    workers: 1,
    retries: 0,
    reporter: 'list',
    outputDir: process.env.SALDI_UI_TEST_OUTPUT || '../../.codex-run/ui-test-results',
    use: {
        browserName: 'chromium',
        headless: true,
        launchOptions: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
            ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH }
            : {},
    },
});
