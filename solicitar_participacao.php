<?php
include 'config.php';

$erro = '';
$sucesso = '';

// Verificar se foi passado ID do evento
if (!isset($_GET['evento']) || !is_numeric($_GET['evento'])) {
    redirecionar('index.php');
}

$idEvento = (int)$_GET['evento'];

// Buscar informações do evento
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        (c.num_max - COUNT(CASE WHEN s.status = 'aprovada' THEN 1 END)) AS vagas_disponiveis
    FROM convite c
    LEFT JOIN solicitacao s ON c.ID_Convite = s.ID_Convite
    WHERE c.ID_Convite = ? AND c.status = 'ativo' AND c.data_evento >= CURDATE()
    GROUP BY c.ID_Convite
");
$stmt->execute([$idEvento]);
$evento = $stmt->fetch();

if (!$evento) {
    redirecionar('index.php');
}

// Buscar assentos já ocupados
$stmt = $pdo->prepare("
    SELECT num_polt 
    FROM solicitacao 
    WHERE ID_Convite = ? AND status IN ('pendente', 'aprovada') AND num_polt IS NOT NULL
    ORDER BY num_polt
");
$stmt->execute([$idEvento]);
$assentosOcupados = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome_cliente']);
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf_cliente']);
    $email = trim($_POST['email_cliente']);
    $dataNasc = $_POST['data_nasc'];
    $numPolt = (int)$_POST['num_polt'];
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    // Validações
    if (empty($nome) || empty($cpf) || empty($email) || empty($dataNasc) || empty($numPolt)) {
        $erro = 'Preencha todos os campos obrigatórios';
    } elseif (!validarCPF($cpf)) {
        $erro = 'CPF inválido';
    } elseif (!validarEmail($email)) {
        $erro = 'E-mail inválido ou domínio inexistente';
    } else {
        // Validar idade mínima
        $idade = calcularIdade($dataNasc);
        if ($evento['idade_minima'] > 0 && $idade < $evento['idade_minima']) {
            $erro = "Você deve ter no mínimo {$evento['idade_minima']} anos para participar deste evento";
        } 
        // Validar número do assento
        elseif ($numPolt < 1 || $numPolt > $evento['num_max']) {
            $erro = "Número de assento inválido. Escolha entre 1 e {$evento['num_max']}";
        }
        // Verificar se o assento já está ocupado
        elseif (in_array($numPolt, $assentosOcupados)) {
            $erro = "O assento $numPolt já está ocupado. Escolha outro assento";
        }
        // Verificar se ainda há vagas
        elseif ($evento['vagas_disponiveis'] <= 0) {
            $erro = "Desculpe, não há mais vagas disponíveis para este evento";
        }
        // Verificar se o CPF já solicitou para este evento
        else {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM solicitacao 
                WHERE ID_Convite = ? AND cpf_cliente = ? AND status IN ('pendente', 'aprovada')
            ");
            $stmt->execute([$idEvento, $cpf]);
            
            if ($stmt->fetchColumn() > 0) {
                $erro = "Você já possui uma solicitação para este evento";
            } else {
                // Inserir solicitação
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO solicitacao 
                        (ID_Convite, nome_cliente, cpf_cliente, email_cliente, data_nasc, num_polt, observacoes, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')
                    ");
                    $stmt->execute([
                        $idEvento, 
                        $nome, 
                        $cpf, 
                        $email, 
                        $dataNasc, 
                        $numPolt, 
                        $observacoes
                    ]);
                    
                    $sucesso = "Solicitação enviada com sucesso! Aguarde a aprovação do moderador. Você receberá um e-mail com a confirmação.";
                    
                    // Limpar campos
                    $_POST = [];
                    
                    // Atualizar lista de assentos ocupados
                    $assentosOcupados[] = $numPolt;
                    
                } catch (PDOException $e) {
                    $erro = "Erro ao processar solicitação: " . $e->getMessage();
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
    <title>Solicitar Participação - <?php echo htmlspecialchars($evento['nome_evento']); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">EchoPass</a>
                <ul class="nav-links">
                    <li><a href="index.php">Voltar aos Eventos</a></li>
                </ul>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="card" style="max-width: 800px; margin: 0 auto;">
            <div class="card-header">
                <h2>Solicitar Participação</h2>
                <p style="color: #777; margin-top: 10px;"><?php echo htmlspecialchars($evento['nome_evento']); ?></p>
            </div>

            <!-- Informações do Evento -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                <div class="event-info" style="gap: 12px;">
                    <span><strong>📅 Data:</strong> <?php echo formatarDataBR($evento['data_evento']); ?></span>
                    <span><strong>🕐 Horário:</strong> <?php echo formatarHoraBR($evento['hora_evento']); ?></span>
                    <span><strong>📍 CEP:</strong> <?php echo htmlspecialchars($evento['cep']); ?></span>
                    <span><strong>✅ Vagas Disponíveis:</strong> <?php echo $evento['vagas_disponiveis']; ?></span>
                    <?php if ($evento['idade_minima'] > 0): ?>
                        <span><strong>🔞 Idade Mínima:</strong> <?php echo $evento['idade_minima']; ?> anos</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($erro): ?>
                <div class="alert alert-error"><?php echo $erro; ?></div>
            <?php endif; ?>

            <?php if ($sucesso): ?>
                <div class="alert alert-success"><?php echo $sucesso; ?></div>
            <?php else: ?>

            <!-- Formulário de Solicitação -->
            <form method="POST" id="formSolicitacao">
                <div class="form-group">
                    <label for="nome_cliente" class="required">Nome Completo</label>
                    <input 
                        type="text" 
                        id="nome_cliente" 
                        name="nome_cliente" 
                        placeholder="Digite seu nome completo"
                        value="<?php echo htmlspecialchars($_POST['nome_cliente'] ?? ''); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="cpf_cliente" class="required">CPF</label>
                    <input 
                        type="text" 
                        id="cpf_cliente" 
                        name="cpf_cliente" 
                        placeholder="000.000.000-00"
                        maxlength="14"
                        value="<?php echo htmlspecialchars($_POST['cpf_cliente'] ?? ''); ?>"
                        required
                    >
                    <small style="color: #777;">Digite apenas números ou use o formato 000.000.000-00</small>
                </div>

                <div class="form-group">
                    <label for="email_cliente" class="required">E-mail</label>
                    <input 
                        type="email" 
                        id="email_cliente" 
                        name="email_cliente" 
                        placeholder="seuemail@exemplo.com"
                        value="<?php echo htmlspecialchars($_POST['email_cliente'] ?? ''); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="data_nasc" class="required">Data de Nascimento</label>
                    <input 
                        type="date" 
                        id="data_nasc" 
                        name="data_nasc" 
                        max="<?php echo date('Y-m-d'); ?>"
                        value="<?php echo htmlspecialchars($_POST['data_nasc'] ?? ''); ?>"
                        required
                    >
                    <?php if ($evento['idade_minima'] > 0): ?>
                        <small style="color: #777;">
                            Você deve ter no mínimo <?php echo $evento['idade_minima']; ?> anos
                        </small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="num_polt" class="required">Número do Assento</label>
                    <select id="num_polt" name="num_polt" required>
                        <option value="">Selecione um assento disponível</option>
                        <?php for ($i = 1; $i <= $evento['num_max']; $i++): ?>
                            <?php if (!in_array($i, $assentosOcupados)): ?>
                                <option value="<?php echo $i; ?>" <?php echo (isset($_POST['num_polt']) && $_POST['num_polt'] == $i) ? 'selected' : ''; ?>>
                                    Assento <?php echo $i; ?>
                                </option>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </select>
                    <small style="color: #777;">
                        Assentos ocupados: <?php echo empty($assentosOcupados) ? 'Nenhum' : implode(', ', $assentosOcupados); ?>
                    </small>
                </div>

                <div class="form-group">
                    <label for="observacoes">Observações (Opcional)</label>
                    <textarea 
                        id="observacoes" 
                        name="observacoes" 
                        placeholder="Alguma informação adicional que deseja compartilhar"
                        rows="4"
                    ><?php echo htmlspecialchars($_POST['observacoes'] ?? ''); ?></textarea>
                </div>

                <div class="btn-group">
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Enviar Solicitação</button>
                </div>
            </form>

            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2025 EchoPass - Sistema de Gerenciamento de Eventos</p>
    </div>

    <script>
        // Máscara para CPF
        document.getElementById('cpf_cliente').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                e.target.value = value;
            }
        });

        // Validação de idade no cliente
        <?php if ($evento['idade_minima'] > 0): ?>
        document.getElementById('data_nasc').addEventListener('change', function(e) {
            const dataNasc = new Date(e.target.value);
            const hoje = new Date();
            let idade = hoje.getFullYear() - dataNasc.getFullYear();
            const mes = hoje.getMonth() - dataNasc.getMonth();
            
            if (mes < 0 || (mes === 0 && hoje.getDate() < dataNasc.getDate())) {
                idade--;
            }
            
            if (idade < <?php echo $evento['idade_minima']; ?>) {
                alert('Você deve ter no mínimo <?php echo $evento['idade_minima']; ?> anos para participar deste evento');
                e.target.value = '';
            }
        });
        <?php endif; ?>

        // Validação do formulário
        document.getElementById('formSolicitacao').addEventListener('submit', function(e) {
            const cpf = document.getElementById('cpf_cliente').value.replace(/\D/g, '');
            
            if (cpf.length !== 11) {
                e.preventDefault();
                alert('Por favor, digite um CPF válido com 11 dígitos');
                return false;
            }
        });
    </script>
</body>
</html>