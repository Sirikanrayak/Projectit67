<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);
if ($user['role'] !== 'student') {
    flash_set('err', 'เฉพาะนักเรียน/นักศึกษาเท่านั้นที่เสนอชื่อโครงงานได้');
    redirect('dashboard.php');
}

$pendingStmt = $pdo->prepare("SELECT id FROM project_proposals WHERE student_id = ? AND status = 'pending'");
$pendingStmt->execute([$user['id']]);
if ($pendingStmt->fetch()) {
    flash_set('err', 'คุณมีข้อเสนอโครงงานที่รอการพิจารณาอยู่แล้ว กรุณารอผลก่อนเสนอใหม่');
    redirect('proposals.php');
}

$errors = [];
$values = [
    'level' => $user['level'] ?: '', 'group' => $user['student_group'] ?: '',
    'member_emails' => $user['email'],
    'items' => array_fill(0, 7, ['title' => '', 'method' => '', 'benefit' => '']),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $values['level'] = trim($_POST['level'] ?? '');
    $values['group'] = trim($_POST['group'] ?? '');
    $values['member_emails'] = trim($_POST['member_emails'] ?? '');

    $items = [];
    for ($i = 1; $i <= 7; $i++) {
        $title = trim($_POST["title$i"] ?? '');
        $method = trim($_POST["method$i"] ?? '');
        $benefit = trim($_POST["benefit$i"] ?? '');
        $values['items'][$i - 1] = ['title' => $title, 'method' => $method, 'benefit' => $benefit];
        if ($title !== '') $items[] = ['seq' => $i, 'title' => $title, 'method' => $method, 'benefit' => $benefit];
    }

    if ($values['level'] === '' || $values['group'] === '') $errors[] = 'กรุณาระบุระดับชั้นและกลุ่มเรียน';
    if (!$items) $errors[] = 'กรุณาเสนอชื่อโครงงานอย่างน้อย 1 หัวข้อ';

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO project_proposals (student_id, level, student_group, member_emails) VALUES (?, ?, ?, ?)')
                ->execute([$user['id'], $values['level'], $values['group'], $values['member_emails']]);
            $propId = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO project_proposal_items (proposal_id, seq, title, method, benefit) VALUES (?, ?, ?, ?, ?)');
            foreach ($items as $it) $ins->execute([$propId, $it['seq'], $it['title'], $it['method'], $it['benefit']]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        flash_set('ok', 'ส่งข้อเสนอโครงงานแล้ว รอครูพิจารณา');
        redirect('proposals.php');
    }
}

$pageTitle = 'เสนอชื่อโครงงาน | ' . site_setting($pdo, 'site_name');
$activeView = 'proposals';
require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/app_nav.php';
?>
<main class="wrap">
  <div class="panel">
    <h2>📝 เสนอชื่อโครงงาน</h2>
    <p style="color:var(--muted);margin-top:-6px">เสนอหัวข้อโครงงานที่สนใจได้สูงสุด 7 หัวข้อ ให้ครูพิจารณาเลือกหัวข้อที่เหมาะสม</p>
    <form method="post" novalidate style="margin-top:14px">
      <?= csrf_field() ?>
      <?php if ($errors): ?><div class="msg err" style="margin-bottom:12px">🚫 <?= esc(implode(' · ', $errors)) ?></div><?php endif; ?>
      <div class="form-grid">
        <label class="f">ระดับชั้น <span class="req">*</span>
          <select name="level">
            <option value="">-</option>
            <?php foreach (LEVELS as $lv): ?><option <?= $values['level'] === $lv ? 'selected' : '' ?>><?= esc($lv) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="f">กลุ่มเรียน <span class="req">*</span>
          <input type="text" name="group" value="<?= esc($values['group']) ?>">
        </label>
        <label class="f full">อีเมลสมาชิกร่วม (ถ้ามี) <small>(1 อีเมลต่อบรรทัด · อีเมลของคุณเองใส่ไว้แล้ว)</small>
          <textarea name="member_emails" rows="2"><?= esc($values['member_emails']) ?></textarea>
        </label>
      </div>

      <?php foreach ($values['items'] as $i => $it): $n = $i + 1; ?>
        <div class="panel" style="margin:14px 0;background:var(--gray-bg, #f8f8f8)">
          <strong><?= $n ?>. ชื่อโครงงาน<?= $n === 1 ? ' <span class="req">*</span>' : '' ?></strong>
          <div class="form-grid" style="margin-top:8px">
            <label class="f full">ชื่อโครงงาน
              <input type="text" name="title<?= $n ?>" value="<?= esc($it['title']) ?>">
            </label>
            <label class="f full">จะทำการศึกษาอย่างไร
              <textarea name="method<?= $n ?>" rows="2"><?= esc($it['method']) ?></textarea>
            </label>
            <label class="f full">ผลที่คาดว่าจะได้รับ
              <textarea name="benefit<?= $n ?>" rows="2"><?= esc($it['benefit']) ?></textarea>
            </label>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="form-actions">
        <a class="btn ghost" href="proposals.php">ยกเลิก</a>
        <button class="btn" type="submit">📤 ส่งข้อเสนอโครงงาน</button>
      </div>
    </form>
  </div>
</main>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
