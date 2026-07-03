<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

/**
 * RF-0042 - Exportação PDF.
 *
 * Gerador de PDF minimalista, escrito à mão, sem dependências externas
 * (não há Composer disponível neste projeto — ver README). Suporta apenas
 * texto monoespaçado (Courier) em várias páginas A4, suficiente para
 * relatórios tabulares. Para PDFs com layout rico (logotipos, gráficos)
 * valeria a pena instalar dompdf/mpdf via Composer no futuro.
 *
 * Referência do formato: PDF 1.4 (ISO 32000), estrutura mínima
 * Catalog → Pages → Page → Content stream, fonte standard-14 (sem embutir).
 */
final class SimplePdfWriter
{
    // A4 paisagem (842x595 pt) com margens de ~20pt; a 13pt/linha cabem
    // confortavelmente ~38 linhas de corpo por página (deixando espaço
    // para o título nas primeiras 2 linhas da primeira página).
    private const LINES_PER_PAGE = 38;
    private const FONT_SIZE = 9;
    private const LINE_HEIGHT = 13;
    private const TOP_Y = 575;
    private const LEFT_X = 30;
    private const PAGE_WIDTH = 842;
    private const PAGE_HEIGHT = 595;

    /** @var string[] */
    private array $lines = [];

    public function __construct(private readonly string $title)
    {
    }

    public function addLine(string $line): void
    {
        $this->lines[] = $line;
    }

    public function output(): string
    {
        $pages = array_chunk($this->lines, self::LINES_PER_PAGE) ?: [[]];
        $pageCount = count($pages);

        // Numeração de objetos definida à partida (ver docblock da classe).
        $catalogId = 1;
        $pagesId = 2;
        $firstPageId = 3;
        $firstContentId = $firstPageId + $pageCount;
        $fontId = $firstContentId + $pageCount;

        $buffer = "%PDF-1.4\n";
        $offsets = [];

        $write = function (int $id, string $body) use (&$buffer, &$offsets): void {
            $offsets[$id] = strlen($buffer);
            $buffer .= "{$id} 0 obj\n{$body}\nendobj\n";
        };

        $kids = implode(' ', array_map(static fn (int $i) => ($firstPageId + $i) . ' 0 R', range(0, $pageCount - 1)));
        $write($catalogId, "<< /Type /Catalog /Pages {$pagesId} 0 R >>");
        $write($pagesId, "<< /Type /Pages /Kids [{$kids}] /Count {$pageCount} >>");

        foreach ($pages as $index => $pageLines) {
            $pageId = $firstPageId + $index;
            $contentId = $firstContentId + $index;

            $write($pageId, "<< /Type /Page /Parent {$pagesId} 0 R "
                . '/MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] '
                . "/Resources << /Font << /F1 {$fontId} 0 R >> >> "
                . "/Contents {$contentId} 0 R >>");

            $stream = $this->buildPageStream($pageLines, $index === 0);
            $write($contentId, "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream");
        }

        $write($fontId, '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>');

        $xrefOffset = strlen($buffer);
        $totalObjects = $fontId + 1;

        $buffer .= "xref\n0 {$totalObjects}\n";
        $buffer .= "0000000000 65535 f \n";
        for ($id = 1; $id < $totalObjects; $id++) {
            $buffer .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        $buffer .= "trailer\n<< /Size {$totalObjects} /Root {$catalogId} 0 R >>\n";
        $buffer .= "startxref\n{$xrefOffset}\n%%EOF";

        return $buffer;
    }

    /**
     * @param string[] $pageLines
     */
    private function buildPageStream(array $pageLines, bool $isFirstPage): string
    {
        $stream = "BT\n/F1 " . self::FONT_SIZE . " Tf\n";
        $y = self::TOP_Y;

        if ($isFirstPage) {
            $stream .= '1 0 0 1 ' . self::LEFT_X . " {$y} Tm\n(" . $this->escape($this->title) . ") Tj\n";
            $y -= self::LINE_HEIGHT * 2;
        }

        foreach ($pageLines as $line) {
            $stream .= '1 0 0 1 ' . self::LEFT_X . " {$y} Tm\n(" . $this->escape($line) . ") Tj\n";
            $y -= self::LINE_HEIGHT;
        }

        $stream .= 'ET';

        return $stream;
    }

    private function escape(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        $text = $converted !== false ? $converted : preg_replace('/[^\x20-\x7E]/', '?', $text);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $text);
    }
}
