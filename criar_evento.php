<?php
include 'config.php';

verificarLogin();

if (!verificarEmailConfirmado()) {
    redirecionar('confirmar_email.php');
}

$modId = $_SESSION['mod_id'];
$erro = '';
$sucesso = '';

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
                // Inserir evento
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO convite 
                        (ID_Mod, nome_evento, cep, endereco_completo, descricao, data_evento, hora_evento, num_max, idade_minima, faq, nome_imagem, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo')
                    ");
                    $stmt->execute([
                        $modId,
                        $nomeEvento,
                        $cep,
                        $enderecoCompleto,
                        $descricao,
                        $dataEvento,
                        $horaEvento,
                        $numMax,
                        $idadeMinima,
                        $faq,
                        $nomeImagem
                    ]);
                    
                    $idEvento = $pdo->lastInsertId();
                    
                    $sucesso = "Evento criado com sucesso!";
                    
                    // Redirecionar após 2 segundos
                    header("refresh:2;url=gerenciar_evento.php?id=$idEvento");
                    
                } catch (PDOException $e) {
                    $erro = "Erro ao criar evento: " . $e->getMessage();
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
    <title>Criar Evento - EchoPass</title>
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
                <h2>Criar Novo Evento</h2>
                <p style="color: #777; margin-top: 10px;">Preencha as informações do evento</p>
            </div>

            <?php if ($erro): ?>
                <div class="alert alert-error"><?php echo $erro; ?></div>
            <?php endif; ?>
            
            <?php if ($sucesso): ?>
                <div class="alert alert-success"><?php echo $sucesso; ?></div>
            <?php endif; ?>

            <form method="POST" id="formEvento">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label for="nome_evento" class="required">Nome do Evento</label>
                        <input 
                            type="text" 
                            id="nome_evento" 
                            name="nome_evento" 
                            placeholder="Ex: Apresentação Fatec"
                            value="<?php echo htmlspecialchars($_POST['nome_evento'] ?? ''); ?>"
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
                            value="<?php echo htmlspecialchars($_POST['cep'] ?? ''); ?>"
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
                        value="<?php echo htmlspecialchars($_POST['endereco_completo'] ?? ''); ?>"
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
                    ><?php echo htmlspecialchars($_POST['descricao'] ?? ''); ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label for="data_evento" class="required">Data do Evento</label>
                        <input 
                            type="date" 
                            id="data_evento" 
                            name="data_evento" 
                            min="<?php echo date('Y-m-d'); ?>"
                            value="<?php echo htmlspecialchars($_POST['data_evento'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="hora_evento" class="required">Hora do Evento</label>
                        <input 
                            type="time" 
                            id="hora_evento" 
                            name="hora_evento" 
                            value="<?php echo htmlspecialchars($_POST['hora_evento'] ?? ''); ?>"
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
                            min="1"
                            max="10000"
                            value="<?php echo htmlspecialchars($_POST['num_max'] ?? ''); ?>"
                            required
                        >
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
                            value="<?php echo htmlspecialchars($_POST['idade_minima'] ?? 0); ?>"
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
                        value="<?php echo htmlspecialchars($_POST['nome_imagem'] ?? ''); ?>"
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
                    ><?php echo htmlspecialchars($_POST['faq'] ?? ''); ?></textarea>
                </div>

                <div class="btn-group">
                    <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Criar Evento</button>
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
                            document.getElementById('endereco_completo').value = endereco;
                        }
                    })
                    .catch(error => console.log('Erro ao buscar CEP'));
            }
        });

        // Verificar se a imagem existe e mostrar pré-visualização
        document.getElementById('nome_imagem').addEventListener('blur', function() {
            const nomeImagem = this.value.trim();
            const statusElement = document.getElementById('imagePreviewStatus');
            
            if (nomeImagem) {
                // Verificar se a imagem existe
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
        });
    </script>
</body>
</html>