<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);

$list = visible_projects($pdo, $user);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="โครงงาน-IT-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM สำหรับ Excel ภาษาไทย
fputcsv($out, ['รหัส', 'ชื่อโครงงาน', 'ประเภท', 'ระดับชั้น', 'กลุ่ม', 'ผู้จัดทำ', 'ครูที่ปรึกษา', 'ครูที่ปรึกษาร่วม', 'วันที่เริ่ม', 'กำหนดส่ง', 'ขั้นตอนปัจจุบัน', 'ความคืบหน้า(%)', 'สถานะ']);

foreach ($list as $p) {
    fputcsv($out, [
        $p['code'], $p['title'], $p['type'], $p['level'], $p['student_group'],
        implode(' / ', member_display_names($pdo, $p)), $p['advisor'], $p['co_advisor'],
        $p['start_date'], $p['due_date'], project_current_step($p['steps']),
        project_progress($p['steps']), STATUS_TEXT[project_status($p)],
    ]);
}
fclose($out);
exit;
