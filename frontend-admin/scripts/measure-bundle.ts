import fs from 'fs';
import path from 'path';
import zlib from 'zlib';

export interface BundleStats {
  initial_js_kb: number;
  largest_chunks: Array<{ file: string; gzip_kb: number }>;
}

interface ManifestChunk {
  file: string;
  isEntry?: boolean;
  imports?: string[];
}

function gzipKb(filePath: string): number {
  const content = fs.readFileSync(filePath);
  const gzipped = zlib.gzipSync(content);
  return Math.round((gzipped.length / 1024) * 10) / 10;
}

export function measureBundle(distDir: string): BundleStats {
  const manifestPath = path.join(distDir, '.vite', 'manifest.json');
  if (!fs.existsSync(manifestPath)) {
    throw new Error(`Vite manifest not found at ${manifestPath}. Build with "build.manifest: true" first.`);
  }

  const manifest: Record<string, ManifestChunk> = JSON.parse(fs.readFileSync(manifestPath, 'utf-8'));

  const entryKey = Object.keys(manifest).find((key) => manifest[key].isEntry);
  if (!entryKey) {
    throw new Error('No entry chunk found in Vite manifest.');
  }

  const entry = manifest[entryKey];

  // The initial bundle is the entry chunk plus anything it statically
  // imports (NOT its dynamicImports — those are lazy route chunks that
  // only load on navigation, not on first paint).
  const initialFiles = new Set<string>([entry.file]);
  for (const importKey of entry.imports ?? []) {
    const chunk = manifest[importKey];
    if (chunk) initialFiles.add(chunk.file);
  }

  let initialKb = 0;
  for (const file of initialFiles) {
    initialKb += gzipKb(path.join(distDir, file));
  }

  const assetsDir = path.join(distDir, 'assets');
  const allJsFiles = fs
    .readdirSync(assetsDir)
    .filter((f) => f.endsWith('.js'))
    .map((f) => ({ file: `assets/${f}`, gzip_kb: gzipKb(path.join(assetsDir, f)) }))
    .sort((a, b) => b.gzip_kb - a.gzip_kb)
    .slice(0, 10);

  return {
    initial_js_kb: Math.round(initialKb * 10) / 10,
    largest_chunks: allJsFiles,
  };
}
