<?php
// index.php
session_start();
require 'db.php';

$error = '';
$reg_success = isset($_GET['registered']) && $_GET['registered'] == 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "ユーザー名またはパスワードが間違っています。";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - 給与計算アプリ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body class="bg-light d-flex align-items-center" style="height: 100vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card shadow border-0">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <i class="bi bi-wallet2 display-4 text-primary"></i>
                            <h3 class="mt-2 fw-bold">ログイン</h3>
                        </div>

                        <?php if ($reg_success): ?>
                            <div class="alert alert-success shadow-sm" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>登録が完了しました！<br>ログインしてください。
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger shadow-sm" role="alert">
                                <i class="bi bi-exclamation-circle-fill me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold">ユーザー名</label>
                                <input type="text" name="username" class="form-control form-control-lg" placeholder="Username" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">パスワード</label>
                                <input type="password" name="password" class="form-control form-control-lg" placeholder="Password" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm fw-bold">ログイン</button>
                        </form>
                        <div class="text-center mt-4">
                            <a href="register.php" class="text-decoration-none">はじめての方はこちら（新規登録）</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>