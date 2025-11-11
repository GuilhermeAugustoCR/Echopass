<?php
// solicitation.php
include 'config.php';

// Obter token da URL
$token_link = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token_link)) {
    die("Link de token inválido. Por favor, use o link completo fornecido pelo organizador do evento.");
}

// Obter informações do token e convite
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.*, 
            c.*,
            (SELECT COUNT(*) FROM solicitacao s WHERE s.ID_Convite = c.ID_Convite AND s.status = 'aprovada') as aprovadas_count
        FROM token t 
        JOIN convite c ON t.ID_Convite = c.ID_Convite 
        WHERE t.token_link = ? AND t.status = 'ativo' AND c.status = 'ativo'
    ");
    $stmt->execute([$token_link]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$token_data) {
        // Verificar qual é o problema específico
        $check_token = $pdo->prepare("SELECT * FROM token WHERE token_link = ?");
        $check_token->execute([$token_link]);
        $token_exists = $check_token->fetch(PDO::FETCH_ASSOC);
        
        if (!$token_exists) {
            die("Token não encontrado. Verifique se o link está correto.");
        } elseif ($token_exists['status'] != 'ativo') {
            die("Este link de inscrição não está mais ativo. Entre em contato com o organizador do evento.");
        } else {
            $check_convite = $pdo->prepare("SELECT * FROM convite WHERE ID_Convite = ? AND status = 'ativo'");
            $check_convite->execute([$token_exists['ID_Convite']]);
            $convite_exists = $check_convite->fetch(PDO::FETCH_ASSOC);
            
            if (!$convite_exists) {
                die("Este evento não está mais ativo para inscrições.");
            }
        }
    }
} catch(PDOException $e) {
    die("Erro ao verificar o token: " . $e->getMessage());
}

// Verificar capacidade
$capacidade_atingida = $token_data['aprovadas_count'] >= $token_data['num_max'];

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_cliente = trim($_POST['nome_cliente']);
    $cpf_cliente = $_POST['cpf_cliente'];
    $email_cliente = $_POST['email_cliente'];
    $data_nasc = $_POST['data_nasc'];
    $num_polt = !empty($_POST['num_polt']) ? $_POST['num_polt'] : NULL;
    
    // Validações básicas
    if (empty($nome_cliente)) {
        $error = "Por favor, informe seu nome completo.";
    } elseif (strlen($cpf_cliente) != 11 || !is_numeric($cpf_cliente)) {
        $error = "CPF deve conter exatamente 11 números.";
    } elseif ($capacidade_atingida) {
        $error = "Este evento já atingiu a capacidade máxima de participantes.";
    } else {
        try {
            // Verificar se já existe solicitação com este CPF para este evento
            $check_stmt = $pdo->prepare("SELECT * FROM solicitacao WHERE cpf_cliente = ? AND ID_Convite = ?");
            $check_stmt->execute([$cpf_cliente, $token_data['ID_Convite']]);
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                $error = "Já existe uma inscrição com este CPF para este evento.";
            } else {
                // Inserir solicitação na tabela solicitacao
                $stmt = $pdo->prepare("
                    INSERT INTO solicitacao 
                    (ID_Convite, ID_Token, nome_cliente, cpf_cliente, email_cliente, data_nasc, num_polt, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')
                ");
                $stmt->execute([
                    $token_data['ID_Convite'], 
                    $token_data['ID_Token'], 
                    $nome_cliente,
                    $cpf_cliente, 
                    $email_cliente, 
                    $data_nasc, 
                    $num_polt
                ]);
                
                $success = "✅ Inscrição realizada com sucesso!<br><br>
                           <strong>Nome:</strong> $nome_cliente<br>
                           <strong>Evento:</strong> {$token_data['nome_evento']}<br><br>
                           Sua solicitação foi enviada e está <strong>aguardando aprovação</strong>.<br>
                           Você receberá uma confirmação por e-mail quando sua inscrição for aprovada.";
                
                // Limpar o formulário após envio bem-sucedido
                $_POST = array();
            }
        } catch(PDOException $e) {
            $error = "Erro ao processar sua inscrição: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscrição para <?php echo htmlspecialchars($token_data['nome_evento']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white; 
            padding: 40px; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .event-info { 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 30px; 
            border-radius: 12px; 
            margin-bottom: 30px; 
            border-left: 5px solid #007bff;
        }
        
        .form-section { 
            padding: 30px; 
            border: 2px dashed #dee2e6; 
            border-radius: 12px; 
            background: #fafbfc;
        }
        
        input, textarea, select { 
            width: 100%; 
            padding: 15px; 
            margin: 10px 0; 
            border: 2px solid #e9ecef; 
            border-radius: 8px; 
            box-sizing: border-box; 
            font-size: 16px; 
            transition: all 0.3s ease;
        }
        
        input:focus, textarea:focus, select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
            outline: none;
        }
        
        button { 
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white; 
            padding: 18px 30px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            width: 100%; 
            font-size: 18px; 
            font-weight: bold; 
            transition: all 0.3s ease;
        }
        
        button:hover { 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
        }
        
        button:disabled { 
            background: #6c757d; 
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .error { 
            color: #dc3545; 
            margin: 20px 0; 
            padding: 20px; 
            background: #f8d7da; 
            border: 1px solid #f5c6cb; 
            border-radius: 8px; 
            border-left: 5px solid #dc3545;
        }
        
        .success { 
            color: #155724; 
            margin: 20px 0; 
            padding: 25px; 
            background: #d4edda; 
            border: 1px solid #c3e6cb; 
            border-radius: 8px; 
            border-left: 5px solid #28a745;
            text-align: center;
        }
        
        .warning { 
            color: #856404; 
            margin: 20px 0; 
            padding: 25px; 
            background: #fff3cd; 
            border: 1px solid #ffeaa7; 
            border-radius: 8px;
            text-align: center;
        }
        
        h1 { 
            color: #2c3e50; 
            text-align: center; 
            margin-bottom: 10px; 
            font-size: 2.5em;
        }
        
        h2 { 
            color: #495057; 
            margin-bottom: 25px; 
            font-size: 1.8em;
        }
        
        .event-details { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
            margin-top: 20px; 
        }
        
        .detail-item { 
            background: white; 
            padding: 15px; 
            border-radius: 8px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .capacity-info { 
            text-align: center; 
            font-weight: bold; 
            margin: 20px 0; 
            padding: 15px; 
            border-radius: 8px; 
            font-size: 1.1em;
        }
        
        .capacity-full { 
            background: #f8d7da; 
            color: #dc3545; 
            border: 2px solid #dc3545;
        }
        
        .capacity-available { 
            background: #d4edda; 
            color: #155724; 
            border: 2px solid #28a745;
        }
        
        .form-group { 
            margin-bottom: 25px; 
        }
        
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: bold; 
            color: #495057;
            font-size: 1.1em;
        }
        
        .required::after {
            content: " *";
            color: #dc3545;
        }
        
        .event-description {
            margin: 20px 0;
            line-height: 1.6;
            color: #6c757d;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #007bff;
            font-size: 2.8em;
            margin-bottom: 5px;
        }
        
        .logo .subtitle {
            color: #6c757d;
            font-size: 1.2em;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>EchoPass</h1>
            <div class="subtitle">Sistema de Inscrição para Eventos</div>
        </div>
        
        <!-- Informações do Evento -->
        <div class="event-info">
            <h2><?php echo htmlspecialchars($token_data['nome_evento']); ?></h2>
            
            <div class="event-details">
                <div class="detail-item">
                    <strong>📅 Data:</strong><br>
                    <?php echo date('d/m/Y', strtotime($token_data['data_evento'])); ?>
                </div>
                <div class="detail-item">
                    <strong>⏰ Horário:</strong><br>
                    <?php echo substr($token_data['hora_evento'], 0, 5); ?>
                </div>
                <div class="detail-item">
                    <strong>👥 Capacidade:</strong><br>
                    <?php echo $token_data['aprovadas_count'] . ' / ' . $token_data['num_max']; ?>
                </div>
            </div>
            
            <?php if (!empty($token_data['descricao'])): ?>
                <div class="event-description">
                    <strong>📝 Descrição:</strong><br>
                    <?php echo nl2br(htmlspecialchars($token_data['descricao'])); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($token_data['faq'])): ?>
                <div class="event-description">
                    <strong>❓ Informações Importantes:</strong><br>
                    <?php echo nl2br(htmlspecialchars($token_data['faq'])); ?>
                </div>
            <?php endif; ?>
            
            <div class="capacity-info <?php echo $capacidade_atingida ? 'capacity-full' : 'capacity-available'; ?>">
                <?php if ($capacidade_atingida): ?>
                    ❌ EVENTO LOTADO - Inscrições Encerradas
                <?php else: ?>
                    ✅ VAGAS DISPONÍVEIS: <?php echo $token_data['num_max'] - $token_data['aprovadas_count']; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($success)): ?>
            <div class="success">
                <?php echo $success; ?>
                <div style="margin-top: 20px;">
                    <a href="<?php echo $token_link; ?>" style="color: #155724; text-decoration: underline;">
                        Voltar para a página do evento
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Formulário de Solicitação -->
        <?php if (!$capacidade_atingida && empty($success)): ?>
            <div class="form-section">
                <h2>📋 Formulário de Inscrição</h2>
                <p style="color: #6c757d; margin-bottom: 25px; text-align: center;">
                    Preencha os dados abaixo para se inscrever no evento. Sua inscrição ficará pendente de aprovação.
                </p>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="nome_cliente" class="required">Nome Completo:</label>
                        <input type="text" 
                               id="nome_cliente" 
                               name="nome_cliente" 
                               placeholder="Digite seu nome completo" 
                               required 
                               maxlength="100"
                               value="<?php echo isset($_POST['nome_cliente']) ? htmlspecialchars($_POST['nome_cliente']) : ''; ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cpf_cliente" class="required">CPF:</label>
                            <input type="text" 
                                   id="cpf_cliente" 
                                   name="cpf_cliente" 
                                   placeholder="Apenas números" 
                                   required 
                                   maxlength="11" 
                                   pattern="[0-9]{11}" 
                                   title="Digite apenas números (11 dígitos)"
                                   value="<?php echo isset($_POST['cpf_cliente']) ? htmlspecialchars($_POST['cpf_cliente']) : ''; ?>">
                            <small style="color: #6c757d;">11 dígitos, sem pontos ou traços</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="data_nasc" class="required">Data de Nascimento:</label>
                            <input type="date" 
                                   id="data_nasc" 
                                   name="data_nasc" 
                                   required 
                                   max="<?php echo date('Y-m-d'); ?>"
                                   value="<?php echo isset($_POST['data_nasc']) ? htmlspecialchars($_POST['data_nasc']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email_cliente" class="required">E-mail:</label>
                        <input type="email" 
                               id="email_cliente" 
                               name="email_cliente" 
                               placeholder="seu@email.com" 
                               required
                               value="<?php echo isset($_POST['email_cliente']) ? htmlspecialchars($_POST['email_cliente']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="num_polt">Número do Assento (opcional):</label>
                        <input type="number" 
                               id="num_polt" 
                               name="num_polt" 
                               placeholder="Número do assento desejado" 
                               min="1"
                               value="<?php echo isset($_POST['num_polt']) ? htmlspecialchars($_POST['num_polt']) : ''; ?>">
                        <small style="color: #6c757d;">Deixe em branco para ser atribuído automaticamente</small>
                    </div>
                    
                    <button type="submit">
                        🚀 Enviar Inscrição
                    </button>
                </form>
            </div>
        <?php elseif ($capacidade_atingida): ?>
            <div class="warning">
                <h3>🎟️ Evento Lotado</h3>
                <p>Infelizmente, este evento já atingiu a capacidade máxima de participantes.</p>
                <p style="margin-top: 10px;">Entre em contato com o organizador para mais informações.</p>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px; color: #6c757d; font-size: 0.9em;">
            <p>© 2024 EchoPass - Sistema de Gestão de Eventos</p>
        </div>
    </div>

    <script>
    // Validação do CPF no frontend
    document.getElementById('cpf_cliente').addEventListener('input', function(e) {
        this.value = this.value.replace(/\D/g, '');
    });
    
    // Validação do número do assento
    document.getElementById('num_polt').addEventListener('input', function(e) {
        if (this.value < 1) {
            this.value = '';
        }
    });
    
    // Foco no primeiro campo
    document.getElementById('nome_cliente').focus();
    
    // Mensagem de confirmação antes de sair da página se o formulário foi preenchido
    window.addEventListener('beforeunload', function(e) {
        const formFilled = document.getElementById('nome_cliente').value || 
                          document.getElementById('cpf_cliente').value || 
                          document.getElementById('email_cliente').value || 
                          document.getElementById('data_nasc').value;
        
        if (formFilled && !document.querySelector('.success')) {
            e.preventDefault();
            e.returnValue = 'Você tem dados preenchidos no formulário. Tem certeza que deseja sair?';
        }
    });
    </script>
</body>
</html>