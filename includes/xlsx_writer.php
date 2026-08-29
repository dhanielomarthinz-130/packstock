<?php
// includes/xlsx_writer.php - Pure PHP High-Performance Genuine XLSX (OpenXML) Exporter
// Produces native .xlsx spreadsheets compatible with Excel without any warning/corruption dialogs.

class XlsxWriter {
    /**
     * Generate and stream genuine .xlsx file to browser
     *
     * @param string $filename Output filename (e.g. "Laporan.xlsx")
     * @param string $title Header title inside worksheet
     * @param array $headers Column header titles
     * @param array $rows Array of data rows
     * @param array $colWidths Optional column widths
     */
    public static function download($filename, $title, array $headers, array $rows, array $colWidths = []) {
        if (!str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        $zip = new ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');

        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            die("Gagal membuat file spreadsheet temporary.");
        }

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . "\n" .
'  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . "\n" .
'  <Default Extension="xml" ContentType="application/xml"/>' . "\n" .
'  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . "\n" .
'  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . "\n" .
'  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . "\n" .
'</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n" .
'  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' . "\n" .
'</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n" .
'  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' . "\n" .
'  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' . "\n" .
'</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 4. xl/workbook.xml
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n" .
'  <sheets>' . "\n" .
'    <sheet name="Sheet1" sheetId="1" r:id="rId1"/>' . "\n" .
'  </sheets>' . "\n" .
'</workbook>';
        $zip->addFromString('xl/workbook.xml', $wb);

        // 5. xl/styles.xml
        // Style Index:
        // 0: Default
        // 1: Title (Bold 13pt Emerald)
        // 2: Header (Bold 10pt White on Emerald #047857 Fill, Center, Thin Border)
        // 3: Data Text Left (Thin Border, Left, Text Format @)
        // 4: Data Text Center (Thin Border, Center, Text Format @)
        // 5: Data Number Right (Thin Border, Right, #,##0 format)
        // 6: Subtitle (Italic 9pt Slate)
        // 7: Status Success (Light Green #D1FAE5, Emerald text #065F46, Bold, Center)
        // 8: Status Warning (Light Amber #FEF3C7, Amber text #92400E, Bold, Center)
        // 9: Status Danger (Light Rose #FEE2E2, Rose text #991B1B, Bold, Center)
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n" .
'  <numFmts count="1">' . "\n" .
'    <numFmt numFmtId="164" formatCode="#,##0"/>' . "\n" .
'  </numFmts>' . "\n" .
'  <fonts count="7">' . "\n" .
'    <font><name val="Calibri"/><sz val="10"/><color rgb="FF1E293B"/></font>' . "\n" .
'    <font><b/><name val="Calibri"/><sz val="13"/><color rgb="FF047857"/></font>' . "\n" .
'    <font><b/><name val="Calibri"/><sz val="10"/><color rgb="FFFFFFFF"/></font>' . "\n" .
'    <font><i/><name val="Calibri"/><sz val="9"/><color rgb="FF64748B"/></font>' . "\n" .
'    <font><b/><name val="Calibri"/><sz val="10"/><color rgb="FF065F46"/></font>' . "\n" .
'    <font><b/><name val="Calibri"/><sz val="10"/><color rgb="FF92400E"/></font>' . "\n" .
'    <font><b/><name val="Calibri"/><sz val="10"/><color rgb="FF991B1B"/></font>' . "\n" .
'  </fonts>' . "\n" .
'  <fills count="6">' . "\n" .
'    <fill><patternFill patternType="none"/></fill>' . "\n" .
'    <fill><patternFill patternType="gray125"/></fill>' . "\n" .
'    <fill><patternFill patternType="solid"><fgColor rgb="FF047857"/></patternFill></fill>' . "\n" .
'    <fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/></patternFill></fill>' . "\n" .
'    <fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>' . "\n" .
'    <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>' . "\n" .
'  </fills>' . "\n" .
'  <borders count="2">' . "\n" .
'    <border><left/><right/><top/><bottom/><diagonal/></border>' . "\n" .
'    <border>' . "\n" .
'      <left style="thin"><color rgb="FFCBD5E1"/></left>' . "\n" .
'      <right style="thin"><color rgb="FFCBD5E1"/></right>' . "\n" .
'      <top style="thin"><color rgb="FFCBD5E1"/></top>' . "\n" .
'      <bottom style="thin"><color rgb="FFCBD5E1"/></bottom>' . "\n" .
'      <diagonal/>' . "\n" .
'    </border>' . "\n" .
'  </borders>' . "\n" .
'  <cellXfs count="10">' . "\n" .
'    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' . "\n" .
'    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' . "\n" .
'    <xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . "\n" .
'    <xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>' . "\n" .
'    <xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . "\n" .
'    <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' . "\n" .
'    <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>' . "\n" .
'    <xf numFmtId="49" fontId="4" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . "\n" .
'    <xf numFmtId="49" fontId="5" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . "\n" .
'    <xf numFmtId="49" fontId="6" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . "\n" .
'  </cellXfs>' . "\n" .
'</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // 6. xl/worksheets/sheet1.xml
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n";

        if (!empty($colWidths)) {
            $sheetXml .= '  <cols>' . "\n";
            $colIdx = 1;
            foreach ($colWidths as $w) {
                $sheetXml .= '    <col min="' . $colIdx . '" max="' . $colIdx . '" width="' . $w . '" customWidth="1"/>' . "\n";
                $colIdx++;
            }
            $sheetXml .= '  </cols>' . "\n";
        }

        $sheetXml .= '  <sheetData>' . "\n";
        $rowNum = 1;

        if (!empty($title)) {
            $sheetXml .= '    <row r="' . $rowNum . '" ht="24" customHeight="1">' . "\n";
            $sheetXml .= '      <c r="A' . $rowNum . '" s="1" t="inlineStr"><is><t>' . htmlspecialchars($title) . '</t></is></c>' . "\n";
            $sheetXml .= '    </row>' . "\n";
            $rowNum++;

            $sheetXml .= '    <row r="' . $rowNum . '" ht="16" customHeight="1">' . "\n";
            $sheetXml .= '      <c r="A' . $rowNum . '" s="6" t="inlineStr"><is><t>Diekspor pada: ' . date('d F Y H:i:s') . ' WIB | WMS PackStock</t></is></c>' . "\n";
            $sheetXml .= '    </row>' . "\n";
            $rowNum++;

            $rowNum++; // blank spacer row
        }

        // Header Row
        $sheetXml .= '    <row r="' . $rowNum . '" ht="25" customHeight="1">' . "\n";
        $cIdx = 0;
        foreach ($headers as $h) {
            $colLetter = self::colLetter($cIdx);
            $sheetXml .= '      <c r="' . $colLetter . $rowNum . '" s="2" t="inlineStr"><is><t>' . htmlspecialchars($h) . '</t></is></c>' . "\n";
            $cIdx++;
        }
        $sheetXml .= '    </row>' . "\n";
        $rowNum++;

        // Data Rows
        foreach ($rows as $r) {
            $sheetXml .= '    <row r="' . $rowNum . '" ht="20" customHeight="1">' . "\n";
            $cIdx = 0;
            foreach ($r as $val) {
                $colLetter = self::colLetter($cIdx);
                $strVal = (string)$val;
                $upperVal = strtoupper($strVal);

                if (in_array($upperVal, ['AMAN', 'COMPLETED', 'SELESAI'])) {
                    $sheetXml .= '      <c r="' . $colLetter . $rowNum . '" s="7" t="inlineStr"><is><t>' . htmlspecialchars($strVal) . '</t></is></c>' . "\n";
                } elseif (in_array($upperVal, ['MENIPIS', 'IN_PROGRESS', 'ON PROSES', 'PENDING', 'URGENT', 'CRITICAL'])) {
                    $sheetXml .= '      <c r="' . $colLetter . $rowNum . '" s="8" t="inlineStr"><is><t>' . htmlspecialchars($strVal) . '</t></is></c>' . "\n";
                } elseif (in_array($upperVal, ['HABIS', 'CANCELLED', 'DIBATALKAN'])) {
                    $sheetXml .= '      <c r="' . $colLetter . $rowNum . '" s="9" t="inlineStr"><is><t>' . htmlspecialchars($strVal) . '</t></is></c>' . "\n";
                } elseif ((is_int($val) || is_float($val)) && !is_string($val)) {
                    // Only raw numbers passed as int/float (like stock qty, count, total) are treated as numbers
                    $sheetXml .= '      <c r="' . $colLetter . $rowNum . '" s="5"><v>' . $val . '</v></c>' . "\n";
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $strVal) || (preg_match('/^[A-Za-z0-9._-]+$/', $strVal) && strlen($strVal) <= 25) || in_array($upperVal, ['NORMAL', 'PCS', 'BOX', 'ROLL', 'UNIT', 'SET', 'PACK', 'LEMBAR', 'KG', 'LITER', '-'])) {
                    // Item Codes, Dates, Document Numbers, Units -> Center aligned Text (NEVER formatted as number)
                    $sheetXml .= '      <c r="' . $colLetter . $rowNum . '" s="4" t="inlineStr"><is><t>' . htmlspecialchars($strVal) . '</t></is></c>' . "\n";
                } else {
                    // Descriptions, Names, Notes -> Left aligned Text
                    $sheetXml .= '      <c r="' . $colLetter . $rowNum . '" s="3" t="inlineStr"><is><t>' . htmlspecialchars($strVal) . '</t></is></c>' . "\n";
                }
                $cIdx++;
            }
            $sheetXml .= '    </row>' . "\n";
            $rowNum++;
        }

        $sheetXml .= '  </sheetData>' . "\n" . '</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        // Send HTTP headers for true .xlsx
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tempFile));
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        readfile($tempFile);
        @unlink($tempFile);
        exit;
    }

    private static function colLetter($idx) {
        $letters = '';
        $idx += 1;
        while ($idx > 0) {
            $rem = ($idx - 1) % 26;
            $letters = chr(65 + $rem) . $letters;
            $idx = intval(($idx - $rem) / 26);
        }
        return $letters;
    }
}
