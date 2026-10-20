<?php

class SimpleXLSXWriter
{
    public static function output($filename, array $headers, array $rows, array $totals = null)
    {
        if (class_exists('ZipArchive')) {
            $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
            $zip = new ZipArchive();
            if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {

                $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

                $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

                $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

                $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Sales Summary" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';

                $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill fillType="none"/></fill>
    <fill><patternFill fillType="gray125"/></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>
  </cellXfs>
</styleSheet>';

                $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>';

                $rowIdx = 1;
                // Header row
                $sheetXml .= '<row r="' . $rowIdx . '">';
                $colIdx = 0;
                foreach ($headers as $header) {
                    $cellRef = self::colName($colIdx) . $rowIdx;
                    $sheetXml .= '<c r="' . $cellRef . '" t="inlineStr" s="1"><is><t>' . htmlspecialchars($header, ENT_XML1, 'UTF-8') . '</t></is></c>';
                    $colIdx++;
                }
                $sheetXml .= '</row>';

                // Data rows
                foreach ($rows as $row) {
                    $rowIdx++;
                    $sheetXml .= '<row r="' . $rowIdx . '">';
                    $colIdx = 0;
                    foreach ($row as $val) {
                        $cellRef = self::colName($colIdx) . $rowIdx;
                        $strVal = (string) $val;
                        $cleanVal = str_replace(',', '', $strVal);
                        if (is_numeric($cleanVal) && preg_match('/^-?\d[\d,]*(\.\d+)?$/', $strVal)) {
                            $sheetXml .= '<c r="' . $cellRef . '"><v>' . (float) $cleanVal . '</v></c>';
                        } else {
                            $sheetXml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . htmlspecialchars($strVal, ENT_XML1, 'UTF-8') . '</t></is></c>';
                        }
                        $colIdx++;
                    }
                    $sheetXml .= '</row>';
                }

                // Totals row
                if (!empty($totals)) {
                    $rowIdx++;
                    $sheetXml .= '<row r="' . $rowIdx . '">';
                    $totalsArr = array(
                        'TOTAL',
                        '',
                        $totals['SALES WITH BA'],
                        $totals['TC WITH BA'],
                        $totals['AC WITH BA'],
                        $totals['SALES W/O BA'],
                        $totals['TC W/O BA'],
                        $totals['AC W/O BA'],
                    );
                    $colIdx = 0;
                    foreach ($totalsArr as $val) {
                        $cellRef = self::colName($colIdx) . $rowIdx;
                        $strVal = (string) $val;
                        $cleanVal = str_replace(',', '', $strVal);
                        if (is_numeric($cleanVal) && preg_match('/^-?\d[\d,]*(\.\d+)?$/', $strVal)) {
                            $sheetXml .= '<c r="' . $cellRef . '" s="1"><v>' . (float) $cleanVal . '</v></c>';
                        } else {
                            $sheetXml .= '<c r="' . $cellRef . '" t="inlineStr" s="1"><is><t>' . htmlspecialchars($strVal, ENT_XML1, 'UTF-8') . '</t></is></c>';
                        }
                        $colIdx++;
                    }
                    $sheetXml .= '</row>';
                }

                $sheetXml .= '  </sheetData>
</worksheet>';

                $zip->addFromString('[Content_Types].xml', $contentTypes);
                $zip->addFromString('_rels/.rels', $rels);
                $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
                $zip->addFromString('xl/workbook.xml', $workbook);
                $zip->addFromString('xl/styles.xml', $styles);
                $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
                $zip->close();

                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($tempFile));
                header('Pragma: no-cache');
                header('Expires: 0');
                readfile($tempFile);
                @unlink($tempFile);
                exit;
            }
        }

        // SpreadsheetML XML fallback
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . str_replace('.xlsx', '.xls', $filename) . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo '<?xml version="1.0"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        echo ' <Styles><Style ss:ID="Header"><Font ss:Bold="1"/></Style><Style ss:ID="Total"><Font ss:Bold="1"/></Style></Styles>' . "\n";
        echo ' <Worksheet ss:Name="Sales Summary"><Table>' . "\n";

        echo '  <Row ss:StyleID="Header">' . "\n";
        foreach ($headers as $h) {
            echo '   <Cell><Data ss:Type="String">' . htmlspecialchars($h, ENT_XML1, 'UTF-8') . '</Data></Cell>' . "\n";
        }
        echo '  </Row>' . "\n";

        foreach ($rows as $row) {
            echo '  <Row>' . "\n";
            foreach ($row as $val) {
                $strVal = (string) $val;
                $cleanVal = str_replace(',', '', $strVal);
                if (is_numeric($cleanVal) && preg_match('/^-?\d[\d,]*(\.\d+)?$/', $strVal)) {
                    echo '   <Cell><Data ss:Type="Number">' . (float) $cleanVal . '</Data></Cell>' . "\n";
                } else {
                    echo '   <Cell><Data ss:Type="String">' . htmlspecialchars($strVal, ENT_XML1, 'UTF-8') . '</Data></Cell>' . "\n";
                }
            }
            echo '  </Row>' . "\n";
        }

        if (!empty($totals)) {
            echo '  <Row ss:StyleID="Total">' . "\n";
            $totalsArr = array(
                'TOTAL',
                '',
                $totals['SALES WITH BA'],
                $totals['TC WITH BA'],
                $totals['AC WITH BA'],
                $totals['SALES W/O BA'],
                $totals['TC W/O BA'],
                $totals['AC W/O BA'],
            );
            foreach ($totalsArr as $val) {
                $strVal = (string) $val;
                $cleanVal = str_replace(',', '', $strVal);
                if (is_numeric($cleanVal) && preg_match('/^-?\d[\d,]*(\.\d+)?$/', $strVal)) {
                    echo '   <Cell><Data ss:Type="Number">' . (float) $cleanVal . '</Data></Cell>' . "\n";
                } else {
                    echo '   <Cell><Data ss:Type="String">' . htmlspecialchars($strVal, ENT_XML1, 'UTF-8') . '</Data></Cell>' . "\n";
                }
            }
            echo '  </Row>' . "\n";
        }

        echo ' </Table></Worksheet></Workbook>' . "\n";
        exit;
    }

    private static function colName($index)
    {
        $numeric = $index % 26;
        $letter = chr(65 + $numeric);
        $num2 = (int) ($index / 26);
        if ($num2 > 0) {
            return self::colName($num2 - 1) . $letter;
        }

        return $letter;
    }
}
