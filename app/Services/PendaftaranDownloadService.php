<?php

namespace App\Services;

use App\Models\Pendaftaran;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class PendaftaranDownloadService
{
    /**
     * Download all registration documents as a ZIP file
     * 
     * @param Pendaftaran $pendaftaran
     * @return BinaryFileResponse
     * @throws \Exception
     */
    public function downloadAllDocuments(Pendaftaran $pendaftaran): BinaryFileResponse
    {
        // Eager load relationships to prevent N+1 queries
        $pendaftaran->load(['berkasPendaftar.berkasWajib', 'mahasiswa.user']);

        // Validate that there are documents to download
        if ($pendaftaran->berkasPendaftar->isEmpty()) {
            throw new \Exception('Tidak ada berkas yang tersedia untuk didownload.');
        }

        // Create ZIP filename: {nama_mahasiswa}_{nim}.zip
        $mahasiswa = $pendaftaran->mahasiswa;
        $nim = $mahasiswa->user->nim ?? 'unknown';
        $nama = $this->sanitizeFilename($mahasiswa->nama ?? 'unknown');
        $zipFilename = "{$nim}_{$nama}.zip";

        // Create temporary directory if not exists
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Create temporary ZIP file path
        $zipPath = $tempDir . '/' . Str::uuid() . '.zip';

        // Create ZIP archive
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Gagal membuat file ZIP.');
        }

        $filesAdded = 0;

        // Add each document to ZIP
        foreach ($pendaftaran->berkasPendaftar as $berkas) {
            $filePath = storage_path('app/public/' . $berkas->file_path);

            // Check if file exists
            if (!file_exists($filePath)) {
                continue;
            }

            // Get file extension
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);

            // Create sanitized filename for the document
            $berkasName = $this->sanitizeFilename($berkas->berkasWajib->nama_berkas ?? 'document');
            $documentFilename = "{$berkasName}.{$extension}";

            // Handle duplicate filenames by appending counter
            $counter = 1;
            $originalFilename = $documentFilename;
            while ($zip->locateName($documentFilename) !== false) {
                $documentFilename = "{$berkasName}_{$counter}.{$extension}";
                $counter++;
            }

            // Add file to ZIP
            $zip->addFile($filePath, $documentFilename);
            $filesAdded++;
        }

        $zip->close();

        // Check if any files were added
        if ($filesAdded === 0) {
            unlink($zipPath);
            throw new \Exception('Tidak ada berkas yang valid untuk didownload.');
        }

        // Return binary file response with automatic deletion
        return response()->download($zipPath, $zipFilename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Sanitize filename by removing special characters
     * 
     * @param string $filename
     * @return string
     */
    private function sanitizeFilename(string $filename): string
    {
        // Replace spaces with underscores
        $filename = str_replace(' ', '_', $filename);

        // Remove special characters except underscores, hyphens, and alphanumeric
        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '', $filename);

        // Remove multiple consecutive underscores
        $filename = preg_replace('/_+/', '_', $filename);

        // Trim underscores from start and end
        $filename = trim($filename, '_');

        return $filename ?: 'document';
    }
}
