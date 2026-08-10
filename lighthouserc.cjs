module.exports = {
    ci: {
        collect: {
            url: [
                'http://127.0.0.1:8018/',
                'http://127.0.0.1:8018/clinicas',
                'http://127.0.0.1:8018/verificar',
                'http://127.0.0.1:8018/login',
            ],
            numberOfRuns: 1,
            settings: {
                chromePath: 'C:\\Users\\PC\\AppData\\Local\\ms-playwright\\chromium-1234\\chrome-win64\\chrome.exe',
                chromeFlags: '--headless --no-sandbox',
            },
        },
        assert: {
            assertions: {
                'categories:performance': ['warn', { minScore: 0.8 }],
                'categories:accessibility': ['error', { minScore: 0.9 }],
                'categories:best-practices': ['warn', { minScore: 0.9 }],
                'categories:seo': ['warn', { minScore: 0.9 }],
                'cumulative-layout-shift': ['warn', { maxNumericValue: 0.1 }],
            },
        },
        upload: { target: 'filesystem', outputDir: './artifacts/lighthouse/latest' },
    },
};
