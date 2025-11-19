<?php
include 'config.php';

$erro = '';
$sucesso = '';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirecionar('mod_evento.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // CADASTRO DE MODERADOR
        if ($_POST['action'] === 'signup') {
            $nome = trim($_POST['nome']);
            $email = trim($_POST['email']);
            $senha = $_POST['senha'];
            $senhaConfirm = $_POST['senha_confirm'];
            $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj']);
            $telefone = preg_replace('/[^0-9]/', '', $_POST['telefone'] ?? '');
            
            // Validações
            if (empty($nome) || empty($email) || empty($senha) || empty($cnpj)) {
                $erro = "Preencha todos os campos obrigatórios";
            } elseif (!validarEmail($email)) {
                $erro = "E-mail inválido ou domínio inexistente";
            } elseif (strlen($senha) < 6) {
                $erro = "A senha deve ter no mínimo 6 caracteres";
            } elseif ($senha !== $senhaConfirm) {
                $erro = "As senhas não coincidem";
            } elseif (!validarCNPJ($cnpj)) {
                $erro = "CNPJ inválido";
            } else {
                try {
                    // Gerar código de confirmação
                    $codigoConfirmacao = gerarCodigoConfirmacao();
                    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO mod_evento (nome, email, senha, cnpj, telefone, codigo_confirmacao, email_confirmado) 
                        VALUES (?, ?, ?, ?, ?, ?, 0)
                    ");
                    $stmt->execute([$nome, $email, $senhaHash, $cnpj, $telefone, $codigoConfirmacao]);
                    $mod_id = $pdo->lastInsertId();
                    
                    // Enviar e-mail de confirmação
                    $assunto = "Confirme seu cadastro - EchoPass";
                    $mensagem = "
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { background: #4a90e2; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                                .code { font-size: 32px; font-weight: bold; color: #4a90e2; text-align: center; padding: 20px; background: white; border-radius: 8px; margin: 20px 0; letter-spacing: 5px; }
                                .footer { text-align: center; margin-top: 20px; color: #777; font-size: 12px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>🎫 Bem-vindo ao EchoPass!</h1>
                                </div>
                                <div class='content'>
                                    <p>Olá, <strong>{$nome}</strong>!</p>
                                    <p>Obrigado por se cadastrar como moderador no EchoPass.</p>
                                    <p>Para confirmar seu cadastro, utilize o código abaixo:</p>
                                    <div class='code'>{$codigoConfirmacao}</div>
                                    <p>Este código expira em 24 horas.</p>
                                    <p>Se você não solicitou este cadastro, ignore este e-mail.</p>
                                </div>
                                <div class='footer'>
                                    <p>&copy; 2025 EchoPass - Sistema de Gerenciamento de Eventos</p>
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    enviarEmail($email, $assunto, $mensagem, 'confirmacao_cadastro');
                    
                    $_SESSION['mod_id_temp'] = $mod_id;
                    $_SESSION['mod_email_temp'] = $email;
                    
                    redirecionar('confirmar_email.php');
                    
                } catch(PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $erro = "E-mail ou CNPJ já cadastrado";
                    } else {
                        $erro = "Erro ao criar conta: " . $e->getMessage();
                    }
                }
            }
        } 
        
        // LOGIN DE MODERADOR
        elseif ($_POST['action'] === 'login') {
            $email = trim($_POST['email']);
            $senha = $_POST['senha'];
            
            if (empty($email) || empty($senha)) {
                $erro = "Preencha todos os campos";
            } else {
                $stmt = $pdo->prepare("SELECT * FROM mod_evento WHERE email = ?");
                $stmt->execute([$email]);
                $moderator = $stmt->fetch();
                
                if ($moderator && password_verify($senha, $moderator['senha'])) {
                    $_SESSION['mod_id'] = $moderator['ID_Mod'];
                    $_SESSION['mod_nome'] = $moderator['nome'];
                    $_SESSION['mod_email'] = $moderator['email'];
                    $_SESSION['email_confirmado'] = $moderator['email_confirmado'];
                    
                    // Verificar se o e-mail foi confirmado
                    if ($moderator['email_confirmado'] == 0) {
                        redirecionar('confirmar_email.php');
                    } else {
                        redirecionar('dashboard.php');
                    }
                } else {
                    $erro = "E-mail ou senha incorretos";
                }
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
    <title>Acesso de Moderador - EchoPass</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">EchoPass</a>
                <ul class="nav-links">
                    <li><a href="index.php">Ver Eventos</a></li>
                </ul>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="auth-container">
            <div class="card">
                <div class="card-header text-center">
                    <h2>Área do Moderador</h2>
                    <p style="color: #777; margin-top: 10px;">Gerencie seus eventos</p>
                </div>

                <?php if ($erro): ?>
                    <div class="alert alert-error"><?php echo $erro; ?></div>
                <?php endif; ?>
                
                <?php if ($sucesso): ?>
                    <div class="alert alert-success"><?php echo $sucesso; ?></div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" onclick="mostrarTab('login')">Login</button>
                    <button class="tab" onclick="mostrarTab('cadastro')">Cadastrar</button>
                </div>

                <!-- Formulário de Login -->
                <div id="login-tab" class="form-section active">
                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="form-group">
                            <label for="login_email" class="required">E-mail</label>
                            <input type="email" id="login_email" name="email" placeholder="seuemail@exemplo.com" required>
                        </div>

                        <div class="form-group">
                            <label for="login_senha" class="required">Senha</label>
                            <input type="password" id="login_senha" name="senha" placeholder="Digite sua senha" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Entrar</button>
                    </form>
                </div>

                <!-- Formulário de Cadastro -->
                <div id="cadastro-tab" class="form-section">
                    <form method="POST" id="formCadastro">
                        <input type="hidden" name="action" value="signup">
                        
                        <div class="form-group">
                            <label for="nome" class="required">Nome Completo</label>
                            <input type="text" id="nome" name="nome" placeholder="Digite seu nome completo" required>
                        </div>

                        <div class="form-group">
                            <label for="email" class="required">E-mail</label>
                            <input type="email" id="email" name="email" placeholder="seuemail@exemplo.com" required>
                            <small style="color: #777;">Você receberá um código de confirmação</small>
                        </div>

                        <div class="form-group">
                            <label for="senha" class="required">Senha</label>
                            <input type="password" id="senha" name="senha" placeholder="Mínimo 6 caracteres" required minlength="6">
                        </div>

                        <div class="form-group">
                            <label for="senha_confirm" class="required">Confirmar Senha</label>
                            <input type="password" id="senha_confirm" name="senha_confirm" placeholder="Digite a senha novamente" required minlength="6">
                        </div>

                        <div class="form-group">
                            <label for="cnpj" class="required">CNPJ</label>
                            <input type="text" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" required maxlength="18">
                        </div>

                        <div class="form-group">
                            <label for="telefone">Telefone</label>
                            <input type="text" id="telefone" name="telefone" placeholder="(00) 00000-0000" maxlength="15">
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Cadastrar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2025 EchoPass - Sistema de Gerenciamento de Eventos</p>
    </div>

    <script>
        function mostrarTab(tab) {
            // Atualizar tabs
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
            
            if (tab === 'login') {
                document.querySelectorAll('.tab')[0].classList.add('active');
                document.getElementById('login-tab').classList.add('active');
            } else {
                document.querySelectorAll('.tab')[1].classList.add('active');
                document.getElementById('cadastro-tab').classList.add('active');
            }
        }

        // Máscara para CNPJ
        document.getElementById('cnpj').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 14) {
                value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
                e.target.value = value;
            }
        });

        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                if (value.length <= 10) {
                    value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{4})(\d)/, '$1-$2');
                } else {
                    value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{5})(\d)/, '$1-$2');
                }
                e.target.value = value;
            }
        });

        // Validação de senha
        document.getElementById('formCadastro').addEventListener('submit', function(e) {
            const senha = document.getElementById('senha').value;
            const senhaConfirm = document.getElementById('senha_confirm').value;
            
            if (senha !== senhaConfirm) {
                e.preventDefault();
                alert('As senhas não coincidem');
                return false;
            }
        });
    </script>
</body>
</html>