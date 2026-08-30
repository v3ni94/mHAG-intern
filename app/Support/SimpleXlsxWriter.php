<?php

namespace App\Support;

/**
 * Minimaler, aber echter XLSX-Writer ohne externe Abhängigkeiten
 * (Abschnitt 108 Masterprompt: Export als PDF, XLSX, CSV).
 *
 * Erzeugt ein gültiges Office-Open-XML-Paket (ZipArchive) mit einem
 * Arbeitsblatt; Zellwerte werden als inlineStr geschrieben, Zahlen als
 * numerische Zellen. Für Listen-Exporte vollkommen ausreichend; keine
 * Formeln, keine Formatierung.
 */
class SimpleXlsxWriter
{
    /**
     * XLSX-Binärinhalt erzeugen.
     *
     * @param  array<int, string>  $headers  Spaltenüberschriften
     * @param  iterable<int, array<int, mixed>>  $rows  Datenzeilen
     */
    public static function make(array $headers, iterable $rows, string $sheetName = 'Export'): string
    {
        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($file === false) {
            throw new \RuntimeException('Temporäre Datei für den XLSX-Export konnte nicht angelegt werden.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($file, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('XLSX-Paket konnte nicht erzeugt werden.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rels());
        $zip->addFromString('xl/workbook.xml', self::workbook($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($headers, $rows));
        $zip->close();

        $content = (string) file_get_contents($file);
        @unlink($file);

        return $content;
    }

    /**
     * Fertige Download-Response (Content-Type für XLSX).
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows, string $sheetName = 'Export'): \Illuminate\Http\Response
    {
        return response(self::make($headers, $rows, $sheetName), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private static function sheet(array $headers, iterable $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        $rowIndex = 1;
        $xml .= self::row($rowIndex, $headers, true);
        foreach ($rows as $row) {
            $rowIndex++;
            $xml .= self::row($rowIndex, array_values((array) $row), false);
        }

        return $xml.'</sheetData></worksheet>';
    }

    private static function row(int $index, array $cells, bool $bold): string
    {
        $xml = '<row r="'.$index.'">';
        foreach (array_values($cells) as $col => $value) {
            $ref = self::columnName($col).$index;
            if (is_int($value) || is_float($value)) {
                $xml .= '<c r="'.$ref.'"'.($bold ? ' s="1"' : '').'><v>'.$value.'</v></c>';
            } else {
                $xml .= '<c r="'.$ref.'" t="inlineStr"'.($bold ? ' s="1"' : '').'><is><t xml:space="preserve">'
                    .htmlspecialchars(self::sanitize((string) ($value ?? '')), ENT_XML1 | ENT_QUOTES, 'UTF-8')
                    .'</t></is></c>';
            }
        }

        return $xml.'</row>';
    }

    /** Steuerzeichen entfernen, die in XML 1.0 unzulässig sind. */
    private static function sanitize(string $value): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
    }

    private static function columnName(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod).$name;
            $index = intdiv($index - $mod - 1, 26);
        }

        return $name;
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private static function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbook(string $sheetName): string
    {
        $name = htmlspecialchars(mb_substr($sheetName, 0, 31), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$name.'" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            .'<cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/></cellXfs>'
            .'</styleSheet>';
    }
}
