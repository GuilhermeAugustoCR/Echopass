<?php
// admin_convite.php
include 'config.php';

// Verificar se o moderador está logado
if (!isset($_SESSION['mod_id'])) {
    header("Location: mod_evento.php");
    exit();
}

// Handle ações nas solicitações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['aprovar_solicitacao'])) {
        $id_solicitacao = $_POST['id_solicitacao'];
        
        try {
            $stmt = $pdo->prepare("CALL sp_aprovar_solicitacao(?)");
            $stmt->execute([$id_solicitacao]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $success = $result['mensagem'];
        } catch(PDOException $e) {
            $error = "Erro ao aprovar solicitação: " . $e->getMessage();
        }
    } elseif (isset($_POST['rejeitar_solicitacao'])) {
        $id_solicitacao = $_POST['id_solicitacao'];
        $observacoes = $_POST['observacoes'] ?? '';
        
        try {
            $stmt = $pdo->prepare("UPDATE solicitacao SET status = 'rejeitada', observacoes = ? WHERE ID_Solicitacao = ? AND status = 'pendente'");
            $stmt->execute([$observacoes, $id_solicitacao]);
            
            if ($stmt->rowCount() > 0) {
                $success = "Solicitação rejeitada com sucesso!";
            } else {
                $error = "Solicitação não encontrada ou já processada";
            }
        } catch(PDOException $e) {
            $error = "Erro ao rejeitar solicitação: " . $e->getMessage();
        }
    }
}

// Obter todas as solicitações para os convites deste moderador
$stmt = $pdo->prepare("
    SELECT s.*, c.nome_evento, c.data_evento, c.hora_evento, c.num_max,
           (SELECT COUNT(*) FROM solicitacao s2 WHERE s2.ID_Convite = c.ID_Convite AND s2.status = 'aprovada') as aprovadas_count
    FROM solicitacao s 
    JOIN convite c ON s.ID_Convite = c.ID_Convite 
    WHERE c.ID_Mod = ? 
    ORDER BY s.data_envio_solicitacao DESC
");
$stmt->execute([$_SESSION['mod_id']]);
$solicitations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_solicitacoes,
        SUM(CASE WHEN s.status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
        SUM(CASE WHEN s.status = 'aprovada' THEN 1 ELSE 0 END) as aprovadas,
        SUM(CASE WHEN s.status = 'rejeitada' THEN 1 ELSE 0 END) as rejeitadas
    FROM solicitacao s 
    JOIN convite c ON s.ID_Convite = c.ID_Convite 
    WHERE c.ID_Mod = ?
");
$stats_stmt->execute([$_SESSION['mod_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; }
        .nav { background: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .nav a { margin-right: 20px; text-decoration: none; color: #007bff; font-weight: 500; }
        .nav a:hover { text-decoration: underline; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2em; font-weight: bold; }
        .stat-pendente { color: #ffc107; }
        .stat-aprovada { color: #28a745; }
        .stat-rejeitada { color: #dc3545; }
        .stat-total { color: #007bff; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: 600; }
        .status-pendente { color: #ffc107; font-weight: bold; }
        .status-aprovada { color: #28a745; font-weight: bold; }
        .status-rejeitada { color: #dc3545; font-weight: bold; }
        .status-cancelada { color: #6c757d; font-weight: bold; }
        .error { color: #dc3545; margin: 10px 0; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; }
        .success { color: #155724; margin: 10px 0; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; }
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
        button { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        .observacoes { font-size: 12px; color: #666; margin-top: 5px; }
        .capacity-warning { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="convite.php">Gerenciar Convites</a>
            <a href="admin_convite.php">Painel Admin</a>
            <span style="float: right;">Olá, <?php echo $_SESSION['mod_nome']; ?> | <a href="mod_evento.php?logout=true">Sair</a></span>
        </div>
        
        <h1>Painel Administrativo - Solicitações</h1>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Estatísticas -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number stat-total"><?php echo $stats['total_solicitacoes']; ?></div>
                <div>Total de Solicitações</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-pendente"><?php echo $stats['pendentes']; ?></div>
                <div>Pendentes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-aprovada"><?php echo $stats['aprovadas']; ?></div>
                <div>Aprovadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-rejeitada"><?php echo $stats['rejeitadas']; ?></div>
                <div>Rejeitadas</div>
            </div>
        </div>
        
        <?php if (count($solicitations) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Evento</th>
                        <th>CPF</th>
                        <th>E-mail</th>
                        <th>Data Nasc.</th>
                        <th>Assento</th>
                        <th>Data Envio</th>
                        <th>Status</th>
                        <th>Capacidade</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitations as $solicitation): 
                        $status_class = 'status-' . $solicitation['status'];
                        $capacity_warning = $solicitation['aprovadas_count'] >= $solicitation['num_max'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($solicitation['nome_evento']); ?></td>
                            <td><?php echo htmlspecialchars($solicitation['cpf_cliente']); ?></td>
                            <td><?php echo htmlspecialchars($solicitation['email_cliente']); ?></td>
                            <td><?php echo $solicitation['data_nasc'] ? date('d/m/Y', strtotime($solicitation['data_nasc'])) : 'N/A'; ?></td>
                            <td><?php echo $solicitation['num_polt'] ? $solicitation['num_polt'] : 'N/A'; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($solicitation['data_envio_solicitacao'])); ?></td>
                            <td><span class="<?php echo $status_class; ?>"><?php echo ucfirst($solicitation['status']); ?></span></td>
                            <td>
                                <?php echo $solicitation['aprovadas_count'] . '/' . $solicitation['num_max']; ?>
                                <?php if ($capacity_warning): ?>
                                    <br><span class="capacity-warning">Capacidade máxima!</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($solicitation['status'] == 'pendente'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="aprovar_solicitacao" value="1">
                                            <input type="hidden" name="id_solicitacao" value="<?php echo $solicitation['ID_Solicitacao']; ?>">
                                            <button type="submit" class="btn-success" <?php echo $capacity_warning ? 'disabled title="Capacidade máxima atingida"' : 'title="Aprovar Solicitação"'; ?>>Aprovar</button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="rejeitar_solicitacao" value="1">
                                            <input type="hidden" name="id_solicitacao" value="<?php echo $solicitation['ID_Solicitacao']; ?>">
                                            <button type="submit" class="btn-danger" title="Rejeitar Solicitação">Rejeitar</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn-secondary" disabled>Processada</button>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($solicitation['observacoes'])): ?>
                                    <div class="observacoes">Obs: <?php echo htmlspecialchars($solicitation['observacoes']); ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nenhuma solicitação encontrada.</p>
        <?php endif; ?>
    </div>
</body>
</html>