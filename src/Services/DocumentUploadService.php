<?php

declare(strict_types=1);

namespace Appsur\FacturasIa\Services;

use Appsur\FacturasIa\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda un PDF de factura en el disco configurado (por defecto el privado 'local',
 * subcarpeta facturas-ia/) y crea el registro Document. Calcula el hash SHA-256 para
 * detectar re-subidas idénticas.
 */
class DocumentUploadService
{
    public function disk(): string
    {
        return (string) config('facturas-ia.disk', 'local');
    }

    /** Acepta un UploadedFile (subida HTTP) o una ruta de archivo en disco. */
    public function store(UploadedFile|string $file, ?int $userId = null): Document
    {
        [$bytes, $name, $size, $mime] = $this->read($file);

        $disk = $this->disk();
        $dir = trim((string) config('facturas-ia.path', 'facturas-ia'), '/');
        $path = $dir.'/'.($userId ?? 'anon').'/'.Str::uuid()->toString().'.pdf';

        Storage::disk($disk)->put($path, $bytes);

        return Document::create([
            'user_id' => $userId,
            'original_name' => $name,
            'disk' => $disk,
            'path' => $path,
            'size' => $size,
            'mime' => $mime,
            'hash' => hash('sha256', $bytes),
        ]);
    }

    public function delete(Document $document): void
    {
        Storage::disk($document->disk)->delete($document->path);
        $document->delete();
    }

    /** SHA-256 del contenido (para detectar re-subidas idénticas antes de extraer). */
    public static function hashFor(UploadedFile|string $file): string
    {
        $bytes = $file instanceof UploadedFile
            ? (string) file_get_contents($file->getRealPath())
            : (string) file_get_contents($file);

        return hash('sha256', $bytes);
    }

    /** Reglas de validación para subir un PDF (25 MB máx). */
    public static function rules(): array
    {
        return ['file', 'mimetypes:application/pdf', 'extensions:pdf', 'max:25600'];
    }

    /** @return array{0:string,1:string,2:int,3:string} [bytes, nombre, tamaño, mime] */
    private function read(UploadedFile|string $file): array
    {
        if ($file instanceof UploadedFile) {
            $bytes = (string) file_get_contents($file->getRealPath());

            return [$bytes, $file->getClientOriginalName(), (int) $file->getSize(), $file->getMimeType() ?: 'application/pdf'];
        }

        $bytes = (string) file_get_contents($file);

        return [$bytes, basename($file), strlen($bytes), 'application/pdf'];
    }
}
