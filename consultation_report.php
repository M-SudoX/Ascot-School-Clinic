<?php
// generate_consultation_report.php
require('fpdf/fpdf.php');
require('includes/db_connect.php'); // ✅ Update this path if needed

class PDF extends FPDF
{
    // Page header
    function Header()
    {
        // School Logo
        $this->Image('img/logo.png', 10, 8, 20);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Aurora State College of Technology', 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 8, 'Online School Clinic System', 0, 1, 'C');
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 10, 'Consultation Requests Report', 0, 1, 'C');
        $this->Ln(3);

        // Table Header
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(240, 200, 0); // Yellow
        $this->Cell(10, 8, 'ID', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Student ID', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Date', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Time', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Requested', 1, 0, 'C', true);
        $this->Cell(50, 8, 'Notes', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Status', 1, 1, 'C', true);
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Generated on ' . date('F d, Y h:i A'), 0, 0, 'L');
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'R');
    }
}

// Create PDF
$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Fetch data from database
$query = "SELECT * FROM consultation_requests ORDER BY created_at DESC";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell(10, 8, $row['id'], 1, 0, 'C');
        $pdf->Cell(25, 8, $row['student_id'], 1, 0, 'C');
        $pdf->Cell(25, 8, $row['date'], 1, 0, 'C');
        $pdf->Cell(25, 8, $row['time'], 1, 0, 'C');
        $pdf->Cell(35, 8, $row['requested'], 1, 0, 'C');
        $pdf->Cell(50, 8, substr($row['notes'], 0, 30) . (strlen($row['notes']) > 30 ? '...' : ''), 1, 0, 'L');
        $pdf->Cell(25, 8, $row['status'], 1, 1, 'C');
    }
} else {
    $pdf->Cell(195, 10, 'No consultation requests found.', 1, 1, 'C');
}

// Output PDF to browser
$pdf->Output('I', 'consultation_report.pdf');
?>
