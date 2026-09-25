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

function project_can_edit(array $project, array $user): bool
{
    if ($user['role'] === 'admin') return true;
    if (in_array((int) $user['id'], $project['member_ids'], true)) return true;
    return project_advises($project, $user);
}

function project_can_grade(array $project, array $user): bool
{
    return $user['role'] === 'admin' || project_advises($project, $user);
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
