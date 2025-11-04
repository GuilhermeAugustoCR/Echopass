<?php
// convite.php
include 'config.php';

// Verificar se o moderador está logado
if (!isset($_SESSION['mod_id'])) {
    header("Location: mod_evento.php");
    exit();
}

// Handle form submission para criar novo convite
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_invite'])) {
        $nome_evento = $_POST['nome_evento'];
        $cep = $_POST['cep'];
        $descricao = $_POST['descricao'];
        $data_evento = $_POST['data_evento'];
        $hora_evento = $_POST['hora_evento'];
        $num_max = $_POST['num_max'];
        $faq = $_POST['faq'];
        
        try {
            // Usar a stored procedure para criar convite
            $stmt = $pdo->prepare("CALL sp_criar_convite(?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['mod_id'], $nome_evento, $cep, $descricao, $data_evento, $hora_evento, $num_max, $faq]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $success = "Convite criado com sucesso! ID: " . $result['novo_convite_id'];
        } catch(PDOException $e) {
            $error = "Erro ao criar convite: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_status'])) {
        // Atualizar status do convite
        $convite_id = $_POST['convite_id'];
        $novo_status = $_POST['novo_status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE convite SET status = ? WHERE ID_Convite = ? AND ID_Mod = ?");
            $stmt->execute([$novo_status, $convite_id, $_SESSION['mod_id']]);
            
            if ($stmt->rowCount() > 0) {
                $success = "Status do convite atualizado com sucesso!";
            } else {
                $error = "Convite não encontrado ou você não tem permissão para alterá-lo";
            }
        } catch(PDOException $e) {
            $error = "Erro ao atualizar status: " . $e->getMessage();
        }
    }
}

// Obter todos os convites deste moderador
$stmt = $pdo->prepare("SELECT * FROM convite WHERE ID_Mod = ? ORDER BY data_evento DESC");
$stmt->execute([$_SESSION['mod_id']]);
$invites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter estatísticas
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_convites,
        SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as ativos,
        SUM(CASE WHEN status = 'finalizado' THEN 1 ELSE 0 END) as finalizados
    FROM convite 
    WHERE ID_Mod = ?
");
$stats_stmt->execute([$_SESSION['mod_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Convites</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .nav { background: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .nav a { margin-right: 20px; text-decoration: none; color: #007bff; font-weight: 500; }
        .nav a:hover { text-decoration: underline; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2em; font-weight: bold; color: #007bff; }
        .form-section, .list-section { background: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        input, textarea, select { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .error { color: #dc3545; margin: 10px 0; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; }
        .success { color: #155724; margin: 10px 0; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: 600; }
        .status-ativo { color: #28a745; font-weight: bold; }
        .status-inativo { color: #6c757d; font-weight: bold; }
        .status-cancelado { color: #dc3545; font-weight: bold; }
        .status-finalizado { color: #17a2b8; font-weight: bold; }
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="convite.php">Gerenciar Convites</a>
            <a href="admin_convite.php">Painel Admin</a>
            <span style="float: right;">Olá, <?php echo $_SESSION['mod_nome']; ?> | <a href="mod_evento.php?logout=true">Sair</a></span>
        </div>
        
        <h1>Gerenciar Convites</h1>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Estatísticas -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_convites']; ?></div>
                <div>Total de Convites</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['ativos']; ?></div>
                <div>Convites Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['finalizados']; ?></div>
                <div>Convites Finalizados</div>
            </div>
        </div>
        
        <!-- Formulário para Criar Novo Convite -->
        <div class="form-section">
            <h2>Criar Novo Convite</h2>
            <form method="POST">
                <input type="hidden" name="create_invite" value="1">
                <input type="text" name="nome_evento" placeholder="Nome do Evento" required maxlength="100">
                <input type="text" name="cep" placeholder="CEP (apenas números)" required maxlength="8" pattern="[0-9]{8}">
                <textarea name="descricao" placeholder="Descrição do Evento" rows="3" maxlength="1000"></textarea>
                <input type="date" name="data_evento" required min="<?php echo date('Y-m-d'); ?>">
                <input type="time" name="hora_evento" required>
                <input type="number" name="num_max" placeholder="Número Máximo de Participantes" required min="1">
                <textarea name="faq" placeholder="Perguntas Frequentes (FAQ)" rows="3"></textarea>
                <button type="submit">Criar Convite</button>
            </form>
        </div>
        
        <!-- Lista de Convites Existentes -->
        <div class="list-section">
            <h2>Seus Convites</h2>
            <?php if (count($invites) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Nome do Evento</th>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Máx. Participantes</th>
                            <th>Status</th>
                            <th>Link do Token</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invites as $invite): 
                            $status_class = 'status-' . $invite['status'];
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($invite['nome_evento']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($invite['data_evento'])); ?></td>
                                <td><?php echo substr($invite['hora_evento'], 0, 5); ?></td>
                                <td><?php echo $invite['num_max']; ?></td>
                                <td><span class="<?php echo $status_class; ?>"><?php echo ucfirst($invite['status']); ?></span></td>
                                <td>
                                    <?php
                                    // Obter token para este convite
                                    $token_stmt = $pdo->prepare("SELECT * FROM token WHERE ID_Convite = ? AND status = 'ativo'");
                                    $token_stmt->execute([$invite['ID_Convite']]);
                                    $token = $token_stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($token) {
                                        echo '<a href="solicitation.php?token=' . urlencode($token['token_link']) . '" target="_blank">Link de Inscrição</a>';
                                    } else {
                                        echo 'Token não disponível';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="update_status" value="1">
                                            <input type="hidden" name="convite_id" value="<?php echo $invite['ID_Convite']; ?>">
                                            <?php if ($invite['status'] == 'ativo'): ?>
                                                <input type="hidden" name="novo_status" value="finalizado">
                                                <button type="submit" class="btn-success" title="Finalizar Convite">✓</button>
                                            <?php endif; ?>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="update_status" value="1">
                                            <input type="hidden" name="convite_id" value="<?php echo $invite['ID_Convite']; ?>">
                                            <input type="hidden" name="novo_status" value="cancelado">
                                            <button type="submit" class="btn-danger" title="Cancelar Convite" onclick="return confirm('Tem certeza que deseja cancelar este convite?')">✕</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Nenhum convite criado ainda.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>