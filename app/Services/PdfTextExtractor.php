<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

class PdfTextExtractor
{
    public function extract(string $path): string
    {
        // Primary: robust parser (handles font encodings / ToUnicode CMaps)
        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();
            if (is_string($text) && trim($text) !== '') {
                return $this->normalize($text);
            }
        } catch (\Throwable $e) {
            // fall through to basic extractor
        }

        return $this->normalize($this->basicExtract($path));
    }

    protected function basicExtract(string $path): string
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return '';
        }

        $text = '';

        preg_match_all('/stream\r?\n(.*?)endstream/s', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $stream = $match[1];
            $decoded = @gzuncompress($stream);
            if ($decoded === false) {
                $decoded = @gzinflate($stream);
            }
            if ($decoded === false) {
                $decoded = $stream;
            }
            $text .= $this->extractTextFromContent($decoded)."\n";
        }

        if (trim($text) === '') {
            $text = $this->extractTextFromContent($content);
        }

        return $text;
    }

    protected function extractTextFromContent(string $content): string
    {
        $text = '';

        preg_match_all('/\((?:\\\\.|[^()\\\\])*\)\s*Tj/s', $content, $tj);
        foreach ($tj[0] as $m) {
            preg_match('/\((.*)\)\s*Tj/s', $m, $inner);
            $val = $inner[1] ?? '';
            $text .= $this->decodePdfString($val).' ';
        }

        preg_match_all('/\[(.*?)\]\s*TJ/s', $content, $tjArrays);
        foreach ($tjArrays[1] as $arr) {
            preg_match_all('/\((?:\\\\.|[^()\\\\])*\)/s', $arr, $strings);
            foreach ($strings[0] as $s) {
                $inner = substr($s, 1, -1);
                $text .= $this->decodePdfString($inner);
            }
            $text .= ' ';
        }

        return $text;
    }

    protected function decodePdfString(string $s): string
    {
        $s = str_replace(['\\n', '\\r', '\\t'], ["\n", "\r", "\t"], $s);
        $s = preg_replace_callback('/\\\\([0-7]{1,3})/', fn ($m) => chr(octdec($m[1])), $s);
        $s = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $s);

        return $s;
    }

    protected function normalize(string $text): string
    {
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
