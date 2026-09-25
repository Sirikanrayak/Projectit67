<?php
declare(strict_types=1);

const LEVELS = ['ปวช.1', 'ปวช.2', 'ปวช.3', 'ปวส.1', 'ปวส.2'];

const STEPS = [
    'เสนอหัวข้อโครงงาน',
    'สอบหัวข้อ (อนุมัติหัวข้อ)',
    'จัดทำบทที่ 1–3',
    'สอบความก้าวหน้า 50%',
    'พัฒนาระบบ / ชิ้นงาน',
    'ทดสอบและประเมินผล',
    'จัดทำบทที่ 4–5',
    'สอบโครงงาน 100%',
    'ส่งรูปเล่มและไฟล์ฉบับสมบูรณ์',
];

const PROJECT_TYPES = [
    'พัฒนาเว็บไซต์/เว็บแอปพลิเคชัน',
    'แอปพลิเคชันบนมือถือ',
    'ระบบฐานข้อมูล',
    'IoT / สมองกลฝังตัว',
    'เครือข่ายคอมพิวเตอร์',
    'ระบบปัญญาประดิษฐ์',
    'อื่น ๆ',
];

const STATUS_TEXT = [
    'notstarted' => 'ยังไม่เริ่ม',
    'progress'   => 'กำลังดำเนินการ',
    'late'       => 'เลยกำหนด',
    'done'       => 'เสร็จสมบูรณ์',
];

const USER_STATUS_TEXT = [
    'pending'   => 'รออนุมัติ',
    'active'    => 'ใช้งานได้',
    'suspended' => 'ถูกระงับ',
];

const USER_STATUS_BADGE = [
    'pending'   => 'soon',
    'active'    => 'done',
    'suspended' => 'late',
];

const ROLE_TEXT = [
    'admin'   => 'ผู้ดูแลระบบ',
    'teacher' => 'ครูที่ปรึกษา',
    'student' => 'นักเรียน',
];

// เกณฑ์การให้คะแนน/ประเมินผลโครงงาน (รวม 100 คะแนน)
const RUBRIC = [
    ['key' => 'content',      'text' => 'เนื้อหาและความสมบูรณ์ของโครงงาน', 'max' => 25],
    ['key' => 'creativity',   'text' => 'ความคิดสร้างสรรค์และนวัตกรรม',     'max' => 25],
    ['key' => 'execution',    'text' => 'การพัฒนาและใช้งานได้จริง',         'max' => 25],
    ['key' => 'presentation', 'text' => 'การนำเสนอและเอกสารประกอบ',         'max' => 25],
];

const DUE_SOON_DAYS = 7;
const MAX_FILE_BYTES = 3 * 1024 * 1024;
const MAX_FILES_PER_PROJECT = 8;

const TH_MONTHS = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
const TH_MONTHS_FULL = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
const WEEKDAYS_TH = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];

function esc(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function thai_date(?string $iso): string
{
    if (!$iso) return '-';
    [$y, $m, $d] = array_map('intval', explode('-', substr($iso, 0, 10)));
    if (!$y) return '-';
    return sprintf('%d %s %d', $d, TH_MONTHS[$m - 1], $y + 543);
}

function days_left(?string $iso): ?int
{
    if (!$iso) return null;
    $due = new DateTime($iso);
    $today = new DateTime(date('Y-m-d'));
    return (int) $today->diff($due)->format('%r%a');
}

function project_progress(array $steps): int
{
    $done = count(array_filter($steps));
    return (int) round($done / count(STEPS) * 100);
}

function project_current_step(array $steps): string
{
    foreach (STEPS as $i => $label) {
        if (empty($steps[$i])) return $label;
    }
    return 'เสร็จสมบูรณ์';
}

function project_status(array $project): string
{
    $pr = project_progress($project['steps']);
    if ($pr === 100) return 'done';
    $dl = days_left($project['due_date']);
    if ($dl !== null && $dl < 0) return 'late';
    return $pr === 0 ? 'notstarted' : 'progress';
}

function due_text(array $project): string
{
    if (project_status($project) === 'done') return 'ส่งแล้ว';
    $dl = days_left($project['due_date']);
    if ($dl === null) return 'ไม่ได้กำหนดวันส่ง';
    if ($dl < 0) return 'เลยกำหนด ' . (-$dl) . ' วัน';
    if ($dl === 0) return 'ครบกำหนดวันนี้';
    return 'เหลือ ' . $dl . ' วัน';
}

function badge_html(array $project): string
{
    $s = project_status($project);
    return '<span class="badge ' . $s . '">' . esc(STATUS_TEXT[$s]) . '</span>';
}

function fmt_bytes(int $n): string
{
    if ($n < 1024) return $n . ' B';
    if ($n < 1024 * 1024) return round($n / 1024, 1) . ' KB';
    return round($n / 1024 / 1024, 2) . ' MB';
}

function grade_label(int $total): string
{
    if ($total >= 90) return 'ดีเยี่ยม';
    if ($total >= 80) return 'ดีมาก';
    if ($total >= 70) return 'ดี';
    if ($total >= 60) return 'พอใช้';
    return 'ควรปรับปรุง';
}

const PW_EXAMPLE = 'Nayok@2565';

function password_problems(string $pw): array
{
    $problems = [];
    if (mb_strlen($pw) < 8) $problems[] = 'อย่างน้อย 8 ตัวอักษร';
    if (!preg_match('/[A-Z]/', $pw)) $problems[] = 'ตัวพิมพ์ใหญ่ภาษาอังกฤษ (A–Z)';
    if (!preg_match('/[a-z]/', $pw)) $problems[] = 'ตัวพิมพ์เล็กภาษาอังกฤษ (a–z)';
    if (!preg_match('/[0-9]/', $pw)) $problems[] = 'ตัวเลข (0–9)';
    if (!preg_match('/[!-\/:-@\[-`{-~]/', $pw)) $problems[] = 'อักขระพิเศษ เช่น @ # $ % ! _ -';
    if (!preg_match('/^[\x21-\x7E]+$/', $pw)) $problems[] = 'ไม่มีช่องว่างหรือตัวอักษรภาษาไทย';
    return $problems;
}

function password_error(string $pw): string
{
    $bad = password_problems($pw);
    if (!$bad) return '';
    return 'รหัสผ่านยังไม่ได้มาตรฐาน ต้องมี: ' . implode(', ', $bad) . ' (ตัวอย่าง: ' . PW_EXAMPLE . ')';
}

function flash_set(string $type, string $text): void
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

function flash_take(): ?array
{
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function decode_json_array(?string $json): array
{
    if (!$json) return [];
    $v = json_decode($json, true);
    return is_array($v) ? $v : [];
}
