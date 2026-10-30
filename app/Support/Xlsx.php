<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal .xlsx reader (values only; cached results of formulas). Enough for the
 * stock position reports, without pulling in a spreadsheet library.
 */
class Xlsx
{
    /**
     * @return array<string, list<list<string|float|null>>> sheet name => rows of cells (0-based columns)
     */
    public static function read(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('This file is not a valid Excel (.xlsx) file.');
        }

        try {
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                foreach (self::xml($xml)->si as $si) {
                    // Plain <t>, or rich text split into several <r><t> runs
                    $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]') ?: []));
                }
            }

            $workbook = self::xml($zip->getFromName('xl/workbook.xml') ?: throw new RuntimeException('Workbook part missing.'));
            $rels = self::xml($zip->getFromName('xl/_rels/workbook.xml.rels') ?: throw new RuntimeException('Workbook relations missing.'));
            $targets = [];
            foreach ($rels->Relationship as $rel) {
                $targets[(string) $rel['Id']] = ltrim(preg_replace('#^/?xl/#', '', (string) $rel['Target']), '/');
            }

            $sheets = [];
            foreach ($workbook->sheets->sheet as $sheet) {
                $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                $part = $zip->getFromName('xl/'.($targets[$rid] ?? ''));
                if ($part === false) {
                    continue;
                }
                $sheets[(string) $sheet['name']] = self::rows(self::xml($part), $shared);
            }

            return $sheets;
        } finally {
            $zip->close();
        }
    }

    /** @return list<list<string|float|null>> */
    private static function rows(SimpleXMLElement $sheet, array $shared): array
    {
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::column(preg_replace('/\d+/', '', (string) $c['r']));
                $type = (string) $c['t'];
                $value = match (true) {
                    $type === 's' => $shared[(int) $c->v] ?? null,
                    $type === 'inlineStr' => (string) $c->is->t,
                    $type === 'str', $type === 'e' => (string) $c->v,
                    $type === 'b' => (string) $c->v,
                    isset($c->v) && $c->v !== '' => (float) $c->v,
                    default => null,
                };
                $cells[$col] = $value;
            }
            $index = (int) $row['r'] - 1;
            $line = [];
            for ($i = 0, $max = $cells ? max(array_keys($cells)) : -1; $i <= $max; $i++) {
                $line[] = $cells[$i] ?? null;
            }
            $rows[$index] = $line;
        }
        ksort($rows);

        return array_values($rows);
    }

    private static function column(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    private static function xml(string $xml): SimpleXMLElement
    {
        $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        if ($doc === false) {
            throw new RuntimeException('This Excel file could not be read.');
        }

        return $doc;
    }
}
