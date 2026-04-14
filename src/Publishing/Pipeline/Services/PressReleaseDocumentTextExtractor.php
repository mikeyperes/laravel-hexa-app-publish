<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use hexa_package_upload_portal\Upload\Media\Models\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class PressReleaseDocumentTextExtractor
{
    public function extract(UploadedFile $file): array
    {
        $path = Storage::disk($file->disk)->path($file->path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['doc', 'docx', 'pdf'], true)) {
            $textutil = $this->extractWithTextutil($path);
            if (!empty($textutil['text'])) {
                return $textutil;
            }
        }

        return match ($extension) {
            'docx' => $this->extractDocx($path),
            'pdf' => $this->extractPdfFallback($path),
            default => [
                'success' => false,
                'text' => '',
                'method' => 'unsupported',
                'message' => 'Unsupported document format: ' . $extension,
            ],
        };
    }

    private function extractWithTextutil(string $path): array
    {
        if (!is_executable('/usr/bin/textutil')) {
            return [
                'success' => false,
                'text' => '',
                'method' => 'textutil',
                'message' => 'textutil not available',
            ];
        }

        $process = new Process(['/usr/bin/textutil', '-convert', 'txt', '-stdout', $path]);
        $process->setTimeout(30);
        $process->run();

        $text = trim($process->getOutput());

        return [
            'success' => $process->isSuccessful() && $text !== '',
            'text' => $text,
            'method' => 'textutil',
            'message' => $process->isSuccessful()
                ? ($text !== '' ? 'Extracted with textutil.' : 'textutil returned no text.')
                : (trim($process->getErrorOutput()) ?: 'textutil failed.'),
        ];
    }

    private function extractDocx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [
                'success' => false,
                'text' => '',
                'method' => 'docx-zip',
                'message' => 'Unable to open DOCX archive.',
            ];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$xml) {
            return [
                'success' => false,
                'text' => '',
                'method' => 'docx-zip',
                'message' => 'DOCX document.xml missing.',
            ];
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags($xml)));

        return [
            'success' => $text !== '',
            'text' => $text,
            'method' => 'docx-zip',
            'message' => $text !== '' ? 'Extracted from DOCX XML.' : 'DOCX XML contained no readable text.',
        ];
    }

    private function extractPdfFallback(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [
                'success' => false,
                'text' => '',
                'method' => 'pdf-fallback',
                'message' => 'Unable to read PDF file.',
            ];
        }

        preg_match_all('/\(([^()]*)\)/', $raw, $matches);
        $chunks = array_map(static fn ($chunk) => trim((string) $chunk), $matches[1] ?? []);
        $text = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($chunks, fn ($chunk) => $chunk !== ''))));
        $text = Str::limit($text, 50000, '');

        return [
            'success' => $text !== '',
            'text' => $text,
            'method' => 'pdf-fallback',
            'message' => $text !== '' ? 'Extracted readable PDF text using fallback parser.' : 'Could not extract readable text from PDF.',
        ];
    }
}
