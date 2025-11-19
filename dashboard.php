<?php
include 'config.php';

verificarLogin();

if (!verificarEmailConfirmado()) {
    redirecionar('confirmar_email.php');
}

$modId = $_SESSION['mod_id'];
$modNome = $_SESSION['mod_nome'];

// Buscar estatísticas
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_eventos 
    FROM convite 
    WHERE ID_Mod = ?
");
$stmt->execute([$modId]);
$stats = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_solicitacoes 
    FROM solicitacao s
    JOIN convite c ON s.ID_Convite = c.ID_Convite
    WHERE c.ID_Mod = ? AND s.status = 'pendente'
");
$stmt->execute([$modId]);
$pendentes = $stmt->fetch();

// Buscar eventos do moderador
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        COUNT(CASE WHEN s.status = 'aprovada' THEN 1 END) as participantes_aprovados,
        COUNT(CASE WHEN s.status = 'pendente' THEN 1 END) as solicitacoes_pendentes,
        (c.num_max - COUNT(CASE WHEN s.status = 'aprovada' THEN 1 END)) as vagas_disponiveis
    FROM convite c
    LEFT JOIN solicitacao s ON c.ID_Convite = s.ID_Convite
    WHERE c.ID_Mod = ?
    GROUP BY c.ID_Convite
    ORDER BY c.data_evento DESC, c.hora_evento DESC
");
$stmt->execute([$modId]);
$eventos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EchoPass</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <nav class="navbar">
                <a href="dashboard.php" class="logo">EchoPass</a>
                <ul class="nav-links">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="criar_evento.php">Novo Evento</a></li>
                    <li><a href="index.php" target="_blank">Ver Eventos Públicos</a></li>
                    <li>
                        <div class="user-info">
                            <span>👤 <?php echo htmlspecialchars($modNome); ?></span>
                            <a href="?logout=1" style="color: var(--danger-color); text-decoration: none;">Sair</a>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <div class="container">
        <!-- Estatísticas -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h3 style="margin-bottom: 10px;">Total de Eventos</h3>
                <p style="font-size: 36px; font-weight: bold; margin: 0;"><?php echo $stats['total_eventos']; ?></p>
            </div>
            <div class="card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <h3 style="margin-bottom: 10px;">Solicitações Pendentes</h3>
                <p style="font-size: 36px; font-weight: bold; margin: 0;"><?php echo $pendentes['total_solicitacoes']; ?></p>
            </div>
        </div>

        <!-- Lista de Eventos -->
        <div class="card">
            <div class="card-header flex-between">
                <h2>Meus Eventos</h2>
                <a href="criar_evento.php" class="btn btn-primary">
                    ➕ Criar Novo Evento
                </a>
            </div>

            <?php if (count($eventos) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nome do Evento</th>
                                <th>Data/Hora</th>
                                <th>Status</th>
                                <th>Vagas</th>
                                <th>Pendentes</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventos as $evento): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($evento['nome_evento']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo formatarDataBR($evento['data_evento']); ?><br>
                                        <small style="color: #777;"><?php echo formatarHoraBR($evento['hora_evento']); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'ativo' => 'badge-success',
                                            'inativo' => 'badge-secondary',
                                            'cancelado' => 'badge-danger',
                                            'finalizado' => 'badge-info'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $statusClass[$evento['status']]; ?>">
                                            <?php echo ucfirst($evento['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $evento['participantes_aprovados']; ?> / <?php echo $evento['num_max']; ?>
                                        <br>
                                        <small style="color: #777;">
                                            <?php echo $evento['vagas_disponiveis']; ?> disponíveis
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($evento['solicitacoes_pendentes'] > 0): ?>
                                            <span class="badge badge-warning">
                                                <?php echo $evento['solicitacoes_pendentes']; ?> pendente(s)
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999;">Nenhuma</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="gerenciar_evento.php?id=<?php echo $evento['ID_Convite']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                Gerenciar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center">
                    <strong>Você ainda não criou nenhum evento</strong><br>
                    Clique em "Criar Novo Evento" para começar!
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2025 EchoPass - Sistema de Gerenciamento de Eventos</p>
    </div>
</body>
</html>