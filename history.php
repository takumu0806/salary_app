<?php
// history.php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delete_month_id = (int)$_POST['month_id'];
    $pdo->prepare("DELETE FROM salary_details WHERE monthly_salary_id = ?")->execute([$delete_month_id]);
    $pdo->prepare("DELETE FROM monthly_salaries WHERE id = ? AND user_id = ?")->execute([$delete_month_id, $user_id]);
    header("Location: history.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT ms.id AS month_id, ms.target_year, ms.target_month, ms.total_amount, sd.hours_worked, sd.calculated_amount, si.item_name, si.item_type
    FROM monthly_salaries ms
    LEFT JOIN salary_details sd ON ms.id = sd.monthly_salary_id
    LEFT JOIN salary_items si ON sd.salary_item_id = si.id
    WHERE ms.user_id = :user_id
    ORDER BY ms.target_year DESC, ms.target_month DESC, CASE WHEN si.item_type = 'hourly' THEN 1 WHEN si.item_type = 'fixed' THEN 2 ELSE 3 END ASC, si.id ASC
");
$stmt->execute([':user_id' => $user_id]);
$results = $stmt->fetchAll();

$history = [];
foreach ($results as $row) {
    $month_key = $row['target_year'] . '年' . $row['target_month'] . '月';
    if (!isset($history[$month_key])) {
        $history[$month_key] = [
            'month_id' => $row['month_id'],
            'target_year' => $row['target_year'],
            'target_month' => $row['target_month'],
            'total_amount' => $row['total_amount'],
            'details' => []
        ];
    }
    if ($row['item_name']) {
        $history[$month_key]['details'][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>履歴・詳細 - 給与計算アプリ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php"><i class="bi bi-wallet2 me-2"></i>給与アプリ</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-house-door me-1"></i> ホーム</a></li>
                    <li class="nav-item"><a class="nav-link" href="add_salary.php"><i class="bi bi-pencil-square me-1"></i> 給与登録</a></li>
                    <li class="nav-item"><a class="nav-link active" href="history.php"><i class="bi bi-clock-history me-1"></i> 履歴・詳細</a></li>
                    <li class="nav-item"><a class="nav-link" href="items.php"><i class="bi bi-gear me-1"></i> 項目管理</a></li>
                </ul>
                <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>ログアウト</a>
            </div>
        </div>
    </nav>

    <div class="container" style="max-width: 800px;">
        <h2 class="h4 mb-4 fw-bold text-secondary"><i class="bi bi-clock-history me-2 text-primary"></i>給与履歴・詳細</h2>

        <?php if (empty($history)): ?>
            <div class="alert alert-secondary text-center p-5 shadow-sm border-0">
                <i class="bi bi-folder2-open display-4 text-muted mb-3 d-block"></i>
                <p class="mb-0 fs-5 text-muted">給与データがまだ登録されていません。</p>
            </div>
        <?php else: ?>
            <?php foreach ($history as $month_title => $data): ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap py-3">
                        <div class="d-flex align-items-center gap-2 mb-2 mb-sm-0">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-check me-2"></i><?= $month_title ?></h5>
                            <a href="add_salary.php?year=<?= $data['target_year'] ?>&month=<?= $data['target_month'] ?>" class="btn btn-sm btn-light text-primary ms-2 fw-bold">
                                <i class="bi bi-pencil-square"></i><span class="d-none d-sm-inline ms-1">編集</span>
                            </a>
                            <form method="POST" onsubmit="return confirm('<?= $month_title ?>の給与データを完全に削除しますか？');" class="m-0">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="month_id" value="<?= $data['month_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger fw-bold border border-light">
                                    <i class="bi bi-trash"></i><span class="d-none d-sm-inline ms-1">削除</span>
                                </button>
                            </form>
                        </div>
                        <div class="fs-4 fw-bold">
                            <span class="fs-6 opacity-75 fw-normal me-1">支給額:</span><?= number_format($data['total_amount']) ?> <span class="fs-6 fw-normal">円</span>
                        </div>
                    </div>
                    
                    <div class="card-body p-0">
                        <?php if (empty($data['details'])): ?>
                            <p class="text-center text-muted p-4 mb-0">詳細データがありません。</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-borderless table-striped mb-0 align-middle">
                                    <thead class="table-light border-bottom">
                                        <tr>
                                            <th class="ps-4">種類</th>
                                            <th>項目名</th>
                                            <th>時間 / 回数</th>
                                            <th class="text-end pe-4">金額</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data['details'] as $detail): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <?php if ($detail['item_type'] === 'fixed'): ?>
                                                    <span class="badge bg-warning text-dark">固定手当</span>
                                                <?php elseif ($detail['item_type'] === 'deduction'): ?>
                                                    <span class="badge bg-danger">控除</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">時給</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold text-secondary"><?= htmlspecialchars($detail['item_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-muted">
                                                <?php if ($detail['item_type'] === 'fixed' || $detail['item_type'] === 'deduction'): ?>
                                                    -
                                                <?php else: ?>
                                                    <?= floatval($detail['hours_worked']) ?> 時間
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($detail['item_type'] === 'deduction'): ?>
                                                <td class="text-end pe-4 text-danger fw-bold">- <?= number_format(abs($detail['calculated_amount'])) ?> 円</td>
                                            <?php else: ?>
                                                <td class="text-end pe-4 fw-bold"><?= number_format($detail['calculated_amount']) ?> 円</td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>