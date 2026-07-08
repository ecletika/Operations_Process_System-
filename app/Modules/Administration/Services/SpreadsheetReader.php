<?php

declare(strict_types=1);

namespace App\Modules\Administration\Services;

use RuntimeException;
use ZipArchive;

/**
 * 📥 Importação — leitor de folhas de cálculo sem dependências externas
 * (Composer/PhpSpreadsheet não estão disponíveis neste projeto — ver README).
 *
 * Suporta:
 *  - .xlsx real (via ZipArchive + SimpleXML, lendo a 1ª folha e resolvendo
 *    o sharedStrings.xml); se a extensão "zip" não estiver ativa no
 *    alojamento, é apanhado com um erro claro a pedir para exportar em CSV.
 *  - .csv (sempre disponível, sem dependências) — alternativa recomendada
 *    quando o .xlsx falhar.
 *
 * Devolve sempre um array de linhas (cada linha = array de células em
 * string), incluindo o cabeçalho na primeira posição.
 */
final class SpreadsheetReader
{
    /** @return array<int, array<int, string>> */
    public static function read(string $filePath, string $originalName): array
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => self::readCsv($filePath),
            'xlsx' => self::readXlsx($filePath),
            default => throw new RuntimeException('Formato não suportado. Envie um ficheiro .xlsx ou .csv.'),
        };
    }

    /** @return array<int, array<int, string>> */
    private static function readCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir o ficheiro CSV.');
        }

        // BOM UTF-8, se existir, não deve entrar na primeira célula.
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $rows = [];
        // Deteta ";" (comum em Excel PT) ou "," automaticamente pela 1ª linha.
        $firstLine = fgets($handle);
        rewind($handle);
        if ($bom === "\xEF\xBB\xBF") {
            fseek($handle, 3);
        }
        $delimiter = substr_count((string) $firstLine, ';') > substr_count((string) $firstLine, ',') ? ';' : ',';

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }
            $rows[] = array_map(static fn ($cell): string => trim((string) $cell), $row);
        }
        fclose($handle);

        return $rows;
    }

    /** @return array<int, array<int, string>> */
    private static function readXlsx(string $filePath): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'Este servidor não tem a extensão PHP "zip" ativa, necessária para ler .xlsx. ' .
                'Exporte a folha como CSV (Ficheiro → Guardar Como → CSV UTF-8) e envie o .csv.'
            );
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('Não foi possível abrir o ficheiro .xlsx (está corrompido ou não é um Excel válido).');
        }

        $sharedStrings = self::readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('Não foi possível ler a primeira folha do Excel.');
        }

        $xml = @simplexml_load_string($sheetXml);
        if ($xml === false) {
            throw new RuntimeException('O ficheiro Excel está num formato inesperado.');
        }

        $rows = [];
        foreach ($xml->sheetData->row as $rowXml) {
            $rowIndex = (int) $rowXml['r'] - 1;
            $cells = [];

            foreach ($rowXml->c as $cellXml) {
                $ref = (string) $cellXml['r']; // ex.: "B3"
                $colLetters = preg_replace('/\d+/', '', $ref) ?? '';
                $colIndex = self::columnLettersToIndex($colLetters);

                $type = (string) $cellXml['t'];
                $value = isset($cellXml->v) ? (string) $cellXml->v : '';

                if ($type === 's') { // shared string
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cellXml->is->t ?? '');
                }

                $cells[$colIndex] = trim($value);
            }

            if ($cells === []) {
                continue;
            }

            $maxCol = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            $rows[$rowIndex] = $line;
        }

        ksort($rows);

        return array_values($rows);
    }

    /** @return array<int, string> */
    private static function readSharedStrings(ZipArchive $zip): array
    {
        $xmlContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlContent === false) {
            return [];
        }

        $xml = @simplexml_load_string($xmlContent);
        if ($xml === false) {
            return [];
        }

        $strings = [];
        foreach ($xml->si as $si) {
            // Texto simples <t> ou texto com formatação corrida <r><t>...
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
            } else {
                $parts = [];
                foreach ($si->r as $run) {
                    $parts[] = (string) $run->t;
                }
                $strings[] = implode('', $parts);
            }
        }

        return $strings;
    }

    private static function columnLettersToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }
}
