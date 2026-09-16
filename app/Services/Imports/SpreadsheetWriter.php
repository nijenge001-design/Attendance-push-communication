<?php

declare(strict_types=1);

namespace App\Services\Imports;

use ZipArchive;

/**
 * Writes a simple .xlsx (inline strings) or .csv. Used for import templates.
 */
final class SpreadsheetWriter
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function csv(array $headers, array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);

        return $csv;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function xlsx(array $headers, array $rows): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($tmp === false) {
            throw new \RuntimeException('Unable to allocate a temp file for the template.');
        }

        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML);

        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);

        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML);

        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Import" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML);

        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($headers, $rows));
        $zip->close();

        $binary = file_get_contents($tmp) ?: '';
        @unlink($tmp);

        return $binary;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    private function sheetXml(array $headers, array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        $xml .= $this->rowXml(1, $headers);
        $r = 2;
        foreach ($rows as $row) {
            $xml .= $this->rowXml($r, $row);
            $r++;
        }

        return $xml.'</sheetData></worksheet>';
    }

    /**
     * @param  list<string|int|float|null>  $values
     */
    private function rowXml(int $rowNumber, array $values): string
    {
        $xml = '<row r="'.$rowNumber.'">';
        foreach (array_values($values) as $i => $value) {
            $col = $this->columnLetter($i);
            $ref = $col.$rowNumber;
            $text = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.$text.'</t></is></c>';
        }

        return $xml.'</row>';
    }

    private function columnLetter(int $index): string
    {
        $index++;
        $letter = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }
}
