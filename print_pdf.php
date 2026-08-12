<?php
require __DIR__ . '/config.php';
require_login();
require __DIR__ . '/includes/filters.php';

require_once __DIR__ . '/vendor/tcpdf/tcpdf.php';

// Document control values come from the Settings page (fall back to config.php constants)
$doc_org            = settings_get($conn, 'doc_org', DOC_ORG);
$doc_title          = settings_get($conn, 'doc_title', DOC_TITLE);
$doc_id             = settings_get($conn, 'doc_id', DOC_ID);
$doc_revision       = settings_get($conn, 'doc_revision', DOC_REVISION);
$doc_date_approved  = settings_get($conn, 'doc_date_approved', DOC_DATE_APPROVED);
$doc_classification = settings_get($conn, 'doc_classification', DOC_CLASSIFICATION);
$doc_department     = settings_get($conn, 'doc_department', DOC_DEPARTMENT);
$doc_branch         = settings_get($conn, 'doc_branch', DOC_BRANCH);

$result = fetch_filtered_assets($conn, 'id ASC');

// Reference palette (matches the official document)
$C_PRIMARY = [18, 58, 115];     // #123A73
$C_HEADER_TEXT = [255, 255, 255];
$C_ROW_ODD  = [244, 247, 249]; // #F4F7F9
$C_ROW_EVEN = [255, 255, 255];
$C_ROW_BORDER = [199, 205, 212]; // #C7CDD4
$C_TEXT     = [26, 26, 26];     // #1a1a1a
$C_META     = [51, 51, 51];     // #333333
$C_NOTE     = [102, 102, 102];  // #666666
$C_FOOTER   = [85, 85, 85];     // #555555

// ------------------------------------------------------------------
// TCPDF subclass: official document header on every page + footer page numbers
// ------------------------------------------------------------------
class InventoryPDF extends TCPDF
{
    public $logoPath = '';
    public $docOrg            = DOC_ORG;
    public $docTitle          = DOC_TITLE;
    public $docId             = DOC_ID;
    public $docRevision       = DOC_REVISION;
    public $docDateApproved   = DOC_DATE_APPROVED;
    public $docClassification = DOC_CLASSIFICATION;
    public $docDepartment     = DOC_DEPARTMENT;
    public $docBranch         = DOC_BRANCH;

    public function Header()
    {
        // ---------------- LEFT ZONE: logo + company name (x 15..~60) ----------------
        $this->Image($this->logoPath, 15, 6, 26, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $this->SetXY(15, 13.5);
        $this->SetFont('helvetica', 'B', 9);
        $this->SetTextColor(26, 26, 26);
        $this->Cell(60, 5, $this->docOrg, 0, 1, 'L');

        // ---------------- CENTER ZONE: title (x 70..200, one line) ----------------
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(18, 58, 115);
        $this->SetXY(70, 6);
        $this->Cell(130, 16, $this->docTitle, 0, 0, 'C');

        // ---------------- RIGHT ZONE: document control (x 205..282, top-aligned together) ----------------
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(51, 51, 51);
        $this->SetXY(205, 6);
        $this->Cell(77, 5, 'Doc ID: ' . $this->docId, 0, 1, 'R');
        $this->Cell(77, 5, 'Revision No.: ' . $this->docRevision, 0, 1, 'R');
        $this->Cell(77, 5, 'Date Approved: ' . $this->docDateApproved, 0, 1, 'R');
        $this->SetFont('helvetica', 'B', 8);
        $this->Cell(77, 5, $this->docClassification, 0, 1, 'R');

        // ---------------- Department / Branch (one line below the header band) ----------------
        $this->SetY(28);
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(51, 51, 51);
        $this->SetX(15);
        $this->Cell(130, 5, 'Department: ' . $this->docDepartment, 0, 0, 'L');
        $this->SetX(152);
        $this->Cell(130, 5, 'Branch: ' . $this->docBranch, 0, 1, 'R');

        // ---------------- Note (italic, under dept/branch) ----------------
        $this->SetX(15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(102, 102, 102);
        $this->Cell(267, 4.5, 'Note: Sections that are left blank denotes "Not Applicable" for the specified item.', 0, 1, 'L');

        // ---------------- Blue separator (directly under the note) ----------------
        $this->SetLineWidth(0.8);
        $this->SetDrawColor(18, 58, 115);
        $this->Line(15, 39.5, 282, 39.5);
        $this->SetLineWidth(0.2);
    }

    public function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(85, 85, 85);
        $this->Cell(267, 5, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

// ------------------------------------------------------------------
// Build the PDF
// ------------------------------------------------------------------
$pdf = new InventoryPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->logoPath = realpath(__DIR__ . '/2.png') ?: '';
$pdf->docOrg            = $doc_org;
$pdf->docTitle          = $doc_title;
$pdf->docId             = $doc_id;
$pdf->docRevision       = $doc_revision;
$pdf->docDateApproved   = $doc_date_approved;
$pdf->docClassification = $doc_classification;
$pdf->docDepartment     = $doc_department;
$pdf->docBranch         = $doc_branch;
$sigPath = realpath(__DIR__ . '/SIGNEW.png') ?: '';
$pdf->SetCreator('COMPASS IT Asset Inventory');
$pdf->SetTitle($doc_title . ' - ' . $doc_id);
$pdf->SetAuthor('COMPASS Maritime Training Center');
$pdf->SetSubject('Inventory List of I.T. Infrastructure');

// Margins: top leaves room for the repeated header block; 15mm sides; ~16mm bottom
$pdf->SetMargins(15, 43, 15);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(12);
$pdf->SetAutoPageBreak(true, 16);

$pdf->AddPage();

// Column widths (mm) for landscape A4 = 267mm usable, reference ratios (165/145/150/90/105/115/175/105)
$widths = [41.9, 36.9, 38.1, 22.9, 26.7, 29.2, 44.5, 26.7];

// Table header (drawn again after every page break)
function drawTableHeader(TCPDF $pdf, array $widths, array $C_PRIMARY, array $C_HEADER_TEXT): void
{
    $headers = ['Model', 'Location', 'Property Code', 'Life Span', 'Acquisition Date', 'Disposal Method', 'Remarks', 'ISSUED TO'];
    $pdf->SetFillColor($C_PRIMARY[0], $C_PRIMARY[1], $C_PRIMARY[2]);
    $pdf->SetTextColor($C_HEADER_TEXT[0], $C_HEADER_TEXT[1], $C_HEADER_TEXT[2]);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetDrawColor(255, 255, 255);
    $pdf->SetLineWidth(0.25);
    $y = $pdf->GetY();
    $x = 15;
    foreach ($headers as $i => $h) {
        $pdf->MultiCell($widths[$i], 6.5, $h, 1, 'C', 1, 0, $x, $y, true, 0, false, true, 6.5, 'M', false);
        $x += $widths[$i];
    }
    $pdf->SetY($y + 6.5);
    $pdf->SetTextColor(0, 0, 0);
}

drawTableHeader($pdf, $widths, $C_PRIMARY, $C_HEADER_TEXT);

$pdf->SetDrawColor($C_ROW_BORDER[0], $C_ROW_BORDER[1], $C_ROW_BORDER[2]);
$pdf->SetLineWidth(0.2);
$pdf->SetFont('helvetica', '', 7.5);

// Per-column horizontal alignment: 0=left, 1=center
$aligns = [0, 0, 1, 1, 1, 1, 0, 0];
$ALIGN_CHARS = ['L', 'C'];

$rowIndex = 0;
while ($row = $result->fetch_assoc()) {
    $cells = [
        (string)$row['items'],
        (string)$row['location'],
        (string)$row['property_code'],
        (string)$row['life_span'],
        (string)$row['acquisition_date'],
        (string)$row['disposal_method'],
        (string)$row['remarks'],
        (string)$row['issued_to'],
    ];

    // Compute the tallest cell in this row (without drawing)
    $rowHeight = 0;
    foreach ($cells as $i => $cell) {
        $h = $pdf->MultiCell($widths[$i], 3.7, $cell, 0, $ALIGN_CHARS[$aligns[$i]], 0, 0, '', '', true, 0, false, true, 0, 'T', true);
        if ($h > $rowHeight) {
            $rowHeight = $h;
        }
    }
    if ($rowHeight < 5.3) {
        $rowHeight = 5.3;
    }

    // Page break if the row does not fit (rows are never split)
    if ($pdf->GetY() + $rowHeight > $pdf->getPageHeight() - 16) {
        $pdf->AddPage();
        drawTableHeader($pdf, $widths, $C_PRIMARY, $C_HEADER_TEXT);
    }

    // Alternating row color: odd rows #F4F7F9, even rows #FFFFFF
    $fill = ($rowIndex % 2 === 0);
    $pdf->SetFillColor($C_ROW_ODD[0], $C_ROW_ODD[1], $C_ROW_ODD[2]);
    $y = $pdf->GetY();
    $x = 15;
    foreach ($cells as $i => $cell) {
        $pdf->MultiCell($widths[$i], 3.7, $cell, 1, $ALIGN_CHARS[$aligns[$i]], $fill ? 1 : 0, 0, $x, $y, true, 0, false, true, $rowHeight, 'T', false);
        $x += $widths[$i];
    }
    $pdf->SetXY(15, $y + $rowHeight);
    $rowIndex++;
}

// ------------------------------------------------------------------
// Signature section — only on the final page (add a page if it won't fit)
// ------------------------------------------------------------------
if ($pdf->GetY() + 38 > $pdf->getPageHeight() - 16) {
    $pdf->AddPage();
}

$pdf->SetLineWidth(0.3);
$pdf->SetDrawColor(0, 0, 0);
$y = $pdf->GetY() + 4;

// Prepared by (left)
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor($C_PRIMARY[0], $C_PRIMARY[1], $C_PRIMARY[2]);
$pdf->SetX(15);
$pdf->Cell(120, 5, 'Prepared by:', 0, 1, 'L');
$pdf->Line(15, $y + 9, 135, $y + 9);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor($C_NOTE[0], $C_NOTE[1], $C_NOTE[2]);
$pdf->SetY($y + 11);
$pdf->SetX(15);
$pdf->Cell(120, 5, 'Name and Signature', 0, 1, 'C');

// Approved by (right)
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor($C_PRIMARY[0], $C_PRIMARY[1], $C_PRIMARY[2]);
$pdf->SetY($y);
$pdf->SetX(165);
$pdf->Cell(117, 5, 'Approved by:', 0, 1, 'L');
$pdf->Image($sigPath, 213, $y + 5, 0, 22, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(26, 26, 26);
$pdf->SetY($y + 20.5);
$pdf->SetX(165);
$pdf->Cell(117, 5, 'RYAN ALDRICH LAVILLA', 0, 1, 'C');
$pdf->Line(165, $y + 26, 282, $y + 26);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor($C_NOTE[0], $C_NOTE[1], $C_NOTE[2]);
$pdf->SetY($y + 28);
$pdf->SetX(165);
$pdf->Cell(117, 5, 'Department Head / Manager', 0, 1, 'C');

// ------------------------------------------------------------------
// Output
// ------------------------------------------------------------------
// Use dest 'S' to get the PDF as a string, then send our own headers.
// (TCPDF's dest 'I' hardcodes 'Cache-Control: private ...' which would
//  override the no-cache headers below and can serve a stale PDF.)
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . rawurlencode(basename('Inventory_List_' . $doc_id . '.pdf')) . '"; ' .
    'filename*=UTF-8\'\'' . rawurlencode(basename('Inventory_List_' . $doc_id . '.pdf')));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
echo $pdf->Output('Inventory_List_' . $doc_id . '.pdf', 'S');
