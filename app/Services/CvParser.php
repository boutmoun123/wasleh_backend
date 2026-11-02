<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use Smalot\PdfParser\Parser;
use ZipArchive;

class CvParser
{
    public function __construct(private AtsCvAnalyzer $atsAnalyzer) {}

    public function parse(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $raw = match ($ext) {
            'pdf' => $this->pdf($file->getRealPath()),
            'docx' => $this->docx($file->getRealPath()),
            default => throw new RuntimeException('Only PDF and DOCX files are supported.'),
        };
        $raw = trim(preg_replace('/[\t ]+/u', ' ', $raw));
        if ($raw === '') {
            throw new RuntimeException('No selectable text found. Scanned images require OCR and manual entry.');
        }
        preg_match('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/u', $raw, $email);
        preg_match('/(?<!\d)(?:\+?963|0)?9\d{8}(?!\d)/u', preg_replace('/[\s\-()]/u', '', $raw), $phone);
        $skills = [];
        foreach (config('wasla.skill_dictionary', []) as $name => $terms) {
            foreach ($terms as $term) {
                $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/iu';
                if (preg_match($pattern, $raw)) {
                    $skills[] = $name;
                    break;
                }
            }
        }
        $emailAddress = $email[0] ?? null;
        $phoneNumber = $phone[0] ?? null;

        return [
            'email' => $emailAddress,
            'phone' => $phoneNumber,
            'skills' => $skills,
            'raw_text' => mb_substr($raw, 0, 30000),
            'ats' => $this->atsAnalyzer->analyzeText($raw, $emailAddress, $phoneNumber, $skills),
            'requires_review' => true,
        ];
    }

    private function pdf(string $path): string
    {
        if (! class_exists(Parser::class)) {
            throw new RuntimeException('Install smalot/pdfparser with Composer.');
        }

        return (new Parser)->parseFile($path)->getText();
    }

    private function docx(string $path): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP extension ext-zip is required.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Invalid DOCX file.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! $xml || strlen($xml) > 15 * 1024 * 1024) {
            throw new RuntimeException('Invalid or oversized DOCX content.');
        }
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            throw new RuntimeException('Malformed DOCX XML.');
        }
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paragraphs = [];
        foreach ($xpath->query('//w:p') as $node) {
            $content = '';
            foreach ($xpath->query('.//w:t', $node) as $word) {
                $content .= $word->textContent;
            }
            $paragraphs[] = $content;
        }

        return implode("\n",$paragraphs);
    }
}
