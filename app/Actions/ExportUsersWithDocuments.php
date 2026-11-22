<?php

namespace App\Actions;

use App\Enums\UserDocumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ExportUsersWithDocuments
{
    /**
     * Build a temporary ZIP archive containing the UTF-8 CSV directory and all available user documents.
     *
     * @param  Builder<User>  $query
     */
    public function execute(Builder $query): string
    {
        $exportDir = storage_path('app/private/exports');
        File::ensureDirectoryExists($exportDir);

        $zipPath = $exportDir.'/padron-usuarios-'.Str::uuid().'.zip';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo ZIP de exportación.');
        }

        $csvHandle = fopen('php://temp', 'r+');
        if ($csvHandle === false) {
            $zip->close();
            throw new RuntimeException('No se pudo inicializar el buffer CSV.');
        }

        // UTF-8 BOM for Excel compatibility
        fwrite($csvHandle, "\xEF\xBB\xBF");
        fputcsv($csvHandle, [
            'nombre',
            'apellidos',
            'direccion',
            'telefono',
            'email',
            'categoria',
            'nss',
            'curp',
            'fecha_nacimiento',
            'fecha_ingreso',
            'rol',
            'activo',
            'documentacion',
        ], ',', '"', '\\');

        $usedFolders = [];

        try {
            (clone $query)
                ->with('media')
                ->orderBy('name')
                ->orderBy('surnames')
                ->chunk(100, function (Collection $users) use ($zip, $csvHandle, &$usedFolders): void {
                    foreach ($users as $user) {
                        /** @var User $user */
                        fputcsv($csvHandle, [
                            $user->name,
                            $user->surnames ?? '',
                            $user->address ?? '',
                            $user->phone ?? '',
                            $user->email,
                            $user->category ?? '',
                            $user->nss ?? '',
                            $user->curp ?? '',
                            $user->birth_date?->format('Y-m-d') ?? '',
                            $user->hiring_date?->format('Y-m-d') ?? '',
                            $user->role?->value ?? '',
                            $user->is_active ? 'si' : 'no',
                            $user->getOverallDocumentStatus()->value,
                        ], ',', '"', '\\');

                        $folder = $this->resolveUserFolder($user, $usedFolders);

                        foreach (UserDocumentType::cases() as $docType) {
                            $media = $user->getDocumentMedia($docType);
                            if ($media === null) {
                                continue;
                            }

                            $filePath = $media->getPath();
                            if (! is_file($filePath)) {
                                continue;
                            }

                            $ext = strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION)) ?: 'pdf';
                            $zip->addFile($filePath, "{$folder}/{$docType->value}.{$ext}");
                        }

                        $ineBackMedia = $user->getIneBackMedia();
                        if ($ineBackMedia !== null && is_file($ineBackMedia->getPath())) {
                            $ext = strtolower(pathinfo($ineBackMedia->file_name, PATHINFO_EXTENSION)) ?: 'pdf';
                            $zip->addFile($ineBackMedia->getPath(), "{$folder}/".User::INE_BACK_COLLECTION.".{$ext}");
                        }
                    }
                });

            rewind($csvHandle);
            $csvContent = stream_get_contents($csvHandle);
            $zip->addFromString('padron-usuarios.csv', $csvContent !== false ? $csvContent : '');
        } finally {
            fclose($csvHandle);
            $zip->close();
        }

        return $zipPath;
    }

    /**
     * @param  array<string, int>  $usedFolders
     */
    protected function resolveUserFolder(User $user, array &$usedFolders): string
    {
        $base = filled($user->curp)
            ? mb_strtoupper(trim($user->curp), 'UTF-8')
            : (Str::upper(Str::slug($user->full_name, '_')) ?: "USUARIO_{$user->id}");

        $folder = $base;
        if (isset($usedFolders[$base])) {
            $usedFolders[$base]++;
            $folder = "{$base}_{$usedFolders[$base]}";
        } else {
            $usedFolders[$base] = 1;
        }

        return $folder;
    }
}
