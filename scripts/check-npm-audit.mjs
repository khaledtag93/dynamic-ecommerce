import fs from 'node:fs';

const reportPath = process.argv[2];

if (!reportPath) {
    console.error('Usage: node scripts/check-npm-audit.mjs <npm-audit.json>');
    process.exit(2);
}

const report = JSON.parse(fs.readFileSync(reportPath, 'utf8'));
const vulnerabilities = report.vulnerabilities ?? {};

const allowedPackages = new Set(['vite', 'esbuild']);
const allowedAdvisories = new Set([
    'GHSA-4w7w-66w2-5vf9',
    'GHSA-v6wh-96g9-6wx3',
    'GHSA-fx2h-pf6j-xcff',
    'GHSA-67mh-4wv8-2f99',
]);

const unexpectedPackages = Object.keys(vulnerabilities)
    .filter((name) => !allowedPackages.has(name));

const unexpectedAdvisories = [];

for (const [packageName, vulnerability] of Object.entries(vulnerabilities)) {
    for (const via of vulnerability.via ?? []) {
        if (typeof via !== 'object' || !via?.url) {
            continue;
        }

        const advisoryId = via.url.split('/').pop();

        if (!allowedAdvisories.has(advisoryId)) {
            unexpectedAdvisories.push({
                package: packageName,
                advisory: advisoryId,
                severity: via.severity ?? vulnerability.severity ?? 'unknown',
                title: via.title ?? 'unknown advisory',
            });
        }
    }
}

if (unexpectedPackages.length > 0 || unexpectedAdvisories.length > 0) {
    console.error('Unexpected npm audit findings detected.');
    console.error(JSON.stringify({ unexpectedPackages, unexpectedAdvisories }, null, 2));
    process.exit(1);
}

const counts = report.metadata?.vulnerabilities ?? {};
console.log(
    `npm audit reviewed baseline accepted: ${counts.total ?? 0} total ` +
    `(${counts.moderate ?? 0} moderate, ${counts.high ?? 0} high, ${counts.critical ?? 0} critical).`
);
console.log('Allowed findings are limited to the reviewed Vite/esbuild development-tooling advisories.');
