module.exports = {
    ci: {
        collect: {
            url: [
                'http://127.0.0.1:8018/',
                'http://127.0.0.1:8018/especialidades',
                'http://127.0.0.1:8018/clinicas',
                'http://127.0.0.1:8018/verificar',
                'http://127.0.0.1:8018/login',
            ],
            numberOfRuns: 1,
            startServerCommand: 'php -S 127.0.0.1:8018 -t public tests/e2e/server.php',
            startServerReadyPattern: 'Development Server',
            settings: {
                chromePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
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
