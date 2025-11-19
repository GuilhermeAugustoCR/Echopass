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

// Verificar se o evento pode ser editado (apenas eventos ativos)
if ($evento['status'] != 'ativo') {
    $_SESSION['erro_temp'] = "Apenas eventos ativos podem ser editados.";
    redirecionar('gerenciar_evento.php?id=' . $idEvento);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeEvento = trim($_POST['nome_evento']);
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep']);
    $enderecoCompleto = trim($_POST['endereco_completo'] ?? '');
    $descricao = trim($_POST['descricao']);
    $dataEvento = $_POST['data_evento'];
    $horaEvento = $_POST['hora_evento'];
    $numMax = (int)$_POST['num_max'];
    $idadeMinima = (int)($_POST['idade_minima'] ?? 0);
    $faq = trim($_POST['faq'] ?? '');
    $nomeImagem = trim($_POST['nome_imagem'] ?? '');
    
    // Validações
    if (empty($nomeEvento) || empty($cep) || empty($descricao) || empty($dataEvento) || empty($horaEvento) || empty($numMax)) {
        $erro = "Preencha todos os campos obrigatórios";
    } elseif (strlen($cep) != 8) {
        $erro = "CEP inválido. Digite 8 dígitos";
    } elseif ($numMax < 1 || $numMax > 10000) {
        $erro = "Capacidade máxima deve ser entre 1 e 10.000";
    } elseif ($idadeMinima < 0 || $idadeMinima > 100) {
        $erro = "Idade mínima deve ser entre 0 e 100 anos";
    } else {
        // Validar data (não pode ser no passado)
        $dataEventoObj = new DateTime($dataEvento);
        $hoje = new DateTime();
        $hoje->setTime(0, 0, 0);
        
        if ($dataEventoObj < $hoje) {
            $erro = "A data do evento não pode ser no passado";
        } else {
            // Validar hora (entre 00:00 e 23:59)
            $horaPartes = explode(':', $horaEvento);
            $hora = (int)$horaPartes[0];
            $minuto = (int)$horaPartes[1];
            
            if ($hora < 0 || $hora > 23 || $minuto < 0 || $minuto > 59) {
                $erro = "Horário inválido. Use o formato HH:MM (00:00 - 23:59)";
            } else {
                // Verificar se a nova capacidade é menor que o número de participantes aprovados
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as aprovados 
                    FROM solicitacao 
                    WHERE ID_Convite = ? AND status = 'aprovada'
                ");
                $stmt->execute([$idEvento]);
                $result = $stmt->fetch();
                $aprovados = $result['aprovados'];
                
                if ($numMax < $aprovados) {
                    $erro = "A capacidade máxima não pode ser menor que o número de participantes já aprovados ({$aprovados})";
                } else {
                    // Atualizar evento
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE convite SET
                                nome_evento = ?,
                                cep = ?,
                                endereco_completo = ?,
                                descricao = ?,
                                data_evento = ?,
                                hora_evento = ?,
                                num_max = ?,
                                idade_minima = ?,
                                faq = ?,
                                nome_imagem = ?
                            WHERE ID_Convite = ? AND ID_Mod = ?
                        ");
                        $stmt->execute([
                            $nomeEvento,
                            $cep,
                            $enderecoCompleto,
                            $descricao,
                            $dataEvento,
                            $horaEvento,
                            $numMax,
                            $idadeMinima,
                            $faq,
                            $nomeImagem,
                            $idEvento,
                            $modId
                        ]);
                        
                        // Verificar se tiveram mudanças significativas (data, hora ou local)
                        $mudancasSignificativas = false;
                        if ($evento['data_evento'] != $dataEvento || 
                            $evento['hora_evento'] != $horaEvento || 
                            $evento['cep'] != $cep) {
                            $mudancasSignificativas = true;
                        }
                        
                        // Se tiveram mudanças significativas, notificar participantes aprovados
                        if ($mudancasSignificativas) {
                            $stmt = $pdo->prepare("
                                SELECT email_cliente, nome_cliente 
                                FROM solicitacao 
                                WHERE ID_Convite = ? AND status = 'aprovada'
                            ");
                            $stmt->execute([$idEvento]);
                            $participantes = $stmt->fetchAll();
                            
                            foreach ($participantes as $part) {
                                try {
                                    $assunto = "Alteração no Evento - {$nomeEvento}";
                                    $mensagem = "
                                        <html>
                                        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                                            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                                                <div style='background: #f59e0b; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                                                    <h1 style='margin: 0;'>⚠️ Evento Atualizado</h1>
                                                </div>
                                                <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px;'>
                                                    <p>Olá, <strong>{$part['nome_cliente']}</strong>!</p>
                                                    <p>O evento <strong>{$nomeEvento}</strong> que você está participando foi atualizado.</p>
                                                    
                                                    <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                                                        <h3 style='color: #4a90e2; margin-top: 0;'>📋 Novos Detalhes:</h3>
                                                        <p><strong>Data:</strong> " . formatarDataBR($dataEvento) . "</p>
                                                        <p><strong>Horário:</strong> " . formatarHoraBR($horaEvento) . "</p>
                                                        <p><strong>Local (CEP):</strong> {$cep}</p>
                                                        " . ($enderecoCompleto ? "<p><strong>Endereço:</strong> {$enderecoCompleto}</p>" : "") . "
                                                    </div>
                                                    
                                                    <p style='color: #777;'>Por favor, fique atento às novas informações!</p>
                                                </div>
                                            </div>
                                        </body>
                                        </html>
                                    ";
                                    
                                    enviarEmail($part['email_cliente'], $assunto, $mensagem, 'atualizacao_evento');
                                } catch (Exception $e) {
                                    // Se der erro no e-mail, continua mesmo assim
                                }
                            }
                            
                            $sucesso = "Evento atualizado com sucesso! " . count($participantes) . " participante(s) foram notificados sobre as alterações.";
                        } else {
                            $sucesso = "Evento atualizado com sucesso!";
                        }
                        
                        // Atualizar dados do evento para exibir no formulário
                        $evento = array_merge($evento, [
                            'nome_evento' => $nomeEvento,
                            'cep' => $cep,
                            'endereco_completo' => $enderecoCompleto,
                            'descricao' => $descricao,
                            'data_evento' => $dataEvento,
                            'hora_evento' => $horaEvento,
                            'num_max' => $numMax,
                            'idade_minima' => $idadeMinima,
                            'faq' => $faq,
                            'nome_imagem' => $nomeImagem
                        ]);
                        
                    } catch (PDOException $e) {
                        $erro = "Erro ao atualizar evento: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Buscar número de participantes aprovados
$stmt = $pdo->prepare("
    SELECT COUNT(*) as aprovados 
    FROM solicitacao 
    WHERE ID_Convite = ? AND status = 'aprovada'
");
$stmt->execute([$idEvento]);
$result = $stmt->fetch();
$participantesAprovados = $result['aprovados'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Evento - EchoPass</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <nav class="navbar">
                <a href="dashboard.php" class="logo">EchoPass</a>
                <ul class="nav-links">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="gerenciar_evento.php?id=<?php echo $idEvento; ?>">← Voltar ao Evento</a></li>
                    <li>
                        <div class="user-info">
                            <span>👤 <?php echo htmlspecialchars($_SESSION['mod_nome']); ?></span>
                            <a href="?logout=1" style="color: var(--danger-color); text-decoration: none;">Sair</a>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="card" style="max-width: 900px; margin: 0 auto;">
            <div class="card-header">
                <h2>✏️ Editar Evento</h2>
                <p style="color: #777; margin-top: 10px;">Atualize as informações do evento</p>
            </div>

            <?php if ($erro): ?>
                <div class="alert alert-error"><?php echo $erro; ?></div>
            <?php endif; ?>
            
            <?php if ($sucesso): ?>
                <div class="alert alert-success"><?php echo $sucesso; ?></div>
            <?php endif; ?>

            <?php if ($participantesAprovados > 0): ?>
                <div class="alert alert-warning">
                    ⚠️ <strong>Atenção:</strong> Este evento já possui <?php echo $participantesAprovados; ?> participante(s) aprovado(s). 
                    Alterações em data, horário ou local serão notificadas por e-mail.
                </div>
            <?php endif; ?>

            <form method="POST" id="formEvento">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label for="nome_evento" class="required">Nome do Evento</label>
                        <input 
                            type="text" 
                            id="nome_evento" 
                            name="nome_evento" 
                            placeholder="Ex: Show de Rock"
                            value="<?php echo htmlspecialchars($evento['nome_evento']); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="cep" class="required">CEP</label>
                        <input 
                            type="text" 
                            id="cep" 
                            name="cep" 
                            placeholder="00000-000"
                            maxlength="9"
                            value="<?php echo htmlspecialchars($evento['cep']); ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="endereco_completo">Endereço Completo (Opcional)</label>
                    <input 
                        type="text" 
                        id="endereco_completo" 
                        name="endereco_completo" 
                        placeholder="Rua, número, complemento, cidade - UF"
                        value="<?php echo htmlspecialchars($evento['endereco_completo']); ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="descricao" class="required">Descrição do Evento</label>
                    <textarea 
                        id="descricao" 
                        name="descricao" 
                        placeholder="Descreva seu evento..."
                        rows="5"
                        required
                    ><?php echo htmlspecialchars($evento['descricao']); ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label for="data_evento" class="required">Data do Evento</label>
                        <input 
                            type="date" 
                            id="data_evento" 
                            name="data_evento" 
                            min="<?php echo date('Y-m-d'); ?>"
                            value="<?php echo htmlspecialchars($evento['data_evento']); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="hora_evento" class="required">Hora do Evento</label>
                        <input 
                            type="time" 
                            id="hora_evento" 
                            name="hora_evento" 
                            value="<?php echo htmlspecialchars($evento['hora_evento']); ?>"
                            required
                        >
                        <small style="color: #777;">Formato: HH:MM (00:00 - 23:59)</small>
                    </div>

                    <div class="form-group">
                        <label for="num_max" class="required">Capacidade Máxima</label>
                        <input 
                            type="number" 
                            id="num_max" 
                            name="num_max" 
                            placeholder="Ex: 100"
                            min="<?php echo $participantesAprovados > 0 ? $participantesAprovados : 1; ?>"
                            max="10000"
                            value="<?php echo htmlspecialchars($evento['num_max']); ?>"
                            required
                        >
                        <?php if ($participantesAprovados > 0): ?>
                            <small style="color: #f59e0b;">⚠️ Mínimo: <?php echo $participantesAprovados; ?> (participantes aprovados)</small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="idade_minima">Idade Mínima</label>
                        <input 
                            type="number" 
                            id="idade_minima" 
                            name="idade_minima" 
                            placeholder="Ex: 18"
                            min="0"
                            max="100"
                            value="<?php echo htmlspecialchars($evento['idade_minima']); ?>"
                        >
                        <small style="color: #777;">0 = sem restrição de idade</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="nome_imagem">Nome da Imagem (Opcional)</label>
                    <input 
                        type="text" 
                        id="nome_imagem" 
                        name="nome_imagem" 
                        placeholder="Ex: festa-neon.jpg"
                        value="<?php echo htmlspecialchars($evento['nome_imagem']); ?>"
                    >
                    <small style="color: #777;">
                        📸 Digite apenas o nome do arquivo da imagem que está na pasta <strong>/images/</strong><br>
                        Exemplo: <code>show-rock.jpg</code>, <code>festa-neon.png</code>, <code>evento-corporativo.webp</code><br>
                        <span id="imagePreviewStatus" style="color: #999;"></span>
                    </small>
                </div>

                <div class="form-group">
                    <label for="faq">FAQ / Informações Adicionais (Opcional)</label>
                    <textarea 
                        id="faq" 
                        name="faq" 
                        placeholder="Perguntas frequentes, regras, informações importantes..."
                        rows="5"
                    ><?php echo htmlspecialchars($evento['faq']); ?></textarea>
                </div>

                <div class="btn-group">
                    <a href="gerenciar_evento.php?id=<?php echo $idEvento; ?>" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">💾 Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2025 EchoPass - Sistema de Gerenciamento de Eventos</p>
    </div>

    <script>
        // Máscara para CEP
        document.getElementById('cep').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 8) {
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
                e.target.value = value;
            }
        });

        // Validar horário
        document.getElementById('hora_evento').addEventListener('change', function(e) {
            const hora = e.target.value.split(':');
            const h = parseInt(hora[0]);
            const m = parseInt(hora[1]);
            
            if (h < 0 || h > 23 || m < 0 || m > 59) {
                alert('Horário inválido. Use valores entre 00:00 e 23:59');
                e.target.value = '';
            }
        });

        // Buscar endereço pelo CEP 
        document.getElementById('cep').addEventListener('blur', function() {
            const cep = this.value.replace(/\D/g, '');
            
            if (cep.length === 8) {
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(response => response.json())
                    .then(data => {
                        if (!data.erro) {
                            const endereco = `${data.logradouro}, ${data.bairro}, ${data.localidade} - ${data.uf}`;
                            // Só preenche se o campo estiver vazio
                            if (!document.getElementById('endereco_completo').value) {
                                document.getElementById('endereco_completo').value = endereco;
                            }
                        }
                    })
                    .catch(error => console.log('Erro ao buscar CEP'));
            }
        });

        // Verificar se a imagem existe e mostrar pré-visualização
        function verificarImagem() {
            const nomeImagem = document.getElementById('nome_imagem').value.trim();
            const statusElement = document.getElementById('imagePreviewStatus');
            
            if (nomeImagem) {
                const img = new Image();
                img.onload = function() {
                    statusElement.innerHTML = '✅ <span style="color: #10b981;">Imagem encontrada!</span>';
                };
                img.onerror = function() {
                    statusElement.innerHTML = '⚠️ <span style="color: #f59e0b;">Imagem não encontrada na pasta /images/</span>';
                };
                img.src = 'images/' + nomeImagem;
            } else {
                statusElement.innerHTML = '';
            }
        }
        
        document.getElementById('nome_imagem').addEventListener('blur', verificarImagem);
        
        // Verificar imagem ao carregar a página
        window.addEventListener('load', verificarImagem);

        // Confirmação de alterações significativas
        document.getElementById('formEvento').addEventListener('submit', function(e) {
            const dataOriginal = '<?php echo $evento['data_evento']; ?>';
            const horaOriginal = '<?php echo $evento['hora_evento']; ?>';
            const cepOriginal = '<?php echo $evento['cep']; ?>';
            
            const dataAtual = document.getElementById('data_evento').value;
            const horaAtual = document.getElementById('hora_evento').value;
            const cepAtual = document.getElementById('cep').value.replace(/\D/g, '');
            
            const participantes = <?php echo $participantesAprovados; ?>;
            
            if (participantes > 0 && (dataOriginal != dataAtual || horaOriginal != horaAtual || cepOriginal != cepAtual)) {
                if (!confirm('Você alterou data, horário ou local do evento. Todos os participantes aprovados serão notificados por e-mail. Deseja continuar?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
</body>
</html>