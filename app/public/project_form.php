<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);

$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$project = null;
if ($editId) {
    $project = get_project($pdo, $editId);
    if (!$project || !project_can_edit($project, $user)) {
        flash_set('err', 'ไม่พบโครงงาน หรือคุณไม่มีสิทธิ์แก้ไข');
        redirect('projects.php');
    }
}

// อนุมัติหัวข้อจากข้อเสนอโครงงาน (proposal) — ใช้ฟอร์มเพิ่มโครงงานเดิม พรีฟิลค่าจากหัวข้อที่เลือก
$proposalId = (!$editId && isset($_GET['proposal_id'])) ? (int) $_GET['proposal_id'] : null;
$proposal = null;
$proposalItem = null;
if ($proposalId) {
    if ($user['role'] === 'student') {
        flash_set('err', 'คุณไม่มีสิทธิ์อนุมัติข้อเสนอโครงงาน');
        redirect('proposals.php');
    }
    $proposal = get_proposal($pdo, $proposalId);
    if (!$proposal || $proposal['status'] !== 'pending' || !proposal_can_review($proposal, $user)) {
        flash_set('err', 'ไม่พบข้อเสนอโครงงาน หรือคุณไม่มีสิทธิ์อนุมัติ');
        redirect('proposals.php');
    }
    $itemSeq = (int) ($_GET['item_seq'] ?? 0);
    foreach (get_proposal_items($pdo, $proposalId) as $it) {
        if ((int) $it['seq'] === $itemSeq) { $proposalItem = $it; break; }
    }
    if (!$proposalItem) {
        flash_set('err', 'ไม่พบหัวข้อที่เลือก');
        redirect('proposal_review.php?id=' . $proposalId);
    }
}

$errors = [];
$values = $project ? [
    'title' => $project['title'], 'title_en' => $project['title_en'], 'code' => $project['code'],
    'type' => $project['type'], 'level' => $project['level'], 'group' => $project['student_group'],
    'advisor' => $project['advisor'], 'co_advisor' => $project['co_advisor'],
    'start_date' => $project['start_date'], 'due_date' => $project['due_date'], 'note' => $project['note'],
    'members' => implode("\n", $project['member_names']),
    'member_emails' => implode("\n", array_column(member_accounts($pdo, $project), 'email')),
] : [
    'title' => $proposalItem['title'] ?? '', 'title_en' => '', 'code' => '', 'type' => PROJECT_TYPES[0],
    'level' => $proposal['level'] ?? '', 'group' => $proposal['student_group'] ?? '',
    'advisor' => is_teacher($user) ? $user['name'] : '', 'co_advisor' => '',
    'start_date' => date('Y-m-d'), 'due_date' => '', 'note' => '',
    'members' => '',
    'member_emails' => $proposal['member_emails'] ?? ((!is_admin($user) && !is_teacher($user)) ? $user['email'] : ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (array_keys($values) as $k) $values[$k] = trim($_POST[$k] ?? '');

    $emails = array_values(array_unique(array_filter(array_map(
        fn ($s) => mb_strtolower(trim($s)),
        preg_split('/[\s,;]+/u', $values['member_emails']) ?: []
    ))));
    $memberIds = [];
    $unknown = [];
    if ($emails) {
        $in = implode(',', array_fill(0, count($emails), '?'));
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email IN ($in)");
        $stmt->execute($emails);
        $found = $stmt->fetchAll();
        $foundEmails = array_column($found, 'email');
        $memberIds = array_map('intval', array_column($found, 'id'));
        $unknown = array_values(array_diff($emails, $foundEmails));
    }
    if ($user['role'] === 'student' && !in_array((int) $user['id'], $memberIds, true)) {
        array_unshift($memberIds, (int) $user['id']);
    }
    $memberNames = array_values(array_filter(array_map('trim', explode("\n", $values['members']))));

    if ($values['title'] === '') $errors[] = 'กรุณากรอกชื่อโครงงาน';
    if ($unknown) $errors[] = 'ไม่พบอีเมลในระบบ: ' . implode(', ', $unknown) . ' (สมาชิกต้องสมัครใช้งานก่อน)';
    if (!$memberNames && !$memberIds) $errors[] = 'กรุณาระบุผู้จัดทำอย่างน้อย 1 คน';
    if ($values['advisor'] === '') {
        $errors[] = 'กรุณากรอกครูที่ปรึกษาหลัก';
    } elseif (
        is_teacher($user)
        && !in_array($user['name'], [$values['advisor'], $values['co_advisor']], true)
        && !teaches_level_group($user, $values['level'], $values['group'])
    ) {
        $errors[] = "ต้องระบุ “{$user['name']}” เป็นครูที่ปรึกษาหลักหรือร่วม หรือเลือกระดับชั้น/กลุ่มเรียนที่ท่านเป็นครูผู้สอน มิฉะนั้นคุณจะไม่เห็นโครงงานนี้";
    }
    if ($values['start_date'] && $values['due_date'] && $values['due_date'] < $values['start_date']) {
        $errors[] = 'กำหนดส่งต้องไม่ก่อนวันที่เริ่ม';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            if ($project) {
                $stmt = $pdo->prepare(
                    'UPDATE projects SET title=:title, title_en=:title_en, code=:code, type=:type, level=:level,
                     student_group=:grp, advisor=:advisor, co_advisor=:co_advisor, start_date=:start_date,
                     due_date=:due_date, note=:note, member_names=:member_names WHERE id=:id'
                );
                $stmt->execute([
                    'title' => $values['title'], 'title_en' => $values['title_en'], 'code' => $values['code'],
                    'type' => $values['type'], 'level' => $values['level'], 'grp' => $values['group'],
                    'advisor' => $values['advisor'], 'co_advisor' => $values['co_advisor'],
                    'start_date' => $values['start_date'] ?: null, 'due_date' => $values['due_date'] ?: null,
                    'note' => $values['note'], 'member_names' => json_encode($memberNames, JSON_UNESCAPED_UNICODE),
                    'id' => $project['id'],
                ]);
                $pid = (int) $project['id'];
                $pdo->prepare('DELETE FROM project_members WHERE project_id = ?')->execute([$pid]);
                flash_set('ok', 'บันทึกการแก้ไขแล้ว');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO projects (title, title_en, code, type, level, student_group, advisor, co_advisor,
                     start_date, due_date, note, member_names, steps)
                     VALUES (:title, :title_en, :code, :type, :level, :grp, :advisor, :co_advisor,
                     :start_date, :due_date, :note, :member_names, :steps)'
                );
                $stmt->execute([
                    'title' => $values['title'], 'title_en' => $values['title_en'], 'code' => $values['code'],
                    'type' => $values['type'], 'level' => $values['level'], 'grp' => $values['group'],
                    'advisor' => $values['advisor'], 'co_advisor' => $values['co_advisor'],
                    'start_date' => $values['start_date'] ?: null, 'due_date' => $values['due_date'] ?: null,
                    'note' => $values['note'], 'member_names' => json_encode($memberNames, JSON_UNESCAPED_UNICODE),
                    'steps' => json_encode(array_fill(0, count(STEPS), false)),
                ]);
                $pid = (int) $pdo->lastInsertId();
                if (!empty($_POST['proposal_id']) && !empty($_POST['item_seq'])) {
                    $propId2 = (int) $_POST['proposal_id'];
                    $seq2 = (int) $_POST['item_seq'];
                    $proposal2 = get_proposal($pdo, $propId2);
                    if ($proposal2 && $proposal2['status'] === 'pending' && proposal_can_review($proposal2, $user)) {
                        $pdo->prepare('UPDATE project_proposal_items SET is_selected = 1 WHERE proposal_id = ? AND seq = ?')
                            ->execute([$propId2, $seq2]);
                        $pdo->prepare("UPDATE project_proposals SET status = 'approved', approved_project_id = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                            ->execute([$pid, $user['name'], $propId2]);
                    }
                }
                flash_set('ok', 'เพิ่มโครงงานแล้ว');
            }
            $insMember = $pdo->prepare('INSERT IGNORE INTO project_members (project_id, user_id) VALUES (?, ?)');
            foreach ($memberIds as $uid) $insMember->execute([$pid, $uid]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        redirect('project_detail.php?id=' . $pid);
    }
}

$pageTitle = ($project ? 'แก้ไขโครงงาน' : 'เพิ่มโครงงาน') . ' | ' . site_setting($pdo, 'site_name');
$activeView = 'projects';
require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/app_nav.php';
$advisorNames = known_advisor_names($pdo);
?>
<main class="wrap">
  <div class="panel">
    <h2><?= $project ? '✏️ แก้ไขโครงงาน' : '➕ เพิ่มโครงงาน' ?></h2>
    <form method="post" novalidate style="margin-top:14px">
      <?= csrf_field() ?>
      <?php if ($proposalId && $proposalItem): ?>
        <input type="hidden" name="proposal_id" value="<?= (int) $proposalId ?>">
        <input type="hidden" name="item_seq" value="<?= (int) $proposalItem['seq'] ?>">
        <p class="msg ok" style="margin-bottom:12px">📝 กำลังอนุมัติหัวข้อจากข้อเสนอโครงงาน #<?= (int) $proposalItem['seq'] ?> — กรอกรายละเอียดที่เหลือแล้วกดบันทึกเพื่อสร้างโครงงานและอนุมัติข้อเสนอนี้</p>
      <?php endif; ?>
      <div class="form-grid">
        <label class="f full">ชื่อโครงงาน (ภาษาไทย) <span class="req">*</span>
          <input type="text" name="title" value="<?= esc($values['title']) ?>">
        </label>
        <label class="f full">ชื่อโครงงาน (ภาษาอังกฤษ)
          <input type="text" name="title_en" value="<?= esc($values['title_en']) ?>">
        </label>
        <label class="f">รหัสโครงงาน
          <input type="text" name="code" placeholder="เช่น IT-67-01" value="<?= esc($values['code']) ?>">
        </label>
        <label class="f">ประเภทโครงงาน
          <select name="type">
            <?php foreach (PROJECT_TYPES as $t): ?><option <?= $values['type'] === $t ? 'selected' : '' ?>><?= esc($t) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="f">ระดับชั้น
          <select name="level">
            <option value="">-</option>
            <?php foreach (LEVELS as $lv): ?><option <?= $values['level'] === $lv ? 'selected' : '' ?>><?= esc($lv) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="f">กลุ่มเรียน
          <input type="text" name="group" placeholder="เช่น 1, 2" value="<?= esc($values['group']) ?>">
        </label>
        <label class="f full">อีเมลสมาชิกที่สมัครในระบบแล้ว <small>(1 อีเมลต่อบรรทัด · สมาชิกจะเห็นและแก้ไขโครงงานนี้ได้)</small>
          <textarea name="member_emails" rows="2" placeholder="friend@example.com"><?= esc($values['member_emails']) ?></textarea>
        </label>
        <label class="f full">ชื่อผู้จัดทำที่แสดงในโครงงาน <small>(1 คนต่อบรรทัด · เว้นว่างเพื่อใช้ชื่อจากบัญชีสมาชิก)</small>
          <textarea name="members" rows="3" placeholder="นายสมชาย ใจดี&#10;นางสาวสุดา รักเรียน"><?= esc($values['members']) ?></textarea>
        </label>
        <label class="f">ครูที่ปรึกษาหลัก <span class="req">*</span>
          <input type="text" name="advisor" list="advisorList" value="<?= esc($values['advisor']) ?>">
        </label>
        <label class="f">ครูที่ปรึกษาร่วม
          <input type="text" name="co_advisor" list="advisorList" value="<?= esc($values['co_advisor']) ?>">
        </label>
        <datalist id="advisorList">
          <?php foreach ($advisorNames as $a): ?><option value="<?= esc($a) ?>"><?php endforeach; ?>
        </datalist>
        <label class="f">วันที่เริ่ม
          <input type="date" name="start_date" value="<?= esc($values['start_date']) ?>">
        </label>
        <label class="f">กำหนดส่ง
          <input type="date" name="due_date" value="<?= esc($values['due_date']) ?>">
        </label>
        <label class="f full">รายละเอียด / เครื่องมือที่ใช้
          <textarea name="note" rows="3"><?= esc($values['note']) ?></textarea>
        </label>
      </div>
      <?php if ($errors): ?><div class="msg err" style="margin-top:12px">🚫 <?= esc(implode(' · ', $errors)) ?></div><?php endif; ?>
      <div class="form-actions">
        <a class="btn ghost" href="<?= $project ? 'project_detail.php?id=' . (int) $project['id'] : 'projects.php' ?>">ยกเลิก</a>
        <button class="btn" type="submit">💾 บันทึก</button>
      </div>
    </form>
  </div>
</main>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
