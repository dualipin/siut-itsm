<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class UserBulkImportService
{
    private const ALLOWED_DOC_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    /**
     * Get the base directory for a given import batch.
     */
    public function batchPath(string $batchId): string
    {
        $safeBatch = preg_replace('/[^a-zA-Z0-9_-]/', '', $batchId) ?: 'default';

        return storage_path('app/private/imports/'.$safeBatch);
    }

    /**
     * Store an uploaded file chunk and assemble/extract once all chunks arrive.
     *
     * @return array{completed: bool, filename: string, kind: string, total_rows?: int, extracted_files?: int}
     */
    public function storeChunk(
        string $batchId,
        string $fileId,
        int $chunkIndex,
        int $totalChunks,
        string $originalName,
        UploadedFile $chunk,
    ): array {
        $safeFileId = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileId) ?: 'file';
        $safeName = $this->sanitizeFilename($originalName);
        $baseDir = $this->batchPath($batchId);
        $chunksDir = $baseDir.'/chunks/'.$safeFileId;
        $filesDir = $baseDir.'/files';

        File::ensureDirectoryExists($chunksDir);
        File::ensureDirectoryExists($filesDir);

        $chunk->move($chunksDir, (string) $chunkIndex);

        if ($chunkIndex < $totalChunks - 1) {
            return [
                'completed' => false,
                'filename' => $safeName,
                'kind' => $this->detectFileKind($safeName),
            ];
        }

        $assembledPath = $filesDir.'/'.$safeName;
        $out = fopen($assembledPath, 'wb');
        if ($out === false) {
            throw new \RuntimeException("No se pudo crear el archivo ensamblado: {$safeName}");
        }

        try {
            for ($i = 0; $i < $totalChunks; $i++) {
                $partPath = $chunksDir.'/'.$i;
                if (! is_file($partPath)) {
                    throw new \RuntimeException("Falta el fragmento {$i} de {$safeName}");
                }
                $in = fopen($partPath, 'rb');
                if ($in === false) {
                    throw new \RuntimeException("No se pudo leer el fragmento {$i} de {$safeName}");
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
            File::deleteDirectory($chunksDir);
        }

        $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        $result = [
            'completed' => true,
            'filename' => $safeName,
            'kind' => $this->detectFileKind($safeName),
        ];

        if ($ext === 'csv') {
            $canonicalCsv = $filesDir.'/padron.csv';
            if ($assembledPath !== $canonicalCsv) {
                File::move($assembledPath, $canonicalCsv);
            }
            $rows = $this->parseCsv($canonicalCsv);
            $result['total_rows'] = count($rows);
        } elseif ($ext === 'zip') {
            $result['extracted_files'] = $this->extractZipSafely($assembledPath, $baseDir.'/extracted');
            File::delete($assembledPath);
        }

        return $result;
    }

    /**
     * Parse the uploaded CSV file into normalized user rows.
     *
     * @return list<array<string, mixed>>
     */
    public function parseCsv(string $csvPath): array
    {
        if (! is_file($csvPath)) {
            throw new \RuntimeException('No se encontró el archivo CSV del padrón.');
        }

        $content = file_get_contents($csvPath);
        if ($content === false) {
            throw new \RuntimeException('No se pudo leer el archivo CSV.');
        }

        // Strip UTF-8 BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el buffer temporal para el CSV.');
        }

        fwrite($handle, $content);
        rewind($handle);

        $rawHeaders = fgetcsv($handle, 0, ',', '"', '\\');
        if (! is_array($rawHeaders)) {
            fclose($handle);
            throw new \RuntimeException('El archivo CSV está vacío o tiene encabezados inválidos.');
        }

        $headers = array_map(fn (?string $h): string => $this->normalizeHeader((string) $h), $rawHeaders);
        $rows = [];
        $lineNumber = 1;

        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $lineNumber++;
            if ($data === [null] || count(array_filter($data, fn (?string $v): bool => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = isset($data[$index]) ? trim((string) $data[$index]) : '';
            }
            $assoc['_line'] = $lineNumber;
            $rows[] = $assoc;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Process a slice of rows from the batch CSV and attach matching documents.
     *
     * @return array{
     *     processed: int,
     *     created: int,
     *     updated: int,
     *     docs_attached: int,
     *     errors: list<array{line: int, name: string, error: string}>,
     *     total: int
     * }
     */
    public function processBatch(
        string $batchId,
        int $offset,
        int $limit,
        string $fallbackPassword = 'Siut2026!',
    ): array {
        $baseDir = $this->batchPath($batchId);
        $csvPath = $baseDir.'/files/padron.csv';
        $extractedDir = $baseDir.'/extracted';

        $rows = $this->parseCsv($csvPath);
        $total = count($rows);
        $slice = array_slice($rows, $offset, $limit);

        $docIndex = $this->indexExtractedDocuments($extractedDir);

        $created = 0;
        $updated = 0;
        $docsAttached = 0;
        $errors = [];

        foreach ($slice as $row) {
            $line = (int) ($row['_line'] ?? 0);
            $fullName = trim(($row['nombre'] ?? '').' '.($row['apellidos'] ?? ''));

            try {
                $outcome = DB::transaction(function () use ($row, $fallbackPassword, $docIndex): array {
                    return $this->upsertRow($row, $fallbackPassword, $docIndex);
                });

                if ($outcome['created']) {
                    $created++;
                } else {
                    $updated++;
                }
                $docsAttached += $outcome['docs_attached'];
            } catch (Throwable $e) {
                Log::warning('Error al importar fila del padrón', [
                    'batch_id' => $batchId,
                    'line' => $line,
                    'exception' => $e->getMessage(),
                ]);

                $errors[] = [
                    'line' => $line,
                    'name' => $fullName !== '' ? $fullName : "Fila {$line}",
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'processed' => count($slice),
            'created' => $created,
            'updated' => $updated,
            'docs_attached' => $docsAttached,
            'errors' => $errors,
            'total' => $total,
        ];
    }

    /**
     * Remove temporary files for a batch.
     */
    public function cleanup(string $batchId): void
    {
        $baseDir = $this->batchPath($batchId);
        if (File::isDirectory($baseDir)) {
            gc_collect_cycles();
            File::deleteDirectory($baseDir);
            clearstatcache(true);
        }
    }

    /**
     * Upsert a single CSV row and attach its INE and comprobante_pago documents if present.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, array{ine?: string, comprobante_pago?: string}>  $docIndex
     * @return array{created: bool, docs_attached: int}
     */
    protected function upsertRow(array $row, string $fallbackPassword, array $docIndex): array
    {
        $name = trim((string) ($row['nombre'] ?? ''));
        $surnames = trim((string) ($row['apellidos'] ?? ''));

        if ($name === '') {
            throw new \InvalidArgumentException('El campo "nombre" es obligatorio.');
        }

        $curp = $this->nullableUpper($row['curp'] ?? null);
        $rawEmail = trim((string) ($row['email'] ?? ''));
        $email = $rawEmail !== '' ? mb_strtolower($rawEmail) : null;
        $nss = $this->nullableTrim($row['nss'] ?? null);

        $existing = null;
        if ($curp !== null) {
            $existing = User::withTrashed()->where('curp', $curp)->first();
        }
        if ($existing === null && $email !== null) {
            $existing = User::withTrashed()->where('email', $email)->first();
        }

        $role = $this->resolveRole((string) ($row['rol'] ?? 'agremiado'));
        $birthDate = $this->parseDate($row['fecha_nacimiento'] ?? null);
        $hiringDate = $this->parseDate($row['fecha_ingreso'] ?? null);
        $phone = $this->nullableTrim($row['telefono'] ?? null);
        $address = $this->nullableTrim($row['direccion'] ?? null);
        $category = $this->nullableTrim($row['categoria'] ?? null);

        $isNew = $existing === null;

        if ($isNew) {
            $resolvedEmail = $email ?? $this->generatePlaceholderEmail($curp, $name, $surnames);
            $initialPassword = $curp ?? $fallbackPassword;

            // Avoid violating unique Lider constraint if a Lider already exists
            if ($role === UserRole::Lider && User::where('role', UserRole::Lider)->exists()) {
                $role = UserRole::Agremiado;
            }

            $user = User::create([
                'name' => $name,
                'surnames' => $surnames !== '' ? $surnames : null,
                'email' => $resolvedEmail,
                'role' => $role,
                'is_active' => true,
                'curp' => $curp,
                'birth_date' => $birthDate,
                'phone' => $phone,
                'address' => $address,
                'category' => $category,
                'nss' => $nss,
                'hiring_date' => $hiringDate,
                'password' => $initialPassword,
            ]);
        } else {
            $user = $existing;
            if ($user->trashed()) {
                $user->restore();
            }

            $updates = [
                'name' => $name,
                'surnames' => $surnames !== '' ? $surnames : $user->surnames,
                'birth_date' => $birthDate ?? $user->birth_date,
                'phone' => $phone ?? $user->phone,
                'address' => $address ?? $user->address,
                'category' => $category ?? $user->category,
                'hiring_date' => $hiringDate ?? $user->hiring_date,
            ];

            if ($curp !== null && $user->curp === null) {
                $updates['curp'] = $curp;
            }

            if ($nss !== null && $user->nss === null) {
                $updates['nss'] = $nss;
            }

            if ($email !== null && str_ends_with((string) $user->email, '@pendiente.siut.local')) {
                $updates['email'] = $email;
            }

            $user->update($updates);
        }

        $docsAttached = $this->attachDocumentsForUser($user, $curp, $name, $surnames, $docIndex);

        return [
            'created' => $isNew,
            'docs_attached' => $docsAttached,
        ];
    }

    /**
     * Attach INE and comprobante_pago documents if present in the extracted index.
     *
     * @param  array<string, array{ine?: string, comprobante_pago?: string}>  $docIndex
     */
    protected function attachDocumentsForUser(
        User $user,
        ?string $curp,
        string $name,
        string $surnames,
        array $docIndex,
    ): int {
        $keysToTry = array_filter([
            $curp,
            Str::upper(Str::slug("{$name} {$surnames}", '_')),
            Str::upper(Str::slug("{$name} {$surnames}", '-')),
        ]);

        $matched = null;
        foreach ($keysToTry as $key) {
            if (isset($docIndex[$key])) {
                $matched = $docIndex[$key];
                break;
            }
        }

        if ($matched === null) {
            return 0;
        }

        $attached = 0;
        foreach (['ine', 'comprobante_pago'] as $collection) {
            if (! isset($matched[$collection]) || ! is_file($matched[$collection])) {
                continue;
            }

            $user->addMedia($matched[$collection])
                ->preservingOriginal()
                ->withCustomProperties([
                    'status' => DocumentStatus::Valid->value,
                ])
                ->toMediaCollection($collection);

            $attached++;
        }

        return $attached;
    }

    /**
     * Index extracted documents by folder/prefix key (CURP or slug).
     *
     * @return array<string, array{ine?: string, comprobante_pago?: string}>
     */
    protected function indexExtractedDocuments(string $extractedDir): array
    {
        if (! is_dir($extractedDir)) {
            return [];
        }

        $index = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractedDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (! in_array($ext, self::ALLOWED_DOC_EXTENSIONS, true)) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($extractedDir) + 1));
            $parts = explode('/', $relative);

            // Expected structure: {KEY}/{ine|comprobante_pago}.{ext}
            // Also supports optional top-level wrapper folder: {WRAPPER}/{KEY}/{ine|comprobante_pago}.{ext}
            $baseName = strtolower(pathinfo($file->getFilename(), PATHINFO_FILENAME));
            $docType = match (true) {
                $baseName === 'ine' || str_starts_with($baseName, 'ine_') => 'ine',
                $baseName === 'comprobante_pago' || str_starts_with($baseName, 'comprobante_pago_') || $baseName === 'recibo' => 'comprobante_pago',
                default => null,
            };

            if ($docType === null || count($parts) < 2) {
                continue;
            }

            $key = strtoupper($parts[count($parts) - 2]);
            $index[$key][$docType] = $file->getPathname();
        }

        unset($file, $iterator);

        return $index;
    }

    /**
     * Extract a ZIP archive safely, blocking path traversal (Zip Slip).
     */
    protected function extractZipSafely(string $zipPath, string $destination): int
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('No se pudo abrir el archivo ZIP: '.basename($zipPath));
        }

        File::ensureDirectoryExists($destination);
        $count = 0;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                if ($entryName === false) {
                    continue;
                }

                $normalized = str_replace('\\', '/', $entryName);
                if (str_contains($normalized, '../') || str_starts_with($normalized, '/') || str_contains($normalized, "\0")) {
                    continue;
                }

                if (str_ends_with($normalized, '/')) {
                    File::ensureDirectoryExists($destination.'/'.rtrim($normalized, '/'));

                    continue;
                }

                $ext = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
                if (! in_array($ext, self::ALLOWED_DOC_EXTENSIONS, true)) {
                    continue;
                }

                $targetPath = $destination.'/'.$normalized;
                File::ensureDirectoryExists(dirname($targetPath));

                $stream = $zip->getStream($entryName);
                if ($stream === false) {
                    continue;
                }

                $out = fopen($targetPath, 'wb');
                if ($out !== false) {
                    stream_copy_to_stream($stream, $out);
                    fclose($out);
                    $count++;
                }
                fclose($stream);
            }
        } finally {
            $zip->close();
        }

        return $count;
    }

    protected function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header), 'UTF-8');

        return strtr($header, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);
    }

    protected function sanitizeFilename(string $filename): string
    {
        $base = basename(str_replace('\\', '/', $filename));

        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $base) ?: 'upload.bin';
    }

    protected function detectFileKind(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'zip' ? 'zip' : 'csv';
    }

    protected function nullableTrim(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    protected function nullableUpper(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? mb_strtoupper($trimmed, 'UTF-8') : null;
    }

    protected function resolveRole(string $rawRole): UserRole
    {
        $normalized = mb_strtolower(trim($rawRole), 'UTF-8');

        return match ($normalized) {
            'admin', 'administrador' => UserRole::Admin,
            'lider', 'líder' => UserRole::Lider,
            default => UserRole::Agremiado,
        };
    }

    protected function parseDate(?string $rawDate): ?string
    {
        $value = trim((string) $rawDate);
        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y H:i', 'd/m/Y H:i:s', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $value);
                if ($dt !== false) {
                    return $dt->format('Y-m-d');
                }
            } catch (Throwable) {
                // Try next format
            }
        }

        return null;
    }

    protected function generatePlaceholderEmail(?string $curp, string $name, string $surnames): string
    {
        $localPart = $curp !== null
            ? strtolower($curp)
            : Str::slug("{$name} {$surnames}", '.').'.'.substr(md5("{$name}|{$surnames}"), 0, 6);

        $candidate = "{$localPart}@pendiente.siut.local";
        $suffix = 1;
        while (User::withTrashed()->where('email', $candidate)->exists()) {
            $candidate = "{$localPart}.{$suffix}@pendiente.siut.local";
            $suffix++;
        }

        return $candidate;
    }
}
