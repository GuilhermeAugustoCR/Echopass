<?php
// mod_evento.php
include 'config.php';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: mod_evento.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'signup') {
            // Cadastrar novo moderador
            $nome = $_POST['nome'];
            $email = $_POST['email'];
            $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
            $cnpj = $_POST['cnpj'];
            $telefone = $_POST['telefone'];
            
            try {
                $stmt = $pdo->prepare("INSERT INTO mod_evento (nome, email, senha, cnpj, telefone) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $email, $senha, $cnpj, $telefone]);
                $mod_id = $pdo->lastInsertId();
                
                $_SESSION['mod_id'] = $mod_id;
                $_SESSION['mod_nome'] = $nome;
                $_SESSION['mod_email'] = $email;
                
                header("Location: convite.php");
                exit();
            } catch(PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "E-mail ou CNPJ já cadastrado";
                } else {
                    $error = "Erro ao criar conta: " . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] === 'login') {
            // Login de moderador existente
            $email = $_POST['email'];
            $senha = $_POST['senha'];
            
            $stmt = $pdo->prepare("SELECT * FROM mod_evento WHERE email = ?");
            $stmt->execute([$email]);
            $moderator = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($moderator && password_verify($senha, $moderator['senha'])) {
                $_SESSION['mod_id'] = $moderator['ID_Mod'];
                $_SESSION['mod_nome'] = $moderator['nome'];
                $_SESSION['mod_email'] = $moderator['email'];
                
                header("Location: convite.php");
                exit();
            } else {
                $error = "E-mail ou senha incorretos";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso de Moderador</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-section { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        input, textarea, select { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px; }
        button:hover { background: #0056b3; }
        .error { color: #dc3545; margin: 10px 0; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; }
        .success { color: #155724; margin: 10px 0; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; }
        h1 { text-align: center; color: #333; margin-bottom: 30px; }
        h2 { color: #555; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>EchoPass - Acesso de Moderador</h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Formulário de Cadastro -->
        <div class="form-section">
            <h2>Cadastrar como Moderador</h2>
            <form method="POST">
                <input type="hidden" name="action" value="signup">
                <input type="text" name="nome" placeholder="Nome completo" required>
                <input type="email" name="email" placeholder="E-mail" required>
                <input type="password" name="senha" placeholder="Senha" required minlength="6">
                <input type="text" name="cnpj" placeholder="CNPJ (apenas números)" required maxlength="14" pattern="[0-9]{14}">
                <input type="text" name="telefone" placeholder="Telefone (com DDD)" maxlength="15">
                <button type="submit">Cadastrar</button>
            </form>
        </div>
        
        <!-- Formulário de Login -->
        <div class="form-section">
            <h2>Login como Moderador</h2>
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <input type="email" name="email" placeholder="E-mail" required>
                <input type="password" name="senha" placeholder="Senha" required>
                <button type="submit">Entrar</button>
            </form>
        </div>
    </div>
</body>
</html>