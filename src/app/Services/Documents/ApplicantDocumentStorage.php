<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ApplicantDocumentStorage
{
    public const PRIVATE_DISK = 'private';

    public const LEGACY_PUBLIC_DISK = 'public';

    public function store(UploadedFile $file, int $jobSeekerId, string $directory): string
    {
        if (! $this->ensurePrivateRoot()) {
            throw new RuntimeException('The private applicant storage root is not secure.');
        }

        $extension = strtolower((string) ($file->guessExtension() ?: $file->extension()));
        $filename = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');
        $path = sprintf(
            'applicants/%d/%s/%s',
            $jobSeekerId,
            trim($directory, '/'),
            $filename,
        );

        $stored = Storage::disk(self::PRIVATE_DISK)->putFileAs(
            dirname($path),
            $file,
            basename($path),
            [
                'visibility' => 'private',
                'directory_visibility' => 'private',
            ],
        );
        $visibilityEnforced = $this->enforcePrivateVisibility($path);

        if (
            $stored !== $path
            || ! Storage::disk(self::PRIVATE_DISK)->exists($path)
            || ! $visibilityEnforced
        ) {
            Storage::disk(self::PRIVATE_DISK)->delete($path);
            throw new RuntimeException('The applicant document could not be stored securely.');
        }

        return $path;
    }

    public function copyPrivate(string $source, int $jobSeekerId, string $directory): string
    {
        $this->assertSafePath($source);

        if (! $this->ensurePrivateRoot()) {
            throw new RuntimeException('The private applicant storage root is not secure.');
        }

        $sourceDisk = $this->diskContaining($source);

        if ($sourceDisk === null) {
            throw new RuntimeException('The source applicant document is unavailable.');
        }

        $extension = strtolower((string) pathinfo($source, PATHINFO_EXTENSION));
        $destination = sprintf(
            'applicants/%d/%s/%s%s',
            $jobSeekerId,
            trim($directory, '/'),
            (string) Str::uuid(),
            $extension !== '' ? '.'.$extension : '',
        );

        $readStream = Storage::disk($sourceDisk)->readStream($source);

        if ($readStream === null) {
            throw new RuntimeException('The source applicant document could not be read.');
        }

        try {
            $written = Storage::disk(self::PRIVATE_DISK)->writeStream($destination, $readStream, [
                'visibility' => 'private',
                'directory_visibility' => 'private',
            ]);
        } finally {
            if (is_resource($readStream)) {
                fclose($readStream);
            }
        }
        $visibilityEnforced = $this->enforcePrivateVisibility($destination);

        if (
            ! $written
            || ! Storage::disk(self::PRIVATE_DISK)->exists($destination)
            || ! $visibilityEnforced
        ) {
            Storage::disk(self::PRIVATE_DISK)->delete($destination);
            throw new RuntimeException('The applicant document copy could not be stored securely.');
        }

        return $destination;
    }

    public function enforcePrivateVisibility(string $path): bool
    {
        $this->assertSafePath($path);

        if (! $this->ensurePrivateRoot()) {
            return false;
        }

        $disk = Storage::disk(self::PRIVATE_DISK);

        if (! $disk->exists($path) || ! $disk->setVisibility($path, 'private')) {
            return false;
        }

        for ($directory = dirname($path); $directory !== '.' && $directory !== ''; $directory = dirname($directory)) {
            if (! $disk->setVisibility($directory, 'private')) {
                return false;
            }
        }

        return $disk->getVisibility($path) === 'private';
    }

    public function ensurePrivateRoot(): bool
    {
        $disk = Storage::disk(self::PRIVATE_DISK);

        try {
            $root = $disk->path('');
        } catch (Throwable) {
            // Non-local adapters enforce privacy through their visibility configuration.
            return true;
        }

        if (! is_dir($root) && ! @mkdir($root, 0700, true) && ! is_dir($root)) {
            return false;
        }

        if (! @chmod($root, 0700)) {
            return false;
        }

        $permissions = @fileperms($root);

        return $permissions !== false && ($permissions & 0777) === 0700;
    }

    public function verifiedExtension(string $path): string
    {
        $disk = $this->diskContaining($path);

        if ($disk === null) {
            return '';
        }

        try {
            $mimeType = strtolower((string) Storage::disk($disk)->mimeType($path));
        } catch (Throwable) {
            $mimeType = '';
        }

        $extension = match ($mimeType) {
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => '',
        };

        if ($extension !== '') {
            return $extension;
        }

        $storedExtension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return in_array($storedExtension, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'], true)
            ? ($storedExtension === 'jpeg' ? 'jpg' : $storedExtension)
            : '';
    }

    public function delete(?string $path): bool
    {
        if (! filled($path)) {
            return true;
        }

        $this->assertSafePath($path);
        $disk = $this->diskContaining($path);

        return $disk === null || Storage::disk($disk)->delete($path);
    }

    public function diskContaining(string $path): ?string
    {
        $this->assertSafePath($path);

        if (Storage::disk(self::PRIVATE_DISK)->exists($path)) {
            return self::PRIVATE_DISK;
        }

        if (Storage::disk(self::LEGACY_PUBLIC_DISK)->exists($path)) {
            return self::LEGACY_PUBLIC_DISK;
        }

        return null;
    }

    public function assertSafePath(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, "\0")) {
            throw new RuntimeException('Unsafe applicant document path.');
        }
    }
}
