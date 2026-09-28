<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$user = require_login($pdo);
if (!is_admin($user) && empty($user['instructor_of'])) {
    flash_set('err', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    redirect('dashboard.php');
}

$format = ($_GET['format'] ?? '') === 'pdf' ? 'pdf' : 'xlsx';
$level = trim((string) ($_GET['level'] ?? ''));
$group = trim((string) ($_GET['group'] ?? ''));
$semester = in_array($_GET['semester'] ?? '', ['1', '2'], true) ? $_GET['semester'] : '1';
$year = trim((string) ($_GET['year'] ?? (string) (date('Y') + 543)));
$instructor = trim((string) ($_GET['instructor'] ?? ''));

if (!is_admin($user) && !teaches_level_group($user, $level, $group)) {
    flash_set('err', 'คุณไม่มีสิทธิ์ส่งออกสรุปโครงงานของระดับชั้น/กลุ่มเรียนนี้');
    redirect('project_summary.php');
}
if ($level === '' || $group === '') {
    flash_set('err', 'กรุณาเลือกระดับชั้นและกลุ่มเรียน');
    redirect('project_summary.php');
}

$stmt = $pdo->prepare(
    "SELECT p.*, GROUP_CONCAT(pm.user_id) AS member_ids_raw FROM projects p
     LEFT JOIN project_members pm ON pm.project_id = p.id
     WHERE p.level = :level AND p.student_group = :grp
     GROUP BY p.id ORDER BY p.code, p.title"
);
$stmt->execute(['level' => $level, 'grp' => $group]);
$rows = array_map('decode_project', $stmt->fetchAll());

$data = [];
$totalMembers = 0;
foreach ($rows as $r) {
    $names = member_display_names($pdo, $r);
    if (!$names && $r['member_ids']) $names = array_fill(0, count($r['member_ids']), '');
    $totalMembers += count($names);
    $data[] = ['title' => $r['title'], 'names' => $names];
}

$levelChecks = (str_starts_with($level, 'ปวช') ? '[X] ปวช.   [ ] ปวส.' : (str_starts_with($level, 'ปวส') ? '[ ] ปวช.   [X] ปวส.' : '[ ] ปวช.   [ ] ปวส.'));
$title = 'แบบรายงานโครงงานวิชาชีพ';
$subtitle = "ประจำภาคเรียนที่ {$semester}/{$year}";
$infoLine1 = "{$levelChecks}    ชื่อครูผู้สอน {$instructor}";
$infoLine2 = "ชั้นปี/กลุ่มเรียน {$level}/{$group}    จำนวน {$totalMembers} คน " . count($data) . ' กลุ่ม';
$fileBase = 'สรุปโครงงาน_' . $level . '_' . $group . '_' . $semester . '-' . $year;

if ($format === 'xlsx') {
    $sheet = new Spreadsheet();
    $ws = $sheet->getActiveSheet();
    $ws->setTitle('สรุปโครงงาน');

    $ws->mergeCells('A1:D1')->setCellValue('A1', $title);
    $ws->mergeCells('A2:D2')->setCellValue('A2', $subtitle);
    $ws->mergeCells('A3:D3')->setCellValue('A3', $infoLine1);
    $ws->mergeCells('A4:D4')->setCellValue('A4', $infoLine2);
    foreach ([1, 2, 3, 4] as $r) $ws->getStyle("A$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $ws->getStyle('A1')->getFont()->setBold(true)->setSize(15);
    $ws->getStyle('A3:A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $headerRow = 6;
    $headers = ['ที่', 'ชื่อโครงงาน', 'ชื่อผู้จัดทำ', 'หมายเหตุ'];
    foreach ($headers as $i => $h) {
        $col = chr(65 + $i);
        $ws->setCellValue("{$col}{$headerRow}", $h);
    }
    $ws->getStyle("A{$headerRow}:D{$headerRow}")->getFont()->setBold(true);
    $ws->getStyle("A{$headerRow}:D{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FDE68A');
    $ws->getStyle("A{$headerRow}:D{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $r = $headerRow + 1;
    foreach ($data as $i => $d) {
        $ws->setCellValue("A{$r}", $i + 1);
        $ws->setCellValue("B{$r}", $d['title']);
        $ws->setCellValue("C{$r}", implode("\n", $d['names']) ?: '-');
        $ws->setCellValue("D{$r}", '');
        $ws->getStyle("C{$r}")->getAlignment()->setWrapText(true);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $r++;
    }
    if (!$data) {
        $ws->mergeCells("A{$r}:D{$r}")->setCellValue("A{$r}", 'ไม่มีโครงงานในระดับชั้น/กลุ่มเรียนนี้');
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $r++;
    }
    $lastRow = $r - 1;
    $ws->getStyle("A{$headerRow}:D{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    $sigRow = $lastRow + 3;
    $ws->mergeCells("A{$sigRow}:D{$sigRow}")->setCellValue("A{$sigRow}", 'ลงชื่อ .............................................');
    $ws->mergeCells('A' . ($sigRow + 1) . ':D' . ($sigRow + 1))->setCellValue('A' . ($sigRow + 1), '( ' . ($instructor ?: '.................................') . ' )');
    $ws->mergeCells('A' . ($sigRow + 2) . ':D' . ($sigRow + 2))->setCellValue('A' . ($sigRow + 2), 'ครูผู้สอนประจำวิชา');
    foreach ([0, 1, 2] as $off) $ws->getStyle('A' . ($sigRow + $off))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    $ws->getColumnDimension('A')->setWidth(6);
    $ws->getColumnDimension('B')->setWidth(46);
    $ws->getColumnDimension('C')->setWidth(28);
    $ws->getColumnDimension('D')->setWidth(20);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileBase . '.xlsx"');
    header('Cache-Control: max-age=0');
    (new Xlsx($sheet))->save('php://output');
    exit;
}

// PDF via mPDF — ใช้ฟอนต์ Garuda ที่มากับ mPDF เอง รองรับภาษาไทยได้ครบถ้วนโดยไม่ต้องติดตั้งฟอนต์เพิ่ม
$mpdfTmp = __DIR__ . '/../storage/tmp/mpdf';
if (!is_dir($mpdfTmp)) mkdir($mpdfTmp, 0775, true);
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'default_font' => 'garuda',
    'tempDir' => $mpdfTmp,
    'margin_top' => 18, 'margin_bottom' => 15, 'margin_left' => 15, 'margin_right' => 15,
]);

$rowsHtml = '';
foreach ($data as $i => $d) {
    $namesHtml = implode('<br>', array_map('htmlspecialchars', $d['names'])) ?: '-';
    $rowsHtml .= '<tr><td class="c">' . ($i + 1) . '</td><td>' . htmlspecialchars($d['title']) . '</td><td>' . $namesHtml . '</td><td></td></tr>';
}
if (!$data) {
    $rowsHtml = '<tr><td colspan="4" class="c">ไม่มีโครงงานในระดับชั้น/กลุ่มเรียนนี้</td></tr>';
}

$html = '<html><head><style>
  body { font-family: garuda; font-size: 13pt; }
  h1 { text-align:center; font-size: 16pt; margin: 0; }
  p.sub { text-align:center; margin: 4px 0 14px; }
  p.info { margin: 2px 0; }
  table { width:100%; border-collapse: collapse; margin-top: 10px; }
  th, td { border: 1px solid #333; padding: 6px 8px; font-size: 12pt; vertical-align: top; }
  th { background:#fde68a; text-align:center; }
  td.c { text-align:center; width: 40px; }
  .sig { margin-top: 50px; text-align: right; }
</style></head><body>'
    . '<h1>' . htmlspecialchars($title) . '</h1>'
    . '<p class="sub">' . htmlspecialchars($subtitle) . '</p>'
    . '<p class="info">' . htmlspecialchars($infoLine1) . '</p>'
    . '<p class="info">' . htmlspecialchars($infoLine2) . '</p>'
    . '<table><thead><tr><th style="width:40px">ที่</th><th>ชื่อโครงงาน</th><th style="width:180px">ชื่อผู้จัดทำ</th><th style="width:120px">หมายเหตุ</th></tr></thead>'
    . '<tbody>' . $rowsHtml . '</tbody></table>'
    . '<div class="sig">ลงชื่อ .............................................<br>( ' . htmlspecialchars($instructor ?: '.................................') . ' )<br>ครูผู้สอนประจำวิชา</div>'
    . '</body></html>';

$mpdf->WriteHTML($html);
$mpdf->Output($fileBase . '.pdf', \Mpdf\Output\Destination::DOWNLOAD);
