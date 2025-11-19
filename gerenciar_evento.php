<?php
include 'config.php';

verificarLogin();

if (!verificarEmailConfirmado()) {
    redirecionar('confirmar_email.php');
}

$modId = $_SESSION['mod_id'];
$erro = '';
$sucesso = '';

// Verificar se foi passado ID do evento
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirecionar('dashboard.php');
}

$idEvento = (int)$_GET['id'];

// Verificar se o evento pertence ao moderador
$stmt = $pdo->prepare("SELECT * FROM convite WHERE ID_Convite = ? AND ID_Mod = ?");
$stmt->execute([$idEvento, $modId]);
$evento = $stmt->fetch();

if (!$evento) {
    redirecionar('dashboard.php');
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // APROVAR SOLICITAÇÃO
    if (isset($_POST['aprovar'])) {
        $idSolicitacao = (int)$_POST['id_solicitacao'];
        
        try {
            // Gerar código único para QR Code
            $qrCode = 'ECHO-' . str_pad($idSolicitacao, 8, '0', STR_PAD_LEFT) . '-' . time();
            
            // Atualizar solicitação
            $stmt = $pdo->prepare("
                UPDATE solicitacao 
                SET status = 'aprovada', qr_code = ?
                WHERE ID_Solicitacao = ? AND status = 'pendente'
            ");
            $stmt->execute([$qrCode, $idSolicitacao]);
            
            if ($stmt->rowCount() > 0) {
                // Buscar informações do participante
                $stmt = $pdo->prepare("
                    SELECT s.*, c.nome_evento, c.data_evento, c.hora_evento
                    FROM solicitacao s
                    JOIN convite c ON s.ID_Convite = c.ID_Convite
                    WHERE s.ID_Solicitacao = ?
                ");
                $stmt->execute([$idSolicitacao]);
                $solicitacao = $stmt->fetch();
                
                // Tentar gerar QR Code (se biblioteca existir)
                try {
                    if (file_exists('phpqrcode/qrlib.php')) {
                        require_once 'phpqrcode/qrlib.php';
                        
                        $qrDir = 'qrcodes/';
                        if (!file_exists($qrDir)) {
                            mkdir($qrDir, 0777, true);
                        }
                        $qrFile = $qrDir . $qrCode . '.png';
                        QRcode::png($qrCode, $qrFile, QR_ECLEVEL_L, 8);
                    }
                } catch (Exception $e) {
                    // Se der erro no QR Code, continua mesmo assim
                }
                
                // Tentar enviar e-mail (se configurado)
                try {
                    $dataEvento = formatarDataBR($solicitacao['data_evento']);
                    $horaEvento = formatarHoraBR($solicitacao['hora_evento']);
                    
                    $assunto = "Solicitação Aprovada - {$solicitacao['nome_evento']}";
                    $mensagem = "
                        <html>
                        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                                <div style='background: #50c878; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                                    <h1 style='margin: 0;'>✅ Solicitação Aprovada!</h1>
                                </div>
                                <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px;'>
                                    <p>Olá, <strong>{$solicitacao['nome_cliente']}</strong>!</p>
                                    <p>Sua solicitação para participar do evento foi aprovada!</p>
                                    
                                    <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                                        <h3 style='color: #4a90e2; margin-top: 0;'>📋 Detalhes do Evento:</h3>
                                        <p><strong>Evento:</strong> {$solicitacao['nome_evento']}</p>
                                        <p><strong>Data:</strong> {$dataEvento}</p>
                                        <p><strong>Horário:</strong> {$horaEvento}</p>
                                        <p><strong>Assento:</strong> {$solicitacao['num_polt']}</p>
                                    </div>
                                    
                                    <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;'>
                                        <h3 style='color: #4a90e2; margin-top: 0;'>🎫 Seu QR Code:</h3>
                                        <p>Apresente este código na entrada do evento:</p>
                                        <img src='cid:qrcode_img' alt='QR Code' style='max-width: 300px; margin: 20px 0;'>
                                        <p style='font-size: 12px; color: #777;'>Código: <strong>{$qrCode}</strong></p>
                                    </div>
                                    
                                    <p style='color: #e74c3c; font-weight: bold; text-align: center;'>⚠️ Guarde este e-mail! Você precisará do QR Code para entrar no evento.</p>
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    // Verificar se QR Code foi gerado e enviar com anexo
                    if (isset($qrFile) && file_exists($qrFile)) {
                        enviarEmailComQRCode($solicitacao['email_cliente'], $assunto, $mensagem, $qrFile, 'aprovacao');
                    } else {
                        enviarEmail($solicitacao['email_cliente'], $assunto, $mensagem, 'aprovacao');
                    }
                } catch (Exception $e) {
                    // Se der erro no e-mail, continua mesmo assim
                }
                
                $sucesso = "Solicitação aprovada com sucesso! Código: {$qrCode}";
            } else {
                $erro = "Erro ao aprovar solicitação. Ela pode já ter sido processada.";
            }
            
        } catch (Exception $e) {
            $erro = "Erro ao aprovar solicitação: " . $e->getMessage();
        }
    }
    
    // REJEITAR SOLICITAÇÃO
    elseif (isset($_POST['rejeitar'])) {
        $idSolicitacao = (int)$_POST['id_solicitacao'];
        $motivo = trim($_POST['motivo_rejeicao'] ?? 'Não especificado');
        
        try {
            $stmt = $pdo->prepare("
                UPDATE solicitacao 
                SET status = 'rejeitada', 
                    observacoes = CONCAT(COALESCE(observacoes, ''), ' | Motivo: ', ?)
                WHERE ID_Solicitacao = ? AND status = 'pendente'
            ");
            $stmt->execute([$motivo, $idSolicitacao]);
            
            if ($stmt->rowCount() > 0) {
                // Buscar e-mail do solicitante
                $stmt = $pdo->prepare("
                    SELECT s.email_cliente, s.nome_cliente, c.nome_evento
                    FROM solicitacao s
                    JOIN convite c ON s.ID_Convite = c.ID_Convite
                    WHERE s.ID_Solicitacao = ?
                ");
                $stmt->execute([$idSolicitacao]);
                $solicitacao = $stmt->fetch();
                
                // Tentar enviar e-mail
                try {
                    $assunto = "Solicitação Não Aprovada - {$solicitacao['nome_evento']}";
                    $mensagem = "
                        <html>
                        <body style='font-family: Arial, sans-serif;'>
                            <h2 style='color: #e74c3c;'>Solicitação Não Aprovada</h2>
                            <p>Olá, {$solicitacao['nome_cliente']}!</p>
                            <p>Infelizmente sua solicitação para o evento <strong>{$solicitacao['nome_evento']}</strong> não foi aprovada.</p>
                            <p><strong>Motivo:</strong> {$motivo}</p>
                            <p>Agradecemos seu interesse!</p>
                        </body>
                        </html>
                    ";
                    
                    enviarEmail($solicitacao['email_cliente'], $assunto, $mensagem, 'rejeicao');
                } catch (Exception $e) {
                    // Se der erro no e-mail, continua mesmo assim
                }
                
                $sucesso = "Solicitação rejeitada com sucesso.";
            } else {
                $erro = "Erro ao rejeitar solicitação.";
            }
            
        } catch (Exception $e) {
            $erro = "Erro ao rejeitar solicitação: " . $e->getMessage();
        }
    }
    
    // CANCELAR EVENTO
    elseif (isset($_POST['cancelar_evento'])) {
        try {
            $stmt = $pdo->prepare("UPDATE convite SET status = 'cancelado' WHERE ID_Convite = ?");
            $stmt->execute([$idEvento]);
            
            // Buscar todos os participantes aprovados
            $stmt = $pdo->prepare("
                SELECT email_cliente, nome_cliente 
                FROM solicitacao 
                WHERE ID_Convite = ? AND status = 'aprovada'
            ");
            $stmt->execute([$idEvento]);
            $participantes = $stmt->fetchAll();
            
            // Tentar enviar e-mail para todos
            foreach ($participantes as $part) {
                try {
                    $assunto = "Evento Cancelado - {$evento['nome_evento']}";
                    $mensagem = "
                        <html>
                        <body style='font-family: Arial, sans-serif;'>
                            <h2 style='color: #e74c3c;'>Evento Cancelado</h2>
                            <p>Olá, {$part['nome_cliente']}!</p>
                            <p>Infelizmente o evento <strong>{$evento['nome_evento']}</strong> foi cancelado.</p>
                            <p>Pedimos desculpas pelo transtorno.</p>
                        </body>
                        </html>
                    ";
                    
                    enviarEmail($part['email_cliente'], $assunto, $mensagem, 'cancelamento');
                } catch (Exception $e) {
                    // Se der erro no e-mail, continua mesmo assim
                }
            }
            
            $sucesso = "Evento cancelado. " . count($participantes) . " participante(s) foram notificados.";
            $evento['status'] = 'cancelado';
            
        } catch (Exception $e) {
            $erro = "Erro ao cancelar evento: " . $e->getMessage();
        }
    }
}

// Buscar solicitações do evento
$stmt = $pdo->prepare("
    SELECT * FROM solicitacao 
    WHERE ID_Convite = ? 
    ORDER BY 
        CASE status
            WHEN 'pendente' THEN 1
            WHEN 'aprovada' THEN 2
            WHEN 'rejeitada' THEN 3
            ELSE 4
        END,
        data_envio_solicitacao DESC
");
$stmt->execute([$idEvento]);
$solicitacoes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Evento - <?php echo htmlspecialchars($evento['nome_evento']); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <nav class="navbar">
                <a href="dashboard.php" class="logo">EchoPass</a>
                <ul class="nav-links">
                    <li><a href="dashboard.php">← Voltar ao Dashboard</a></li>
                    <li>
                        <div class="user-info">
                            <span>👤 <?php echo htmlspecialchars($_SESSION['mod_nome']); ?></span>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <div class="container">
        <?php if ($erro): ?>
            <div class="alert alert-error"><?php echo $erro; ?></div>
        <?php endif; ?>
        
        <?php if ($sucesso): ?>
            <div class="alert alert-success"><?php echo $sucesso; ?></div>
        <?php endif; ?>

        <!-- Informações do Evento -->
        <div class="card">
            <div class="card-header flex-between">
                <div>
                    <h2><?php echo htmlspecialchars($evento['nome_evento']); ?></h2>
                    <p style="color: #777; margin-top: 5px;">
                        <?php echo formatarDataBR($evento['data_evento']); ?> às <?php echo formatarHoraBR($evento['hora_evento']); ?>
                    </p>
                </div>
                <span class="badge <?php 
                    echo $evento['status'] == 'ativo' ? 'badge-success' : 
                        ($evento['status'] == 'cancelado' ? 'badge-danger' : 'badge-secondary'); 
                ?>">
                    <?php echo ucfirst($evento['status']); ?>
                </span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div>
                    <strong>📍 Local:</strong><br>
                    CEP: <?php echo $evento['cep']; ?>
                    <?php if ($evento['endereco_completo']): ?>
                        <br><small style="color: #777;"><?php echo htmlspecialchars($evento['endereco_completo']); ?></small>
                    <?php endif; ?>
                </div>
                <div>
                    <strong>👥 Capacidade:</strong><br>
                    <?php echo $evento['num_max']; ?> pessoas
                </div>
                <?php if ($evento['idade_minima'] > 0): ?>
                <div>
                    <strong>🔞 Idade Mínima:</strong><br>
                    <?php echo $evento['idade_minima']; ?> anos
                </div>
                <?php endif; ?>
            </div>

            <?php if ($evento['descricao']): ?>
            <div style="margin-bottom: 20px;">
                <strong>Descrição:</strong>
                <p style="color: #555; margin-top: 5px;"><?php echo nl2br(htmlspecialchars($evento['descricao'])); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($evento['status'] == 'ativo'): ?>
            <div style="border-top: 1px solid var(--border-color); padding-top: 20px; display: flex; gap: 10px;">
                <a href="editar_evento.php?id=<?php echo $idEvento; ?>" class="btn btn-primary">
                    ✏️ Editar Evento
                </a>
                <form method="POST" onsubmit="return confirm('Tem certeza que deseja cancelar este evento? Todos os participantes serão notificados.');" style="display: inline;">
                    <button type="submit" name="cancelar_evento" class="btn btn-danger">
                        ❌ Cancelar Evento
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Lista de Solicitações -->
        <div class="card">
            <div class="card-header">
                <h2>Solicitações de Participação</h2>
            </div>

            <?php if (count($solicitacoes) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Idade</th>
                                <th>Assento</th>
                                <th>Data Solicitação</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitacoes as $sol): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($sol['nome_cliente']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($sol['email_cliente']); ?></td>
                                    <td><?php echo calcularIdade($sol['data_nasc']); ?> anos</td>
                                    <td><?php echo $sol['num_polt']; ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($sol['data_envio_solicitacao'])); ?></td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'pendente' => 'badge-warning',
                                            'aprovada' => 'badge-success',
                                            'rejeitada' => 'badge-danger',
                                            'cancelada' => 'badge-secondary'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $badges[$sol['status']]; ?>">
                                            <?php echo ucfirst($sol['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($sol['status'] == 'pendente'): ?>
                                            <div class="table-actions">
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Aprovar esta solicitação?');">
                                                    <input type="hidden" name="id_solicitacao" value="<?php echo $sol['ID_Solicitacao']; ?>">
                                                    <button type="submit" name="aprovar" class="btn btn-success btn-sm">
                                                        ✓ Aprovar
                                                    </button>
                                                </form>
                                                <button onclick="mostrarModalRejeicao(<?php echo $sol['ID_Solicitacao']; ?>)" class="btn btn-danger btn-sm">
                                                    ✗ Rejeitar
                                                </button>
                                            </div>
                                        <?php elseif ($sol['status'] == 'aprovada' && $sol['qr_code']): ?>
                                            <button onclick="mostrarCodigo('<?php echo $sol['qr_code']; ?>', '<?php echo htmlspecialchars($sol['nome_cliente']); ?>')" class="btn btn-primary btn-sm">
                                                📱 Ver Código
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center">
                    Nenhuma solicitação recebida ainda.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Rejeição -->
    <div id="modalRejeicao" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2>Rejeitar Solicitação</h2>
                <button class="modal-close" onclick="fecharModalRejeicao()" type="button">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_solicitacao" id="idSolicitacaoRejeitar">
                    <div class="form-group">
                        <label for="motivo_rejeicao" class="required">Motivo da Rejeição</label>
                        <textarea name="motivo_rejeicao" id="motivo_rejeicao" rows="4" required placeholder="Explique o motivo da rejeição..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fecharModalRejeicao()">Cancelar</button>
                    <button type="submit" name="rejeitar" class="btn btn-danger">Rejeitar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Código -->
    <div id="modalCodigo" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 id="tituloCodigo">Código do Participante</h2>
                <button class="modal-close" onclick="fecharModalCodigo()" type="button">&times;</button>
            </div>
            <div class="modal-body">
                <div class="qr-container">
                    <div id="codigoConteudo" style="text-align: center;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2025 EchoPass - Sistema de Gerenciamento de Eventos</p>
    </div>

    <script>
        function mostrarModalRejeicao(idSolicitacao) {
            document.getElementById('idSolicitacaoRejeitar').value = idSolicitacao;
            document.getElementById('modalRejeicao').classList.add('active');
        }

        function fecharModalRejeicao() {
            document.getElementById('modalRejeicao').classList.remove('active');
        }

        function mostrarCodigo(codigo, nome) {
            document.getElementById('tituloCodigo').textContent = 'Código - ' + nome;
            
            // Tentar mostrar imagem QR Code se existir, senão mostra só o código
            let html = '<img src="qrcodes/' + codigo + '.png" alt="QR Code" style="max-width: 300px;" onerror="this.style.display=\'none\'">';
            html += '<p style="font-size: 20px; font-weight: bold; margin-top: 20px; color: #4a90e2;">' + codigo + '</p>';
            html += '<p style="color: #777; font-size: 14px; margin-top: 10px;">O participante deve apresentar este código na entrada</p>';
            
            document.getElementById('codigoConteudo').innerHTML = html;
            document.getElementById('modalCodigo').classList.add('active');
        }

        function fecharModalCodigo() {
            document.getElementById('modalCodigo').classList.remove('active');
        }

        // Fechar modais ao clicar fora
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // Fechar modais com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });
    </script>
</body>
</html>