<?php
// add_salary.php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// --- 保存処理の最後にリダイレクトするため、ここでの計算用関数 ---
function customNumber($number) {
    if ($number <= 0) {
        return 0;
    }
    // PHPの正しい文法に修正しました
    $int_part = floor($number);
    $decimal_part = $number - $int_part;
    $decimal_part = round($decimal_part, 3);
    return ($decimal_part >= 0.1) ? ceil($number) : $int_part - 1;
}

$is_edit_mode = isset($_GET['year']) && isset($_GET['month']);
$target_year = $is_edit_mode ? (int)$_GET['year'] : (int)date('Y');
$target_month = $is_edit_mode ? (int)$_GET['month'] : (int)date('n');

$existing_details = [];
$existing_ms_id = null;

if ($is_edit_mode) {
    $stmt_exist = $pdo->prepare("SELECT id FROM monthly_salaries WHERE user_id = ? AND target_year = ? AND target_month = ?");
    $stmt_exist->execute([$user_id, $target_year, $target_month]);
    $existing_ms_id = $stmt_exist->fetchColumn();

    if ($existing_ms_id) {
        $stmt_det = $pdo->prepare("SELECT sd.salary_item_id, sd.hours_worked, sd.calculated_amount, si.item_type FROM salary_details sd JOIN salary_items si ON sd.salary_item_id = si.id WHERE sd.monthly_salary_id = ?");
        $stmt_det->execute([$existing_ms_id]);
        while ($row = $stmt_det->fetch()) {
            $existing_details[$row['salary_item_id']] = ($row['item_type'] === 'fixed' || $row['item_type'] === 'deduction') ? abs($row['calculated_amount']) : $row['hours_worked'];
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM salary_items WHERE user_id = :user_id AND is_deleted = FALSE ORDER BY CASE WHEN item_type = 'hourly' THEN 1 WHEN item_type = 'fixed' THEN 2 ELSE 3 END ASC, id ASC");
$stmt->execute([':user_id' => $user_id]);
$items = $stmt->fetchAll();

if (!empty($items) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_year = (int)$_POST['target_year'];
    $target_month = (int)$_POST['target_month'];
    $plus_total_amount = 0;
    $minus_total_amount = 0;

    try {
        $pdo->beginTransaction();
        $stmt_month = $pdo->prepare("INSERT INTO monthly_salaries (user_id, target_year, target_month, total_amount) VALUES (:user_id, :year, :month, 0) ON CONFLICT (user_id, target_year, target_month) DO UPDATE SET total_amount = 0 RETURNING id");
        $stmt_month->execute([':user_id' => $user_id, ':year' => $target_year, ':month' => $target_month]);
        $monthly_salary_id = $stmt_month->fetchColumn();

        $pdo->prepare("DELETE FROM salary_details WHERE monthly_salary_id = ?")->execute([$monthly_salary_id]);
        $stmt_detail = $pdo->prepare("INSERT INTO salary_details (monthly_salary_id, salary_item_id, hours_worked, calculated_amount) VALUES (?, ?, ?, ?)");
        
        foreach ($_POST['hours'] as $item_id => $input_val) {
            if ($input_val !== '' && $input_val > 0) {
                $rate_stmt = $pdo->prepare("SELECT default_hourly_rate, item_type FROM salary_items WHERE id = ?");
                $rate_stmt->execute([$item_id]);
                $item_data = $rate_stmt->fetch();
                
                $rate = $item_data['default_hourly_rate'];
                $item_type = $item_data['item_type'] ?? 'hourly';
                
                if ($item_type === 'fixed') {
                    $amount = $input_val;
                    $db_hours_worked = 1;
                    $plus_total_amount += $amount;
                    $db_amount = (int)round($amount); 
                } elseif ($item_type === 'deduction') {
                    $amount = $input_val;
                    $db_hours_worked = 1;
                    $minus_total_amount += $amount;
                    $db_amount = -(int)round($amount); 
                } else {
                    $amount = ceil($rate * $input_val);
                    $db_hours_worked = $input_val;
                    $plus_total_amount += $amount;
                    $db_amount = (int)$amount; 
                }
                $stmt_detail->execute([$monthly_salary_id, $item_id, $db_hours_worked, $db_amount]);
            }
        }

        $final_total_amount = $plus_total_amount - $minus_total_amount;
        $pdo->prepare("UPDATE monthly_salaries SET total_amount = ? WHERE id = ?")->execute([$final_total_amount, $monthly_salary_id]);
        
        $pdo->commit();
        
        // ★ 保存・更新が完了したらダッシュボードへ戻る
        header("Location: dashboard.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "エラーが発生しました: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>給与登録 - 給与計算アプリ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .custom-section-title { border-left: 4px solid #0d6efd; padding-left: 10px; margin: 30px 0 15px; }
        .custom-section-deduct { border-left-color: #dc3545; }
        .form-padding-bottom { padding-bottom: 120px !important; }
        .floating-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid #dee2e6;
            box-shadow: 0 -4px 10px rgba(0,0,0,0.05);
            z-index: 1030;
            padding-bottom: env(safe-area-inset-bottom);
        }
    </style>
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
                    <li class="nav-item"><a class="nav-link active" href="add_salary.php"><i class="bi bi-pencil-square me-1"></i> 給与登録</a></li>
                    <li class="nav-item"><a class="nav-link" href="history.php"><i class="bi bi-clock-history me-1"></i> 履歴・詳細</a></li>
                    <li class="nav-item"><a class="nav-link" href="items.php"><i class="bi bi-gear me-1"></i> 項目管理</a></li>
                </ul>
                <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>ログアウト</a>
            </div>
        </div>
    </nav>

    <div class="container" style="max-width: 700px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0 fw-bold text-secondary">給与の計算と登録</h2>
        </div>

        <?php if (!empty($items)): ?>
            <?php if ($message): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="card shadow-sm border-0 form-padding-bottom">
                <div class="card-body p-3 p-sm-4">
                    <div class="bg-light p-3 rounded mb-4 border border-1">
                        <div class="row align-items-center">
                            <div class="col-auto"><label class="col-form-label fw-bold"><i class="bi bi-calendar-event me-2"></i>対象年月:</label></div>
                            <?php if ($is_edit_mode): ?>
                                <div class="col-auto"><span class="fs-5 fw-bold"><?= $target_year ?>年 <?= $target_month ?>月</span></div>
                                <div class="col-auto"><span class="badge bg-danger"><i class="bi bi-pencil me-1"></i>編集モード</span></div>
                                <input type="hidden" name="target_year" value="<?= $target_year ?>">
                                <input type="hidden" name="target_month" value="<?= $target_month ?>">
                            <?php else: ?>
                                <div class="col-auto">
                                    <div class="input-group input-group-sm">
                                        <select name="target_year" class="form-select" required style="min-width: 90px;">
                                            <?php 
                                            $current_yr = (int)date('Y');
                                            for ($y = 2000; $y <= $current_yr; $y++): 
                                            ?>
                                                <option value="<?= $y ?>" <?= $y === $target_year ? 'selected' : '' ?>><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                        <span class="input-group-text bg-white">年</span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <div class="input-group input-group-sm">
                                        <select name="target_month" class="form-select" required style="min-width: 70px;">
                                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                                <option value="<?= $m ?>" <?= $m === $target_month ? 'selected' : '' ?>><?= $m ?></option>
                                            <?php endfor; ?>
                                        </select>
                                        <span class="input-group-text bg-white">月</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h5 class="custom-section-title"><i class="bi bi-plus-circle text-primary me-2"></i>勤務時間・手当 (プラス)</h5>
                    <?php foreach ($items as $item): ?>
                        <?php if (!isset($item['item_type']) || $item['item_type'] !== 'deduction'): ?>
                        <?php $default_val = isset($existing_details[$item['id']]) ? floatval($existing_details[$item['id']]) : ''; ?>
                        <div class="row align-items-center mb-3 pb-3 border-bottom border-light">
                            <div class="col-sm-5 mb-2 mb-sm-0">
                                <strong class="fs-6"><?= htmlspecialchars($item['item_name']) ?></strong> 
                                <span class="badge <?= $item['item_type'] === 'fixed' ? 'bg-warning text-dark' : 'bg-primary' ?> ms-1">
                                    <?= $item['item_type'] === 'fixed' ? '固定' : '時給' ?>
                                </span><br>
                                <?php if($item['item_type'] !== 'fixed'): ?>
                                    <small class="text-muted">単価: <span class="rate" data-item-id="<?= $item['id'] ?>" data-type="<?= $item['item_type'] ?? 'hourly' ?>"><?= $item['default_hourly_rate'] ?></span>円</small>
                                <?php else: ?>
                                    <small class="text-muted">金額を直接入力</small>
                                    <span class="rate" data-item-id="<?= $item['id'] ?>" data-type="fixed" style="display:none;">1</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-sm-7">
                                <div class="d-flex align-items-center justify-content-sm-end">
                                    <div class="input-group" style="width: auto;">
                                        <input type="number" step="any" min="0" name="hours[<?= $item['id'] ?>]" class="form-control hours-input text-end bg-light" style="min-width: 90px; max-width: 140px;" data-item-id="<?= $item['id'] ?>" placeholder="<?= $item['item_type'] === 'fixed' ? '金額' : '時間' ?>" value="<?= $default_val ?>">
                                        <span class="input-group-text bg-white text-muted px-2">
                                            <?= $item['item_type'] === 'fixed' ? '円' : '時間' ?>
                                        </span>
                                    </div>
                                    <?php if($item['item_type'] !== 'fixed'): ?>
                                        <div class="ms-2 text-end text-nowrap" style="min-width: 90px;">
                                            = <span id="subtotal-<?= $item['id'] ?>" class="fw-bold fs-6">0</span> <span class="text-muted small">円</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <h5 class="custom-section-title custom-section-deduct"><i class="bi bi-dash-circle text-danger me-2"></i>控除 (マイナス)</h5>
                    <?php foreach ($items as $item): ?>
                        <?php if (isset($item['item_type']) && $item['item_type'] === 'deduction'): ?>
                        <?php $default_val = isset($existing_details[$item['id']]) ? floatval($existing_details[$item['id']]) : ''; ?>
                        <div class="row align-items-center mb-3 pb-3 border-bottom border-light">
                            <div class="col-sm-5 mb-2 mb-sm-0">
                                <strong><?= htmlspecialchars($item['item_name']) ?></strong> <span class="badge bg-danger ms-1">控除</span><br>
                                <button type="button" class="btn btn-sm btn-outline-success mt-1 py-0 px-2" onclick="calculateTax(<?= $item['id'] ?>)"><i class="bi bi-calculator me-1"></i>10.21%自動入力</button>
                            </div>
                            <div class="col-sm-7">
                                <div class="d-flex align-items-center justify-content-sm-end">
                                    <div class="input-group" style="width: auto;">
                                        <input type="number" step="1" min="0" name="hours[<?= $item['id'] ?>]" id="input-<?= $item['id'] ?>" class="form-control hours-input text-end bg-light" style="min-width: 100px; max-width: 140px;" data-item-id="<?= $item['id'] ?>" placeholder="引かれる額" value="<?= $default_val ?>">
                                        <span class="input-group-text bg-white text-muted px-2">円</span>
                                    </div>
                                    <span class="rate" data-item-id="<?= $item['id'] ?>" data-type="deduction" style="display:none;">1</span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                </div>

                <div class="floating-footer p-3 p-sm-4">
                    <div class="container" style="max-width: 700px;">
                        <div class="row align-items-center g-2 g-sm-3">
                            <div class="col-7 col-sm-8">
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>小計: <span id="plus-total">0</span>円</span>
                                    <span class="text-danger">控除: -<span id="minus-total">0</span>円</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-end">
                                    <span class="fw-bold d-inline text-secondary">合計:</span>
                                    <span class="fs-3 fw-bold text-danger lh-1"><span id="grand-total">0</span> <span class="fs-6">円</span></span>
                                </div>
                            </div>
                            <div class="col-5 col-sm-4">
                                <button type="submit" class="btn btn-primary w-100 h-100 fw-bold shadow-sm d-flex align-items-center justify-content-center py-2 py-sm-3">
                                    <?php if ($is_edit_mode && $existing_ms_id): ?>
                                        <i class="bi bi-arrow-repeat me-1 fs-5"></i> 更新
                                    <?php else: ?>
                                        <i class="bi bi-cloud-arrow-up me-1 fs-5"></i> 登録
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-warning text-center p-5 shadow-sm border-0">
                <i class="bi bi-exclamation-triangle display-4 text-warning mb-3"></i>
                <h4>有効な給与項目がありません</h4>
                <p class="text-muted">給与計算を行うには、最初に「授業」や「手当」などの項目を作成してください。</p>
                <a href="items.php" class="btn btn-primary mt-2"><i class="bi bi-gear me-1"></i>項目を設定しに行く</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const inputs = document.querySelectorAll('.hours-input');
        
        function calculateAll() {
            let rawPlusTotal = 0;
            let minusTotal = 0;
            
            inputs.forEach(inp => {
                const itemId = inp.getAttribute('data-item-id');
                const rateElement = document.querySelector(`.rate[data-item-id="${itemId}"]`);
                if (rateElement) {
                    const rate = parseFloat(rateElement.innerText);
                    const type = rateElement.getAttribute('data-type');
                    const value = parseFloat(inp.value) || 0;
                    
                    if (type === 'hourly') {
                        const amount = Math.ceil(rate * value);
                        const subtotalText = document.getElementById(`subtotal-${itemId}`);
                        if (subtotalText) subtotalText.innerText = amount.toLocaleString();
                        rawPlusTotal += amount;
                    } else if (type === 'fixed') {
                        rawPlusTotal += rate * value;
                    } else if (type === 'deduction') {
                        minusTotal += rate * value;
                    }
                }
            });
            
            let grandTotal = rawPlusTotal - minusTotal;
            document.getElementById('plus-total').innerText = rawPlusTotal.toLocaleString();
            document.getElementById('minus-total').innerText = minusTotal.toLocaleString();
            document.getElementById('grand-total').innerText = grandTotal.toLocaleString();
            return rawPlusTotal;
        }

        inputs.forEach(input => input.addEventListener('input', calculateAll));

        function calculateTax(itemId) {
            let currentPlusTotal = calculateAll();
            let taxAmount = Math.floor(currentPlusTotal * 0.1021);
            let inputField = document.getElementById('input-' + itemId);
            inputField.value = taxAmount;
            inputField.dispatchEvent(new Event('input'));
        }

        window.onload = calculateAll;
    </script>
</body>
</html>