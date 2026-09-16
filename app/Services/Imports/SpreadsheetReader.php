<?php

declare(strict_types=1);

namespace App\Services\Imports;

use InvalidArgumentException;
use ZipArchive;

/**
 * Reads the first worksheet of a .xlsx or a .csv into [headers, list of row arrays].
 * No Composer packages: ZipArchive + SimpleXML for Office Open XML.
 */
final class SpreadsheetReader
{
    /**
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    public function read(string $path, string $originalName = ''): array
    {
        $ext = strtolower(pathinfo($originalName !== '' ? $originalName : $path, PATHINFO_EXTENSION));

        return match ($ext) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx' => $this->readXlsx($path),
            'xls' => throw new InvalidArgumentException('Legacy .xls is not supported. Save the file as .xlsx or .csv.'),
            default => throw new InvalidArgumentException('Unsupported file type. Upload .xlsx or .csv.'),
        };
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('Could not read the uploaded file.');
        }

        $first = fgets($handle);
        if ($first === false) {
            fclose($handle);
            throw new InvalidArgumentException('The spreadsheet is empty.');
        }

        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = array_map(static fn ($v) => trim((string) $v), $row);
        }
        fclose($handle);

        return $this->splitHeader($rows);
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('The .xlsx file could not be opened.');
        }

        $strings = $this->sharedStrings($zip->getFromName('xl/sharedStrings.xml') ?: '');
        $sheetPath = $this->firstSheetPath($zip);
        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        if (! is_string($sheetXml) || $sheetXml === '') {
            throw new InvalidArgumentException('The workbook has no worksheet.');
        }

        $sheet = simplexml_load_string($sheetXml);
        if ($sheet === false) {
            throw new InvalidArgumentException('The worksheet XML is invalid.');
        }

        $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xmlRows = $sheet->xpath('//m:sheetData/m:row') ?: [];

        $grid = [];
        foreach ($xmlRows as $xmlRow) {
            $rowIndex = ((int) $xmlRow['r']) - 1;
            if ($rowIndex < 0) {
                $rowIndex = count($grid);
            }
            $cells = [];
            foreach ($xmlRow->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main') as $c) {
                if ($c->getName() !== 'c') {
                    continue;
                }
                $ref = (string) $c['r'];
                $col = $this->columnIndex($ref);
                $cells[$col] = $this->cellValue($c, $strings);
            }
            if ($cells === []) {
                continue;
            }
            $width = max(array_keys($cells)) + 1;
            $row = array_fill(0, $width, '');
            foreach ($cells as $col => $value) {
                $row[$col] = $value;
            }
            $grid[$rowIndex] = $row;
        }

        ksort($grid);

        return $this->splitHeader(array_values($grid));
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    private function splitHeader(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn (array $row) => implode('', $row) !== ''));
        if ($rows === []) {
            throw new InvalidArgumentException('The spreadsheet is empty.');
        }

        $headers = array_map(static fn ($h) => trim((string) $h), $rows[0]);
        $body = array_slice($rows, 1);

        return ['headers' => $headers, 'rows' => $body];
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $sst = simplexml_load_string($xml);
        if ($sst === false) {
            return [];
        }

        $sst->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $items = $sst->xpath('//m:si') ?: [];
        $out = [];
        foreach ($items as $si) {
            $texts = $si->xpath('.//m:t') ?: [];
            $out[] = trim(implode('', array_map(static fn ($t) => (string) $t, $texts)));
        }

        return $out;
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        if (! is_string($workbook) || $workbook === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        $xml = simplexml_load_string($workbook);
        if ($xml === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $sheets = $xml->xpath('//m:sheets/m:sheet') ?: [];
        $rid = '';
        if ($sheets !== []) {
            $rAttrs = $sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $rid = $rAttrs !== null ? (string) $rAttrs['id'] : '';
        }

        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($rid !== '' && is_string($rels)) {
            $relXml = simplexml_load_string($rels);
            if ($relXml !== false) {
                foreach ($relXml->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = ltrim((string) $rel['Target'], '/');
                        return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                    }
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function columnIndex(string $cellRef): int
    {
        if (! preg_match('/^([A-Z]+)/i', $cellRef, $m)) {
            return 0;
        }

        $n = 0;
        foreach (str_split(strtoupper($m[1])) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    /**
     * @param  list<string>  $strings
     */
    private function cellValue(\SimpleXMLElement $cell, array $strings): string
    {
        $type = (string) $cell['t'];
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

        if ($type === 's') {
            $idx = (int) $cell->children($ns)->v;

            return $strings[$idx] ?? '';
        }

        if ($type === 'inlineStr') {
            $texts = $cell->xpath('.//*[local-name()="t"]') ?: [];

            return trim(implode('', array_map(static fn ($t) => (string) $t, $texts)));
        }

        if ($type === 'b') {
            return ((string) $cell->children($ns)->v) === '1' ? '1' : '0';
        }

        $raw = trim((string) $cell->children($ns)->v);

        return $raw;
    }
}
