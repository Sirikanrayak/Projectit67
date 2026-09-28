<?php
declare(strict_types=1);

function decode_project(array $row): array
{
    $row['steps'] = decode_json_array($row['steps'] ?? null);
    if (count($row['steps']) < count(STEPS)) {
        $row['steps'] = array_pad($row['steps'], count(STEPS), false);
    }
    $row['evaluation'] = $row['evaluation'] ? json_decode($row['evaluation'], true) : null;
    $row['member_names'] = decode_json_array($row['member_names'] ?? null);
    $row['member_ids'] = !empty($row['member_ids_raw'])
        ? array_map('intval', explode(',', $row['member_ids_raw']))
        : [];
    return $row;
}

const PROJECT_SELECT_SQL = 'SELECT p.*, GROUP_CONCAT(pm.user_id) AS member_ids_raw
    FROM projects p
    LEFT JOIN project_members pm ON pm.project_id = p.id';

function get_all_projects(PDO $pdo): array
{
    $rows = $pdo->query(PROJECT_SELECT_SQL . ' GROUP BY p.id ORDER BY p.created_at DESC')->fetchAll();
    return array_map('decode_project', $rows);
}

function get_project(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(PROJECT_SELECT_SQL . ' WHERE p.id = :id GROUP BY p.id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ? decode_project($row) : null;
}

function project_advises(array $project, array $user): bool
{
    if ($user['role'] !== 'teacher') return false;
    return $project['advisor'] === $user['name'] || $project['co_advisor'] === $user['name'];
}

// ดึงรายการ (ระดับชั้น/กลุ่มเรียน) ที่ครูคนนี้ได้รับมอบหมายให้เป็น "ครูผู้สอนโครงงาน"
function teacher_assignments(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT level, student_group FROM teacher_assignments WHERE user_id = ? ORDER BY level, student_group');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function save_teacher_assignments(PDO $pdo, int $userId, array $pairs): void
{
    $pdo->prepare('DELETE FROM teacher_assignments WHERE user_id = ?')->execute([$userId]);
    $ins = $pdo->prepare('INSERT IGNORE INTO teacher_assignments (user_id, level, student_group) VALUES (?, ?, ?)');
    foreach ($pairs as $p) {
        if ($p['level'] === '' || $p['student_group'] === '') continue;
        $ins->execute([$userId, $p['level'], $p['student_group']]);
    }
}

// ครูผู้สอนโครงงานมีสิทธิ์เหนือโครงงานทุกชิ้นในระดับชั้น/กลุ่มเรียนที่ได้รับมอบหมาย ไม่ว่าจะเป็นครูที่ปรึกษาเองหรือไม่
function project_taught_by(array $project, array $user): bool
{
    if ($user['role'] !== 'teacher' || empty($user['instructor_of'])) return false;
    foreach ($user['instructor_of'] as $a) {
        if ($a['level'] === $project['level'] && $a['student_group'] === $project['student_group']) return true;
    }
    return false;
}

// ครูผู้สอนโครงงานสำหรับระดับชั้น/กลุ่มเรียนที่ระบุ (ใช้ตอนเพิ่มโครงงานใหม่ ก่อนมี $project จริง)
function teaches_level_group(array $user, string $level, string $group): bool
{
    if ($user['role'] !== 'teacher' || empty($user['instructor_of'])) return false;
    foreach ($user['instructor_of'] as $a) {
        if ($a['level'] === $level && $a['student_group'] === $group) return true;
    }
    return false;
}

function project_can_edit(array $project, array $user): bool
{
    if ($user['role'] === 'admin') return true;
    if (in_array((int) $user['id'], $project['member_ids'], true)) return true;
    if (project_advises($project, $user)) return true;
    return project_taught_by($project, $user);
}

function project_can_grade(array $project, array $user): bool
{
    return $user['role'] === 'admin' || project_advises($project, $user) || project_taught_by($project, $user);
}

function project_can_delete(array $project, array $user): bool
{
    return $user['role'] === 'admin' || project_advises($project, $user) || project_taught_by($project, $user);
}

function visible_projects(PDO $pdo, array $user): array
{
    $all = get_all_projects($pdo);
    if ($user['role'] === 'admin') return $all;
    return array_values(array_filter($all, fn ($p) => project_can_edit($p, $user)));
}

function due_soon_projects(PDO $pdo, array $user): array
{
    $list = array_filter(visible_projects($pdo, $user), function ($p) {
        if (project_status($p) === 'done') return false;
        if (!$p['due_date']) return false;
        $dl = days_left($p['due_date']);
        return $dl !== null && $dl <= DUE_SOON_DAYS;
    });
    usort($list, fn ($a, $b) => strcmp($a['due_date'], $b['due_date']));
    return array_values($list);
}

function member_display_names(PDO $pdo, array $project): array
{
    if (!empty($project['member_names'])) return $project['member_names'];
    if (empty($project['member_ids'])) return [];
    $in = implode(',', array_fill(0, count($project['member_ids']), '?'));
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id IN ($in)");
    $stmt->execute($project['member_ids']);
    return array_column($stmt->fetchAll(), 'name');
}

function member_accounts(PDO $pdo, array $project): array
{
    if (empty($project['member_ids'])) return [];
    $in = implode(',', array_fill(0, count($project['member_ids']), '?'));
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id IN ($in)");
    $stmt->execute($project['member_ids']);
    return $stmt->fetchAll();
}

function get_project_logs(PDO $pdo, int $projectId): array
{
    $stmt = $pdo->prepare('SELECT * FROM project_logs WHERE project_id = ? ORDER BY log_date DESC, id DESC');
    $stmt->execute([$projectId]);
    return $stmt->fetchAll();
}

function get_project_files(PDO $pdo, int $projectId): array
{
    $stmt = $pdo->prepare('SELECT * FROM project_files WHERE project_id = ? ORDER BY uploaded_at DESC');
    $stmt->execute([$projectId]);
    return $stmt->fetchAll();
}

function get_qc_rows(PDO $pdo, int $projectId): array
{
    $stmt = $pdo->prepare('SELECT * FROM project_qc WHERE project_id = ?');
    $stmt->execute([$projectId]);
    return array_column($stmt->fetchAll(), null, 'step_key');
}

function qc_can_sign(array $project, array $user, string $role): bool
{
    if ($user['role'] === 'admin') return true;
    if ($role === 'advisor') return project_advises($project, $user);
    if ($role === 'instructor') return project_taught_by($project, $user);
    if ($role === 'any') return project_advises($project, $user) || project_taught_by($project, $user);
    return false;
}

function qc_all_passed(PDO $pdo, int $projectId): bool
{
    $rows = get_qc_rows($pdo, $projectId);
    foreach (QC_STEPS as $step) {
        if (($rows[$step['key']]['status'] ?? null) !== 'pass') return false;
    }
    return true;
}

// ---- ข้อเสนอชื่อโครงงาน (proposals) ----

function get_student_proposals(PDO $pdo, int $studentId): array
{
    $stmt = $pdo->prepare('SELECT * FROM project_proposals WHERE student_id = ? ORDER BY created_at DESC');
    $stmt->execute([$studentId]);
    return $stmt->fetchAll();
}

function get_proposal(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM project_proposals WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_proposal_items(PDO $pdo, int $proposalId): array
{
    $stmt = $pdo->prepare('SELECT * FROM project_proposal_items WHERE proposal_id = ? ORDER BY seq');
    $stmt->execute([$proposalId]);
    return $stmt->fetchAll();
}

function proposal_can_review(array $proposal, array $user): bool
{
    if ($user['role'] === 'admin') return true;
    return teaches_level_group($user, $proposal['level'], $proposal['student_group']);
}

function get_pending_proposals_for_reviewer(PDO $pdo, array $user): array
{
    if ($user['role'] === 'admin') {
        return $pdo->query(
            "SELECT pp.*, u.name AS student_name FROM project_proposals pp
             JOIN users u ON u.id = pp.student_id
             WHERE pp.status = 'pending' ORDER BY pp.created_at"
        )->fetchAll();
    }
    if (empty($user['instructor_of'])) return [];
    $rows = [];
    $stmt = $pdo->prepare(
        "SELECT pp.*, u.name AS student_name FROM project_proposals pp
         JOIN users u ON u.id = pp.student_id
         WHERE pp.status = 'pending' AND pp.level = ? AND pp.student_group = ? ORDER BY pp.created_at"
    );
    foreach ($user['instructor_of'] as $a) {
        $stmt->execute([$a['level'], $a['student_group']]);
        foreach ($stmt->fetchAll() as $r) $rows[$r['id']] = $r;
    }
    return array_values($rows);
}

// ---- รายงานความก้าวหน้าเป็นรอบทางการ (progress rounds) ----

function get_progress_rounds(PDO $pdo, int $projectId): array
{
    $stmt = $pdo->prepare('SELECT * FROM project_progress_rounds WHERE project_id = ?');
    $stmt->execute([$projectId]);
    return array_column($stmt->fetchAll(), null, 'round_no');
}

function get_defense_request(PDO $pdo, int $projectId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM project_defense_requests WHERE project_id = ?');
    $stmt->execute([$projectId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function rename_advisor_everywhere(PDO $pdo, string $oldName, string $newName): void
{
    if ($oldName === '' || $oldName === $newName) return;
    $pdo->prepare('UPDATE projects SET advisor = :new WHERE advisor = :old')->execute(['new' => $newName, 'old' => $oldName]);
    $pdo->prepare('UPDATE projects SET co_advisor = :new WHERE co_advisor = :old')->execute(['new' => $newName, 'old' => $oldName]);
}

function known_advisor_names(PDO $pdo): array
{
    $names = $pdo->query("SELECT name FROM users WHERE role = 'teacher' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    $advisors = $pdo->query('SELECT DISTINCT advisor FROM projects WHERE advisor <> ""')->fetchAll(PDO::FETCH_COLUMN);
    $coAdvisors = $pdo->query('SELECT DISTINCT co_advisor FROM projects WHERE co_advisor <> ""')->fetchAll(PDO::FETCH_COLUMN);
    $all = array_unique(array_merge($names, $advisors, $coAdvisors));
    sort($all, SORT_LOCALE_STRING);
    return $all;
}
