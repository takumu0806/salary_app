<?php
// register.php
require 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)");
        $stmt->execute([':username' => $username, ':password_hash' => $password_hash]);
        
        // ★ 登録成功後、パラメータを付けてログイン画面へリダイレクト
        header("Location: index.php?registered=1");
        exit;

    } catch (PDOException $e) {
        // PostgreSQLの重複エラーコード: 23505
        if ($e->getCode() == 23505) {
            $message = "そのユーザー名はすでに使われています。";
        } else {
            $message = "エラーが発生しました: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新規登録 - 給与計算アプリ</title>
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
                            <i class="bi bi-person-plus-fill display-4 text-success"></i>
                            <h3 class="mt-2 fw-bold">新規登録</h3>
                        </div>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold">ユーザー名</label>
                                <input type="text" name="username" class="form-control form-control-lg" placeholder="例: tanaka" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">パスワード</label>
                                <input type="password" name="password" class="form-control form-control-lg" placeholder="••••••••" required>
                            </div>
                            <button type="submit" class="btn btn-success btn-lg w-100 shadow-sm fw-bold">登録する</button>
                        </form>
                        <div class="text-center mt-4">
                            <a href="index.php" class="text-decoration-none text-muted small">既にアカウントをお持ちの方</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>