<?php
// items.php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $item_name = trim($_POST['item_name']);
        $item_type = $_POST['item_type'] ?? 'hourly'; 
        $rate = ($item_type === 'fixed' || $item_type === 'deduction') ? 1 : (int)$_POST['default_hourly_rate'];

        $stmt = $pdo->prepare("INSERT INTO salary_items (user_id, item_name, default_hourly_rate, item_type) VALUES (:user_id, :item_name, :rate, :type)");
        $stmt->execute([':user_id' => $user_id, ':item_name' => $item_name, ':rate' => $rate, ':type' => $item_type]);
        $message = "項目「{$item_name}」を追加しました。";
    } elseif ($_POST['action'] === 'edit') {
        $edit_id = (int)$_POST['item_id'];
        $edit_name = trim($_POST['item_name']);
        if (isset($_POST['default_hourly_rate'])) {
            $edit_rate = (int)$_POST['default_hourly_rate'];
            $stmt = $pdo->prepare("UPDATE salary_items SET item_name = :name, default_hourly_rate = :rate WHERE id = :id AND user_id = :user_id");
            $stmt->execute([':name' => $edit_name, ':rate' => $edit_rate, ':id' => $edit_id, ':user_id' => $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE salary_items SET item_name = :name WHERE id = :id AND user_id = :user_id");
            $stmt->execute([':name' => $edit_name, ':id' => $edit_id, ':user_id' => $user_id]);
        }
        $message = "項目を更新しました。";
    } elseif ($_POST['action'] === 'delete') {
        $delete_id = (int)$_POST['item_id'];
        try {
            $stmt = $pdo->prepare("UPDATE salary_items SET is_deleted = TRUE WHERE id = :id AND user_id = :user_id");
            $stmt->execute([':id' => $delete_id, ':user_id' => $user_id]);
            $message = "項目を削除しました。";
        } catch (PDOException $e) {
            $error_message = "削除に失敗しました: " . $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare("
    SELECT * FROM salary_items 
    WHERE user_id = :user_id AND is_deleted = FALSE 
    ORDER BY CASE WHEN item_type = 'hourly' THEN 1 WHEN item_type = 'fixed' THEN 2 ELSE 3 END ASC, id ASC
");
$stmt->execute([':user_id' => $user_id]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>項目管理 - 給与計算アプリ</title>
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
                    <li class="nav-item"><a class="nav-link" href="history.php"><i class="bi bi-clock-history me-1"></i> 履歴・詳細</a></li>
                    <li class="nav-item"><a class="nav-link active" href="items.php"><i class="bi bi-gear me-1"></i> 項目管理</a></li>
                </ul>
                <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>ログアウト</a>
            </div>
        </div>
    </nav>

    <div class="container" style="max-width: 800px;">
        <h2 class="h4 mb-3 fw-bold text-secondary"><i class="bi bi-gear-fill me-2 text-primary"></i>給与項目の設定</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-plus-circle text-success me-2"></i>新しい項目の追加</h5>
            </div>
            <div class="card-body bg-light">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="item_type" value="hourly" id="type1" checked onchange="toggleRateInput()">
                            <label class="form-check-label" for="type1"><span class="badge bg-primary me-1">時給</span> 時間を入力して計算</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="item_type" value="fixed" id="type2" onchange="toggleRateInput()">
                            <label class="form-check-label" for="type2"><span class="badge bg-warning text-dark me-1">固定手当</span> 給与登録時に金額を直接入力</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="item_type" value="deduction" id="type3" onchange="toggleRateInput()">
                            <label class="form-check-label" for="type3"><span class="badge bg-danger me-1">控除</span> 税金など引かれる金額</label>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <input type="text" name="item_name" class="form-control" placeholder="項目名 (例: 授業、通勤手当)" required>
                        </div>
                        <div class="col-md-6" id="rate-input-container">
                            <div class="input-group">
                                <input type="number" id="hourly-rate-input" name="default_hourly_rate" class="form-control" placeholder="金額 / 時給" required>
                                <span class="input-group-text bg-white">円</span>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100 fw-bold"><i class="bi bi-node-plus me-1"></i>追加する</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-5">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">種類</th>
                            <th>項目名</th>
                            <th>金額/時給</th>
                            <th class="text-end pe-3">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="ps-3">
                                <?php if ($item['item_type'] === 'fixed'): ?>
                                    <span class="badge bg-warning text-dark">固定手当</span>
                                <?php elseif ($item['item_type'] === 'deduction'): ?>
                                    <span class="badge bg-danger">控除</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">時給</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold"><?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($item['item_type'] === 'fixed' || $item['item_type'] === 'deduction'): ?>
                                    <span class="text-muted small">登録時に入力</span>
                                <?php else: ?>
                                    <?= number_format($item['default_hourly_rate']) ?> 円
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= $item['id'] ?>, '<?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?>', <?= $item['default_hourly_rate'] ?>, '<?= $item['item_type'] ?? 'hourly' ?>')">
                                        <i class="bi bi-pencil-square"></i><span class="d-none d-sm-inline ms-1">編集</span>
                                    </button>
                                    <form method="POST" onsubmit="return confirm('「<?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?>」を削除しますか？\n(過去のデータには影響しません)');" style="margin: 0;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i><span class="d-none d-sm-inline ms-1">削除</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">登録されている項目はありません。</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>項目の編集</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p id="edit_type_label" class="mb-3 bg-light p-2 rounded text-center"></p>
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="item_id" id="edit_item_id">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">項目名</label>
                            <input type="text" name="item_name" id="edit_item_name" class="form-control" required>
                        </div>
                        <div class="mb-3" id="edit_rate_container">
                            <label class="form-label fw-bold">時給</label>
                            <div class="input-group">
                                <input type="number" name="default_hourly_rate" id="edit_rate" class="form-control">
                                <span class="input-group-text bg-white">円</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>更新する</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleRateInput() {
            const type = document.querySelector('input[name="item_type"]:checked').value;
            const container = document.getElementById('rate-input-container');
            const rateInput = document.getElementById('hourly-rate-input');

            if (type === 'fixed' || type === 'deduction') {
                container.style.display = 'none';
                rateInput.required = false;
            } else {
                container.style.display = 'block';
                rateInput.required = true;
            }
        }
        toggleRateInput();

        const editModalEl = document.getElementById('editModal');
        const editModal = new bootstrap.Modal(editModalEl);

        function openEditModal(id, name, rate, type) {
            document.getElementById('edit_item_id').value = id;
            document.getElementById('edit_item_name').value = name;
            const rateContainer = document.getElementById('edit_rate_container');
            const rateInput = document.getElementById('edit_rate');
            const typeLabel = document.getElementById('edit_type_label');

            if (type === 'fixed' || type === 'deduction') {
                rateContainer.style.display = 'none';
                rateInput.required = false;
                rateInput.disabled = true;
                const badge = type === 'fixed' ? '<span class="badge bg-warning text-dark">固定手当</span>' : '<span class="badge bg-danger">控除</span>';
                typeLabel.innerHTML = '種類: ' + badge;
            } else {
                rateContainer.style.display = 'block';
                rateInput.value = rate;
                rateInput.required = true;
                rateInput.disabled = false;
                typeLabel.innerHTML = '種類: <span class="badge bg-primary">時給</span>';
            }
            editModal.show();
        }
    </script>
</body>
</html>