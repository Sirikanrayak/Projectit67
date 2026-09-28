<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);
if (!is_admin($user) && empty($user['instructor_of'])) {
    flash_set('err', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    redirect('dashboard.php');
}

// รายการ ระดับชั้น/กลุ่มเรียน ที่เลือกได้: แอดมินเลือกได้ทุกกลุ่มที่มีโครงงานจริง, ครูผู้สอนเลือกได้เฉพาะที่ได้รับมอบหมาย
if (is_admin($user)) {
    $combos = $pdo->query(
        "SELECT DISTINCT level, student_group FROM projects WHERE level <> '' AND student_group <> '' ORDER BY level, student_group"
    )->fetchAll();
} else {
    $combos = $user['instructor_of'];
}

$level = trim((string) ($_GET['level'] ?? ($combos[0]['level'] ?? '')));
$group = trim((string) ($_GET['group'] ?? ($combos[0]['student_group'] ?? '')));
$semester = in_array($_GET['semester'] ?? '', ['1', '2'], true) ? $_GET['semester'] : (((int) date('n') >= 5 && (int) date('n') <= 10) ? '1' : '2');
$year = trim((string) ($_GET['year'] ?? (string) (date('Y') + 543)));
$instructor = trim((string) ($_GET['instructor'] ?? (is_teacher($user) ? $user['name'] : '')));

$allowed = is_admin($user) || teaches_level_group($user, $level, $group);
$rows = [];
if ($allowed && $level !== '' && $group !== '') {
    $stmt = $pdo->prepare(
        "SELECT p.*, GROUP_CONCAT(pm.user_id) AS member_ids_raw FROM projects p
         LEFT JOIN project_members pm ON pm.project_id = p.id
         WHERE p.level = :level AND p.student_group = :grp
         GROUP BY p.id ORDER BY p.code, p.title"
    );
    $stmt->execute(['level' => $level, 'grp' => $group]);
    $rows = array_map('decode_project', $stmt->fetchAll());
}

$totalMembers = 0;
foreach ($rows as $r) {
    $names = member_display_names($pdo, $r);
    $totalMembers += count($names) ?: count($r['member_ids']);
}

$pageTitle = 'สรุปโครงงาน | ' . site_setting($pdo, 'site_name');
$activeView = 'summary';
require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/app_nav.php';
$qs = http_build_query(['level' => $level, 'group' => $group, 'semester' => $semester, 'year' => $year, 'instructor' => $instructor]);
?>
<main class="wrap">
  <div class="panel">
    <h2>📋 สรุปโครงงาน</h2>
    <form method="get" class="toolbar" style="margin-top:10px;flex-wrap:wrap">
      <select name="level_group" onchange="var v=this.value.split('|');document.querySelector('[name=level]').value=v[0];document.querySelector('[name=group]').value=v[1];this.form.submit()">
        <?php foreach ($combos as $c): $sel = $c['level'] === $level && $c['student_group'] === $group; ?>
          <option value="<?= esc($c['level']) ?>|<?= esc($c['student_group']) ?>" <?= $sel ? 'selected' : '' ?>><?= esc($c['level']) ?> / กลุ่ม <?= esc($c['student_group']) ?></option>
        <?php endforeach; ?>
        <?php if (!$combos): ?><option value="|">— ยังไม่มีระดับชั้น/กลุ่มเรียนที่ได้รับมอบหมาย —</option><?php endif; ?>
      </select>
      <input type="hidden" name="level" value="<?= esc($level) ?>">
      <input type="hidden" name="group" value="<?= esc($group) ?>">
      <select name="semester" onchange="this.form.submit()">
        <option value="1" <?= $semester === '1' ? 'selected' : '' ?>>ภาคเรียนที่ 1</option>
        <option value="2" <?= $semester === '2' ? 'selected' : '' ?>>ภาคเรียนที่ 2</option>
      </select>
      <input type="text" name="year" value="<?= esc($year) ?>" style="width:90px" placeholder="ปีการศึกษา (พ.ศ.)">
      <input type="text" name="instructor" value="<?= esc($instructor) ?>" style="width:200px" placeholder="ชื่อครูผู้สอน">
      <button class="btn ghost sm" type="submit">🔍 แสดงผล</button>
    </form>

    <?php if (!$allowed): ?>
      <p class="msg err" style="margin-top:14px">🚫 คุณไม่มีสิทธิ์ดูสรุปโครงงานของระดับชั้น/กลุ่มเรียนนี้</p>
    <?php else: ?>
      <div class="toolbar" style="justify-content:flex-end;margin-top:10px">
        <a class="btn ghost sm" href="project_summary_export.php?format=xlsx&<?= $qs ?>">📊 ส่งออก Excel</a>
        <a class="btn ghost sm" href="project_summary_export.php?format=pdf&<?= $qs ?>">📄 ส่งออก PDF</a>
      </div>

      <div class="summary-sheet" style="margin-top:16px">
        <h3 style="text-align:center;margin:0">แบบรายงานโครงงานวิชาชีพ</h3>
        <p style="text-align:center;margin:4px 0 14px">ประจำภาคเรียนที่ <?= esc($semester) ?>/<?= esc($year) ?></p>
        <p style="margin:0 0 4px">
          <?= str_starts_with($level, 'ปวช') ? '☑' : '☐' ?> ปวช. &nbsp; <?= str_starts_with($level, 'ปวส') ? '☑' : '☐' ?> ปวส.
          &nbsp;&nbsp; ชื่อครูผู้สอน ....<?= esc($instructor) ?: '................................' ?>....
        </p>
        <p style="margin:0 0 14px">ชั้นปี/กลุ่มเรียน <?= esc($level) ?>/<?= esc($group) ?> &nbsp;&nbsp; จำนวน <?= $totalMembers ?> คน <?= count($rows) ?> กลุ่ม</p>
        <table class="display" style="width:100%">
          <thead>
            <tr><th style="width:50px">ที่</th><th>ชื่อโครงงาน</th><th style="width:220px">ชื่อผู้จัดทำ</th><th style="width:140px">หมายเหตุ</th></tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="4" style="text-align:center;color:var(--muted)">ไม่มีโครงงานในระดับชั้น/กลุ่มเรียนนี้</td></tr>
            <?php else: foreach ($rows as $i => $r): $names = member_display_names($pdo, $r); ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= esc($r['title']) ?></td>
                <td><?= $names ? implode('<br>', array_map('esc', $names)) : '-' ?></td>
                <td></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        <p style="margin-top:40px;text-align:right">ลงชื่อ .............................................<br>( <?= esc($instructor) ?: '.................................' ?> )<br>ครูผู้สอนประจำวิชา</p>
      </div>
    <?php endif; ?>
  </div>
</main>
<script>jQuery('.summary-sheet table').DataTable({ paging: false, searching: false, info: false, ordering: false });</script>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
