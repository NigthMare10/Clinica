import { execFileSync } from 'node:child_process';
import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';

export default async function globalSetup() {
    const root = process.cwd();
    rmSync(path.join(root, 'public', 'hot'), { force: true });
    const database = path.join(root, 'database', 'e2e.sqlite');
    mkdirSync(path.dirname(database), { recursive: true });
    writeFileSync(database, '');
    const env = { ...process.env, APP_ENV: 'e2e', DB_CONNECTION: 'sqlite', DB_DATABASE: database,
        APP_URL: 'http://127.0.0.1:8017', QUEUE_CONNECTION: 'sync', CACHE_STORE: 'array', SESSION_DRIVER: 'database',
        INSTITUTIONAL_PDF_PASSWORD: 'E2E-Pdf-Encryption-Only!', MEDICAL_PDF_ENCRYPTION: 'true' };
    execFileSync('php', ['-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3', 'artisan', 'migrate:fresh',
        '--seed', '--seeder=Database\\Seeders\\E2ESeeder', '--force'], { cwd: root, env, stdio: 'inherit' });
}
