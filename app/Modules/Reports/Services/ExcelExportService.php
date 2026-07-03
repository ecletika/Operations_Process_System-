<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

/**
 * RF-0041 - Exportação Excel.
 *
 * Sem Composer disponível neste projeto (ver README), gerar um .xlsx real
 * (OOXML) exigiria uma biblioteca externa como PhpSpreadsheet. Em vez disso
 * usamos uma técnica sem dependências amplamente suportada: uma tabela HTML
 * servida com Content-Type do Excel e extensão .xls — o Excel (e o
 * LibreOffice Calc, Google Sheets) abrem-na diretamente como folha de
 * cálculo. Suficiente para relatórios tabulares; se no futuro for preciso
 * formatação avançada (fórmulas, múltiplas folhas), vale a pena instalar
 * o PhpSpreadsheet via Composer.
 */
final class ExcelExportService
{
    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function render(array $rows, string $title): string
    {
        $html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8">';
        $html .= '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
        $html .= '<x:Name>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</x:Name>';
        $html .= '<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>';
        $html .= '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        $html .= '</head><body><table border="1">';

        if ($rows !== []) {
            $html .= '<tr>';
            foreach (array_keys($rows[0]) as $column) {
                $html .= '<th>' . htmlspecialchars((string) $column, ENT_QUOTES, 'UTF-8') . '</th>';
            }
            $html .= '</tr>';

            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($row as $value) {
                    $html .= '<td>' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</table></body></html>';

        return $html;
    }
}
