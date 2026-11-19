<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploadService
{
    public function __construct(
        private readonly string $uploadsDirectory,
        private readonly SluggerInterface $slugger
    ) {}

    public function upload(UploadedFile $file, string $subDirectory = ''): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename     = $this->slugger->slug($originalFilename);
        $newFilename      = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $targetDir = $this->uploadsDirectory . ($subDirectory ? '/' . $subDirectory : '');

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        try {
            $file->move($targetDir, $newFilename);
        } catch (FileException $e) {
            throw new \RuntimeException('Erreur lors de l\'upload du fichier : ' . $e->getMessage());
        }

        return ($subDirectory ? $subDirectory . '/' : '') . $newFilename;
    }

    public function delete(string $filename): void
    {
        $path = $this->uploadsDirectory . '/' . $filename;
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
