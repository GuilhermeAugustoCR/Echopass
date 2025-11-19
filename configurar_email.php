<?php
include 'config.php';

$erro = '';
$sucesso = '';

// Verificar se há sessão temporária ou moderador logado
if (!isset($_SESSION['mod_id_temp']) && !isset($_SESSION['mod_id'])) {
    redirecionar('mod_evento.php');
}

$modId = $_SESSION['mod_id_temp'] ?? $_SESSION['mod_id'];
$email = $_SESSION['mod_email_temp'] ?? $_SESSION['mod_email'];

// Verificar se já está confirmado
$stmt = $pdo->prepare("SELECT email_confirmado FROM mod_evento WHERE ID_Mod = ?");
$stmt->execute([$modId]);
$mod = $stmt->fetch();

if ($mod && $mod['email_confirmado'] == 1) {
    redirecionar('dashboard.php');
}

// Processar confirmação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo']);
    
    if (empty($codigo)) {
        $erro = "Digite o código de confirmação";
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM mod_evento 
            WHERE ID_Mod = ? AND codigo_confirmacao = ? AND email_confirmado = 0
        ");
        $stmt->execute([$modId, $codigo]);
        $moderator = $stmt->fetch();
        
        if ($moderator) {
            // Confirmar e-mail
            $stmt = $pdo->prepare("
                UPDATE mod_evento 
                SET email_confirmado = 1, codigo_confirmacao = NULL 
                WHERE ID_Mod = ?
            ");
            $stmt->execute([$modId]);
            
            // Atualizar sessão
            $_SESSION['mod_id'] = $moderator['ID_Mod'];
            $_SESSION['mod_nome'] = $moderator['nome'];
            $_SESSION['mod_email'] = $moderator['email'];
            $_SESSION['email_confirmado'] = 1;
            
            // Limpar sessão temporária
            unset($_SESSION['mod_id_temp']);
            unset($_SESSION['mod_email_temp']);
            
            $sucesso = "E-mail confirmado com sucesso! Redirecionando...";
            
            // Redirecionar após 2 segundos
            header("refresh:2;url=dashboard.php");
            
        } else {
            $erro = "Código inválido ou expirado";
        }
    }
}

// Reenviar código
if (isset($_GET['reenviar'])) {
    $novoCodig = gerarCodigoConfirmacao();
    
    $stmt = $pdo->prepare("UPDATE mod_evento SET codigo_confirmacao = ? WHERE ID_Mod = ?");
    $stmt->execute([$novoCodigo, $modId]);
    
    // Enviar e-mail novamente
    $stmt = $pdo->prepare("SELECT nome FROM mod_evento WHERE ID_Mod = ?");
    $stmt->execute([$modId]);
    $mod = $stmt->fetch();
    
    $assunto = "Novo código de confirmação - EchoPass";
    $mensagem = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4a90e2; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                .code { font-size: 32px; font-weight: bold; color: #4a90e2; text-align: center; padding: 20px; background: white; border-radius: 8px; margin: 20px 0; letter-spacing: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎫 Novo Código de Confirmação</h1>
                </div>
                <div class='content'>
                    <p>Olá, <strong>{$mod['nome']}</strong>!</p>
                    <p>Você solicitou um novo código de confirmação.</p>
                    <div class='code'>{$novoCodigo}</div>
                    <p>Este código expira em 24 horas.</p>
                </div>
            </div>
        </body>
        </html>
    ";
    
    enviarEmail($email, $assunto, $mensagem, 'reenvio_codigo');
    
    $sucesso = "Novo código enviado para seu e-mail!";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar E-mail - EchoPass</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .confirmation-container {
            max-width: 500px;
            margin: 60px auto;
        }
        .code-input {
            text-align: center;
            font-size: 32px;
            letter-spacing: 10px;
            font-weight: bold;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">EchoPass</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="confirmation-container">
            <div class="card">
                <div class="card-header text-center">
                    <h2>Confirme seu E-mail</h2>
                    <p style="color: #777; margin-top: 10px;">
                        Enviamos um código de 6 dígitos para<br>
                        <strong><?php echo htmlspecialchars($email); ?></strong>
                    </p>
                </div>

                <?php if ($erro): ?>
                    <div class="alert alert-error"><?php echo $erro; ?></div>
                <?php endif; ?>
                
                <?php if ($sucesso): ?>
                    <div class="alert alert-success"><?php echo $sucesso; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="codigo" class="required">Código de Confirmação</label>
                        <input 
                            type="text" 
                            id="codigo" 
                            name="codigo" 
                            class="code-input"
                            placeholder="000000"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            required
                            autofocus
                        >
                        <small style="color: #777; display: block; text-align: center;">
                            Digite o código de 6 dígitos que você recebeu por e-mail
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Confirmar</button>
                </form>

                <div style="text-align: center; margin-top: 20px;">
                    <p style="color: #777;">Não recebeu o código?</p>
                    <a href="?reenviar=1" class="btn btn-secondary btn-sm">Reenviar Código</a>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2025 EchoPass - Sistema de Gerenciamento de Eventos</p>
    </div>

    <script>
        // Aceitar apenas números
        document.getElementById('codigo').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    </script>
</body>
</html>