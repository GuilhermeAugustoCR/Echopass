<?php
// admin_convite_individual.php
include 'config.php';

// Verificar se o moderador está logado
if (!isset($_SESSION['mod_id'])) {
    header("Location: mod_evento.php");
    exit();
}

// Obter ID do convite da URL
$convite_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($convite_id <= 0) {
    die("ID do convite inválido");
}

// Verificar se o convite pertence ao moderador logado
$stmt = $pdo->prepare("SELECT * FROM convite WHERE ID_Convite = ? AND ID_Mod = ?");
$stmt->execute([$convite_id, $_SESSION['mod_id']]);
$convite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$convite) {
    die("Convite não encontrado ou você não tem permissão para acessá-lo");
}

// Handle ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_convite_status'])) {
        $novo_status = $_POST['novo_status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE convite SET status = ? WHERE ID_Convite = ? AND ID_Mod = ?");
            $stmt->execute([$novo_status, $convite_id, $_SESSION['mod_id']]);
            
            if ($stmt->rowCount() > 0) {
                $success = "Status do convite atualizado com sucesso!";
                // Atualizar dados do convite
                $convite['status'] = $novo_status;
            } else {
                $error = "Erro ao atualizar status do convite";
            }
        } catch(PDOException $e) {
            $error = "Erro ao atualizar status: " . $e->getMessage();
        }
    } elseif (isset($_POST['aprovar_solicitacao'])) {
        $id_solicitacao = $_POST['id_solicitacao'];
        
        try {
            // Usar uma função alternativa em vez da stored procedure para evitar problemas com cursor
            $stmt = $pdo->prepare("UPDATE solicitacao SET status = 'aprovada' WHERE ID_Solicitacao = ? AND status = 'pendente'");
            $stmt->execute([$id_solicitacao]);
            
            if ($stmt->rowCount() > 0) {
                $success = "Solicitação aprovada com sucesso!";
            } else {
                $error = "Solicitação não encontrada ou já processada";
            }
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
    } elseif (isset($_POST['atualizar_convite'])) {
        $nome_evento = $_POST['nome_evento'];
        $descricao = $_POST['descricao'];
        $faq = $_POST['faq'];
        $num_max = $_POST['num_max'];
        
        try {
            $stmt = $pdo->prepare("UPDATE convite SET nome_evento = ?, descricao = ?, faq = ?, num_max = ? WHERE ID_Convite = ? AND ID_Mod = ?");
            $stmt->execute([$nome_evento, $descricao, $faq, $num_max, $convite_id, $_SESSION['mod_id']]);
            
            if ($stmt->rowCount() > 0) {
                $success = "Convite atualizado com sucesso!";
                // Atualizar dados do convite
                $convite['nome_evento'] = $nome_evento;
                $convite['descricao'] = $descricao;
                $convite['faq'] = $faq;
                $convite['num_max'] = $num_max;
            } else {
                $error = "Erro ao atualizar convite";
            }
        } catch(PDOException $e) {
            $error = "Erro ao atualizar convite: " . $e->getMessage();
        }
    }
}

// Obter estatísticas do convite - CORRIGIDO
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_solicitacoes,
        SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
        SUM(CASE WHEN status = 'aprovada' THEN 1 ELSE 0 END) as aprovadas,
        SUM(CASE WHEN status = 'rejeitada' THEN 1 ELSE 0 END) as rejeitadas
    FROM solicitacao 
    WHERE ID_Convite = ?
");
$stats_stmt->execute([$convite_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Garantir que as estatísticas não sejam nulas
if (!$stats) {
    $stats = [
        'total_solicitacoes' => 0,
        'pendentes' => 0,
        'aprovadas' => 0,
        'rejeitadas' => 0
    ];
}

// Obter todas as solicitações deste convite
$solicitacoes_stmt = $pdo->prepare("
    SELECT s.* 
    FROM solicitacao s 
    WHERE s.ID_Convite = ? 
    ORDER BY s.data_envio_solicitacao DESC
");
$solicitacoes_stmt->execute([$convite_id]);
$solicitacoes = $solicitacoes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter token ativo
$token_stmt = $pdo->prepare("SELECT * FROM token WHERE ID_Convite = ? AND status = 'ativo' ORDER BY data_criacao DESC LIMIT 1");
$token_stmt->execute([$convite_id]);
$token = $token_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?php echo htmlspecialchars($convite['nome_evento']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; }
        .nav { background: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .nav a { margin-right: 20px; text-decoration: none; color: #007bff; font-weight: 500; }
        .nav a:hover { text-decoration: underline; }
        .dashboard { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .section { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-height: 300px; }
        .section-full { grid-column: 1 / -1; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 20px; }
        .stat-card { background: #f8f9fa; padding: 15px; border-radius: 6px; text-align: center; }
        .stat-number { font-size: 1.5em; font-weight: bold; }
        .stat-pendente { color: #ffc107; }
        .stat-aprovada { color: #28a745; }
        .stat-rejeitada { color: #dc3545; }
        .stat-total { color: #007bff; }
        
        .event-header { 
            background: linear-gradient(135deg, #007bff, #0056b3); 
            color: white; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
        }
        .event-info-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 10px; 
            margin-top: 15px; 
        }
        .info-item { 
            background: rgba(255,255,255,0.1); 
            padding: 10px; 
            border-radius: 4px; 
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px; 
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 10px; 
            text-align: left; 
        }
        th { 
            background-color: #f8f9fa; 
            font-weight: 600; 
        }
        
        /* Status colors */
        .status-pendente { color: #ffc107; font-weight: bold; }
        .status-aprovada { color: #28a745; font-weight: bold; }
        .status-rejeitada { color: #dc3545; font-weight: bold; }
        .status-cancelada { color: #6c757d; font-weight: bold; }
        .status-ativo { color: #28a745; font-weight: bold; }
        .status-inativo { color: #6c757d; font-weight: bold; }
        .status-cancelado { color: #dc3545; font-weight: bold; }
        .status-finalizado { color: #17a2b8; font-weight: bold; }
        
        .error { 
            color: #dc3545; 
            margin: 10px 0; 
            padding: 10px; 
            background: #f8d7da; 
            border: 1px solid #f5c6cb; 
            border-radius: 4px; 
        }
        .success { 
            color: #155724; 
            margin: 10px 0; 
            padding: 10px; 
            background: #d4edda; 
            border: 1px solid #c3e6cb; 
            border-radius: 4px; 
        }
        
        .action-buttons { 
            display: flex; 
            gap: 5px; 
            flex-wrap: wrap; 
        }
        button, .btn { 
            padding: 8px 12px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block; 
            font-size: 12px; 
        }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        
        input, textarea, select { 
            width: 100%; 
            padding: 8px; 
            margin: 5px 0; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box; 
        }
        .form-group { 
            margin-bottom: 15px; 
        }
        .capacity-warning { 
            color: #dc3545; 
            font-weight: bold; 
        }
        .token-link { 
            word-break: break-all; 
            background: rgba(255,255,255,0.2); 
            padding: 10px; 
            border-radius: 4px; 
            margin: 10px 0; 
        }
        
        @media (max-width: 1200px) {
            .dashboard {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="convite.php">← Voltar para Convites</a>
            <a href="admin_convite.php">Painel Geral</a>
            <span style="float: right;">
                Olá, <?php echo htmlspecialchars($_SESSION['mod_nome']); ?> | 
                <a href="mod_evento.php?logout=true" style="color: #007bff;">Sair</a>
            </span>
        </div>

        <!-- Mensagens de sucesso/erro -->
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Cabeçalho do Evento -->
        <div class="event-header">
            <h1><?php echo htmlspecialchars($convite['nome_evento']); ?></h1>
            <div class="event-info-grid">
                <div class="info-item">
                    <strong>Data:</strong> <?php echo date('d/m/Y', strtotime($convite['data_evento'])); ?>
                </div>
                <div class="info-item">
                    <strong>Horário:</strong> <?php echo substr($convite['hora_evento'], 0, 5); ?>
                </div>
                <div class="info-item">
                    <strong>Status:</strong> 
                    <span class="status-<?php echo $convite['status']; ?>">
                        <?php echo ucfirst($convite['status']); ?>
                    </span>
                </div>
                <div class="info-item">
                    <strong>Capacidade:</strong> 
                    <?php echo $stats['aprovadas'] . '/' . $convite['num_max']; ?>
                    <?php if ($stats['aprovadas'] >= $convite['num_max']): ?>
                        <span class="capacity-warning"> (Lotado)</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($token): ?>
                <div class="token-link">
                    <strong>Link de Inscrição:</strong><br>
                    <a href="solicitation.php?token=<?php echo urlencode($token['token_link']); ?>" target="_blank" style="color: white; text-decoration: underline;">
                        <?php echo $token['token_link']; ?>
                    </a>
                    <br>
                    <small>Expira em: <?php echo $token['data_expiracao'] ? date('d/m/Y', strtotime($token['data_expiracao'])) : 'Não definida'; ?></small>
                </div>
            <?php else: ?>
                <div class="token-link" style="background: rgba(220,53,69,0.2);">
                    <strong>⚠️ Nenhum token ativo encontrado</strong><br>
                    <small>Volte para a página de convites e gere um novo token.</small>
                </div>
            <?php endif; ?>
        </div>

        <!-- Dashboard com 3 seções -->
        <div class="dashboard">
            
            <!-- SEÇÃO 1: Informações do Convite -->
            <div class="section">
                <h2>📋 Informações do Convite</h2>
                <form method="POST">
                    <input type="hidden" name="atualizar_convite" value="1">
                    
                    <div class="form-group">
                        <label><strong>Nome do Evento:</strong></label>
                        <input type="text" name="nome_evento" value="<?php echo htmlspecialchars($convite['nome_evento']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><strong>Descrição:</strong></label>
                        <textarea name="descricao" rows="3" placeholder="Descrição do evento..."><?php echo htmlspecialchars($convite['descricao'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><strong>Perguntas Frequentes (FAQ):</strong></label>
                        <textarea name="faq" rows="3" placeholder="Informações adicionais..."><?php echo htmlspecialchars($convite['faq'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><strong>Capacidade Máxima:</strong></label>
                        <input type="number" name="num_max" value="<?php echo $convite['num_max']; ?>" min="1" required>
                    </div>
                    
                    <button type="submit" class="btn-info">💾 Atualizar Informações</button>
                </form>

                <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid #eee;">
                    <h3>🔄 Alterar Status do Convite</h3>
                    <form method="POST">
                        <input type="hidden" name="update_convite_status" value="1">
                        <select name="novo_status" required style="margin-bottom: 10px;">
                            <option value="">Selecione um status</option>
                            <option value="ativo" <?php echo ($convite['status'] == 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                            <option value="inativo" <?php echo ($convite['status'] == 'inativo') ? 'selected' : ''; ?>>Inativo</option>
                            <option value="cancelado" <?php echo ($convite['status'] == 'cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                            <option value="finalizado" <?php echo ($convite['status'] == 'finalizado') ? 'selected' : ''; ?>>Finalizado</option>
                        </select>
                        <button type="submit" class="btn-warning">🔄 Alterar Status</button>
                    </form>
                </div>
            </div>

            <!-- SEÇÃO 2: Estatísticas e Resumo -->
            <div class="section">
                <h2>📊 Estatísticas</h2>
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-number stat-total"><?php echo $stats['total_solicitacoes']; ?></div>
                        <div>Total</div>
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

                <div style="margin-top: 25px;">
                    <h3>📅 Informações do Evento</h3>
                    <p><strong>Data de Criação:</strong> <?php echo date('d/m/Y H:i', strtotime($convite['conv_data_cria'])); ?></p>
                    <p><strong>CEP:</strong> <?php echo $convite['cep']; ?></p>
                    <p><strong>Capacidade Ocupada:</strong> 
                        <?php echo $stats['aprovadas'] . ' de ' . $convite['num_max']; ?>
                        (<?php echo $convite['num_max'] > 0 ? round(($stats['aprovadas'] / $convite['num_max']) * 100, 1) : 0; ?>%)
                    </p>
                    
                    <?php if ($stats['aprovadas'] >= $convite['num_max']): ?>
                        <div class="error" style="margin-top: 10px; padding: 10px;">
                            ⚠️ <strong>Capacidade máxima atingida!</strong>
                        </div>
                    <?php elseif ($stats['aprovadas'] >= ($convite['num_max'] * 0.8)): ?>
                        <div style="color: #ffc107; margin-top: 10px; padding: 10px; background: #fff3cd; border-radius: 4px;">
                            ⚠️ <strong>Capacidade próxima do limite (80%)</strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SEÇÃO 3: Ações Rápidas -->
            <div class="section">
                <h2>⚡ Ações Rápidas</h2>
                
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php if ($token): ?>
                        <a href="solicitation.php?token=<?php echo urlencode($token['token_link']); ?>" target="_blank" class="btn-success" style="text-align: center;">
                            👀 Visualizar Página de Inscrição
                        </a>
                        
                        <button onclick="copyToClipboard('<?php echo $token['token_link']; ?>')" class="btn-info">
                            📋 Copiar Link de Inscrição
                        </button>
                    <?php else: ?>
                        <button class="btn-secondary" disabled>
                            👀 Visualizar Página de Inscrição
                        </button>
                        
                        <button class="btn-secondary" disabled>
                            📋 Copiar Link de Inscrição
                        </button>
                    <?php endif; ?>
                    
                    <a href="convite.php" class="btn-warning" style="text-align: center;">
                        📝 Gerenciar Todos os Convites
                    </a>
                </div>

                <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 20px;">
                    <h4>📈 Resumo de Status</h4>
                    <p>✅ <strong>Aprovadas:</strong> <?php echo $stats['aprovadas']; ?></p>
                    <p>⏳ <strong>Pendentes:</strong> <?php echo $stats['pendentes']; ?></p>
                    <p>❌ <strong>Rejeitadas:</strong> <?php echo $stats['rejeitadas']; ?></p>
                    <p>📊 <strong>Total:</strong> <?php echo $stats['total_solicitacoes']; ?></p>
                </div>
            </div>

            <!-- SEÇÃO 4: Solicitações (ocupa toda a largura) -->
            <div class="section section-full">
                <h2>👥 Solicitações de Participação (<?php echo count($solicitacoes); ?>)</h2>
                
                <?php if (count($solicitacoes) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>CPF</th>
                                    <th>E-mail</th>
                                    <th>Data Nasc.</th>
                                    <th>Assento</th>
                                    <th>Data Envio</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($solicitacoes as $solicitacao): 
                                    $status_class = 'status-' . $solicitacao['status'];
                                    $capacidade_atingida = $stats['aprovadas'] >= $convite['num_max'];
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($solicitacao['nome_cliente']); ?></td>
                                        <td><?php echo htmlspecialchars($solicitacao['cpf_cliente']); ?></td>
                                        <td><?php echo htmlspecialchars($solicitacao['email_cliente']); ?></td>
                                        <td><?php echo $solicitacao['data_nasc'] ? date('d/m/Y', strtotime($solicitacao['data_nasc'])) : 'N/A'; ?></td>
                                        <td><?php echo $solicitacao['num_polt'] ? $solicitacao['num_polt'] : 'N/A'; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($solicitacao['data_envio_solicitacao'])); ?></td>
                                        <td>
                                            <span class="<?php echo $status_class; ?>">
                                                <?php echo ucfirst($solicitacao['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($solicitacao['status'] == 'pendente'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="aprovar_solicitacao" value="1">
                                                        <input type="hidden" name="id_solicitacao" value="<?php echo $solicitacao['ID_Solicitacao']; ?>">
                                                        <button type="submit" class="btn-success" 
                                                            <?php echo $capacidade_atingida ? 'disabled title="Capacidade máxima atingida"' : 'title="Aprovar Solicitação"'; ?>>
                                                            ✅ Aprovar
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="rejeitar_solicitacao" value="1">
                                                        <input type="hidden" name="id_solicitacao" value="<?php echo $solicitacao['ID_Solicitacao']; ?>">
                                                        <button type="submit" class="btn-danger" title="Rejeitar Solicitação">
                                                            ❌ Rejeitar
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="btn-secondary">Processada</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($solicitacao['observacoes'])): ?>
                                                <div style="font-size: 11px; color: #666; margin-top: 5px;">
                                                    <strong>Obs:</strong> <?php echo htmlspecialchars($solicitacao['observacoes']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #6c757d;">
                        <h3>📭 Nenhuma solicitação encontrada</h3>
                        <p>Quando as pessoas se inscreverem no seu evento, elas aparecerão aqui.</p>
                        <?php if ($token): ?>
                            <p>Compartilhe este link para receber inscrições:</p>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0;">
                                <code><?php echo $token['token_link']; ?></code>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('✅ Link copiado para a área de transferência:\n' + text);
        }, function(err) {
            console.error('Erro ao copiar: ', err);
            alert('❌ Erro ao copiar o link. Tente manualmente.');
        });
    }
    </script>
</body>
</html>