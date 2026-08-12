<?php
/**
 * Minimal, dependency-free XLSX writer and reader.
 *
 * Writer emits a valid .xlsx (Office Open XML) file using inline strings and
 * no shared-string table. Reader parses shared strings, inline strings and
 * numeric cells from the first worksheet. Both rely on ZipArchive (bundled
 * with XAMPP PHP).
 */

// ------------------------------------------------------------------
// Writing
// ------------------------------------------------------------------

/**
 * Build a .xlsx file as a binary string.
 *
 * @param array $headers  Row of column headers (string values).
 * @param array $rows     Rows of cells (arrays of scalar values).
 * @return string         XLSX file contents.
 */
function xlsx_build(array $headers, array $rows): string
{
    $sheetRows = [xlsx_map_row(1, $headers)];
    $r = 2;
    foreach ($rows as $row) {
        $sheetRows[] = xlsx_map_row($r, array_values($row));
        $r++;
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<sheetData>' . implode('', $sheetRows) . '</sheetData></worksheet>';

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ' .
        'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
        '</Types>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
        '</Relationships>';

    $zip = new ZipArchive();
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx') ?: 'xlsx_tmp';
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        return '';
    }
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rootRels);
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    $data = @file_get_contents($tmp);
    @unlink($tmp);
    return $data === false ? '' : $data;
}

/** Build one <row> XML fragment. */
function xlsx_map_row(int $rowIndex, array $values): string
{
    $cells = '';
    $col = 0;
    foreach ($values as $value) {
        $ref = xlsx_col_ref($col);
        if (is_int($value) || is_float($value)) {
            $cells .= '<c r="' . $ref . $rowIndex . '"><v>' . $value . '</v></c>';
        } else {
            $cells .= '<c r="' . $ref . $rowIndex . '" t="inlineStr"><is><t xml:space="preserve">' .
                xlsx_xml_escape((string)$value) . '</t></is></c>';
        }
        $col++;
    }
    return '<row r="' . $rowIndex . '">' . $cells . '</row>';
}

/** Convert a 0-based column index to an Excel column reference (A, B, ... Z, AA). */
function xlsx_col_ref(int $index): string
{
    $ref = '';
    while ($index >= 0) {
        $ref = chr(65 + ($index % 26)) . $ref;
        $index = intdiv($index, 26) - 1;
    }
    return $ref;
}

function xlsx_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// ------------------------------------------------------------------
// Reading
// ------------------------------------------------------------------

/**
 * Read all rows from the first worksheet of an .xlsx file.
 *
 * @param string $file  Path to the .xlsx file.
 * @return array        Rows of cells (each an array of scalar values).
 */
function xlsx_read_rows(string $file): array
{
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        return [];
    }

    $shared = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false && $ssXml !== '') {
        $dom = new DOMDocument();
        if (@$dom->loadXML($ssXml)) {
            $xp = new DOMXPath($dom);
            $xp->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($xp->query('//m:si') as $si) {
                $text = '';
                foreach ($xp->query('.//m:t', $si) as $t) {
                    $text .= $t->textContent;
                }
                $shared[] = $text;
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false || $sheetXml === '') {
        return [];
    }

    $dom = new DOMDocument();
    if (!@$dom->loadXML($sheetXml)) {
        return [];
    }

    $rows = [];
    $xp = new DOMXPath($dom);
    $xp->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    foreach ($xp->query('//m:sheetData/m:row') as $row) {
        $cells = [];
        foreach ($xp->query('./m:c', $row) as $c) {
            $type = $c->getAttribute('t');
            $ref  = $c->getAttribute('r');

            if ($type === 's') {
                $idx = (int)$c->textContent;
                $value = $shared[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = '';
                foreach ($xp->query('.//m:t', $c) as $t) {
                    $value .= $t->textContent;
                }
            } else {
                $value = $c->textContent;
            }

            // Determine the column position (from the cell reference, or sequential)
            $colIndex = $ref !== '' ? xlsx_col_to_index(preg_replace('/\d+/', '', $ref)) : count($cells);
            while (count($cells) < $colIndex) {
                $cells[] = '';
            }
            $cells[] = $value;
        }
        $rows[] = $cells;
    }

    return $rows;
}

/** Convert an Excel column reference (e.g. "AA") to a 0-based index. */
function xlsx_col_to_index(string $ref): int
{
    $ref = strtoupper($ref);
    $index = 0;
    $len = strlen($ref);
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord($ref[$i]) - 64);
    }
    return $index - 1;
}
