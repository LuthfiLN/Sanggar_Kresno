<?php

declare(strict_types=1);

final class SimplePdf
{
    /** @var list<string> */
    private array $pages = [];

    /** @var list<string> */
    private array $operations = [];

    private float $y = 800.0;

    public function __construct(private readonly string $title = 'Laporan')
    {
        $this->newPage();
    }

    public function heading(string $text): void
    {
        $this->ensureSpace(34);
        $this->text(40, $this->y, 16, $text);
        $this->y -= 24;
    }

    public function paragraph(string $text): void
    {
        $this->ensureSpace(18);
        $this->text(40, $this->y, 10, $text);
        $this->y -= 16;
    }

    public function gap(float $height = 10): void
    {
        $this->y -= $height;
    }

    /** @param list<string> $cells @param list<int> $widths */
    public function row(array $cells, array $widths, bool $header = false): void
    {
        $this->ensureSpace(22);
        $fontSize = $header ? 8 : 8;
        $x = 40.0;
        $top = $this->y + 12;
        foreach ($cells as $index => $cell) {
            $width = (float) ($widths[$index] ?? 70);
            $this->operations[] = sprintf('%.2F %.2F %.2F 18 re S', $x, $this->y - 4, $width);
            $this->text($x + 3, $this->y + 2, $fontSize, $this->fit((string) $cell, $width));
            $x += $width;
        }
        $this->operations[] = sprintf('0 %.2F m', $top);
        $this->y -= 18;
    }

    public function output(string $filename): never
    {
        $this->pages[] = implode("\n", $this->operations);
        $this->operations = [];

        $pageObjects = [];
        $contentObjects = [];
        $objectNumber = 3;
        foreach ($this->pages as $content) {
            $stream = "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
            $contentObjects[$objectNumber] = $stream;
            $pageObjects[] = $objectNumber + 1;
            $objectNumber += 2;
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = implode(' ', array_map(static fn (int $id): string => ($id) . ' 0 R', $pageObjects));
        $objects[2] = '<< /Type /Pages /Count ' . count($pageObjects) . ' /Kids [' . $kids . '] >>';
        foreach ($contentObjects as $id => $stream) {
            $objects[$id] = $stream;
            $pageId = $id + 1;
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents ' . $id . ' 0 R /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> >>';
        }
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id < $count; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $safeTitle = $this->escape($this->title);
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R /Info << /Title ({$safeTitle}) >> >>\nstartxref\n{$xref}\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: no-store');
        echo $pdf;
        exit;
    }

    private function newPage(): void
    {
        $this->operations = [];
        $this->y = 800.0;
    }

    private function ensureSpace(float $height): void
    {
        if ($this->y - $height < 40) {
            $this->pages[] = implode("\n", $this->operations);
            $this->operations = [];
            $this->y = 800.0;
        }
    }

    private function text(float $x, float $y, int $size, string $value): void
    {
        $this->operations[] = sprintf('BT /F1 %d Tf %.2F %.2F Td (%s) Tj ET', $size, $x, $y, $this->escape($value));
    }

    private function fit(string $value, float $width): string
    {
        $max = max(8, (int) floor(($width - 8) / 4.4));
        if (mb_strlen($value) <= $max) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $max - 1)) . '...';
    }

    private function escape(string $value): string
    {
        $ascii = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        $ascii = $ascii === false ? $value : $ascii;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }
}
