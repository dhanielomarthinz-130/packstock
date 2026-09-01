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

        // Clean any preceding output buffers or notices to prevent corruption
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        // Check if ZipArchive is available
        if (class_exists('ZipArchive')) {
            // Find writable temp directory
            $tempDir = __DIR__ . '/../scratch';
            if (!is_dir($tempDir) || !is_writable($tempDir)) {
                $tempDir = sys_get_temp_dir();
            }
            if (!is_dir($tempDir) || !is_writable($tempDir)) {
                $tempDir = '.';
            }

            $tempFile = @tempnam($tempDir, 'xlsx_');
            if ($tempFile) {
                $zip = new ZipArchive();
                if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
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
                                $sheetXml .= '      <c r="' . $colLetter . $rowNum . '" s="5"><v>' . $val . '</v></c>' . "\n";
                            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $strVal) || (preg_match('/^[A-Za-z0-9._-]+$/', $strVal) && strlen($strVal) <= 25) || in_array($upperVal, ['NORMAL', 'PCS', 'BOX', 'ROLL', 'UNIT', 'SET', 'PACK', 'LEMBAR', 'KG', 'LITER', '-'])) {
                                $sheetXml .= '      <c r="' . $colLetter . $rowNum . '" s="4" t="inlineStr"><is><t>' . htmlspecialchars($strVal) . '</t></is></c>' . "\n";
                            } else {
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
                    header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');
                    header('Pragma: public');
                    header('Expires: 0');

                    readfile($tempFile);
                    @unlink($tempFile);
                    exit;
                }
            }
        }

        // FALLBACK: Stream XML Spreadsheet 2003 (.xls) which Microsoft Excel opens natively without third-party libs
        $fallbackFilename = preg_replace('/\.xlsx$/i', '.xls', $filename);
        self::streamExcelXml($fallbackFilename, $title, $headers, $rows);
    }

    /**
     * Fallback XML Spreadsheet 2003 stream (Zero dependencies, 100% Excel compatible)
     */
    private static function streamExcelXml($filename, $title, array $headers, array $rows) {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');
        header('Pragma: public');
        header('Expires: 0');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ' .
             'xmlns:o="urn:schemas-microsoft-com:office:office" ' .
             'xmlns:x="urn:schemas-microsoft-com:office:excel" ' .
             'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        
        echo '  <Styles>' . "\n";
        echo '    <Style ss:ID="Default" ss:Name="Normal"><Font ss:FontName="Calibri" ss:Size="10"/></Style>' . "\n";
        echo '    <Style ss:ID="Title"><Font ss:FontName="Calibri" ss:Size="13" ss:Bold="1" ss:Color="#047857"/></Style>' . "\n";
        echo '    <Style ss:ID="Sub"><Font ss:FontName="Calibri" ss:Size="9" ss:Italic="1" ss:Color="#64748B"/></Style>' . "\n";
        echo '    <Style ss:ID="Header"><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#047857" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders></Style>' . "\n";
        echo '    <Style ss:ID="DataLeft"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders><Alignment ss:Horizontal="Left" ss:Vertical="Center"/></Style>' . "\n";
        echo '    <Style ss:ID="DataCenter"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>' . "\n";
        echo '    <Style ss:ID="DataNum"><NumberFormat ss:Format="#,##0"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders><Alignment ss:Horizontal="Right" ss:Vertical="Center"/></Style>' . "\n";
        echo '  </Styles>' . "\n";

        echo '  <Worksheet ss:Name="Sheet1">' . "\n";
        echo '    <Table>' . "\n";

        if (!empty($title)) {
            echo '      <Row ss:Height="24"><Cell ss:StyleID="Title"><Data ss:Type="String">' . htmlspecialchars($title) . '</Data></Cell></Row>' . "\n";
            echo '      <Row ss:Height="16"><Cell ss:StyleID="Sub"><Data ss:Type="String">Diekspor pada: ' . date('d F Y H:i:s') . ' WIB | WMS PackStock</Data></Cell></Row>' . "\n";
            echo '      <Row ss:Height="10"/>' . "\n";
        }

        echo '      <Row ss:Height="25">' . "\n";
        foreach ($headers as $h) {
            echo '        <Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($h) . '</Data></Cell>' . "\n";
        }
        echo '      </Row>' . "\n";

        foreach ($rows as $r) {
            echo '      <Row ss:Height="20">' . "\n";
            foreach ($r as $val) {
                if ((is_int($val) || is_float($val)) && !is_string($val)) {
                    echo '        <Cell ss:StyleID="DataNum"><Data ss:Type="Number">' . $val . '</Data></Cell>' . "\n";
                } else {
                    $strVal = (string)$val;
                    $isCenter = preg_match('/^\d{4}-\d{2}-\d{2}/', $strVal) || (preg_match('/^[A-Za-z0-9._-]+$/', $strVal) && strlen($strVal) <= 25);
                    $styleId = $isCenter ? 'DataCenter' : 'DataLeft';
                    echo '        <Cell ss:StyleID="' . $styleId . '"><Data ss:Type="String">' . htmlspecialchars($strVal) . '</Data></Cell>' . "\n";
                }
            }
            echo '      </Row>' . "\n";
        }

        echo '    </Table>' . "\n";
        echo '  </Worksheet>' . "\n";
        echo '</Workbook>' . "\n";
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
