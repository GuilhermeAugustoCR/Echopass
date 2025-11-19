<?php
include 'config.php';

// Buscar eventos ativos
$stmt = $pdo->query("
    SELECT 
        c.*,
        m.nome AS moderador_nome,
        (c.num_max - COUNT(CASE WHEN s.status = 'aprovada' THEN 1 END)) AS vagas_disponiveis,
        COUNT(CASE WHEN s.status = 'aprovada' THEN 1 END) AS vagas_ocupadas
    FROM convite c
    JOIN mod_evento m ON c.ID_Mod = m.ID_Mod
    LEFT JOIN solicitacao s ON c.ID_Convite = s.ID_Convite
    WHERE c.status = 'ativo' 
      AND c.data_evento >= CURDATE()
    GROUP BY c.ID_Convite
    ORDER BY c.data_evento ASC, c.hora_evento ASC
");
$eventos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EchoPass - Eventos Disponíveis</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">EchoPass</a>
                <ul class="nav-links">
                    <li><a href="index.php">Eventos</a></li>
                    <li><a href="mod_evento.php">Área do Moderador</a></li>
                </ul>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <div class="card-header text-center">
                <h2>Eventos Disponíveis</h2>
                <p style="color: #777; margin-top: 10px;">Encontre e participe dos melhores eventos</p>
            </div>

            <?php if (count($eventos) > 0): ?>
                <div class="events-grid">
                    <?php foreach ($eventos as $evento): ?>
                        <div class="event-card" onclick="abrirModalEvento(<?php echo $evento['ID_Convite']; ?>)">
                            <div class="event-image" <?php if (!empty($evento['nome_imagem'])): ?>style="background-image: url('images/<?php echo htmlspecialchars($evento['nome_imagem']); ?>'); background-size: cover; background-position: center; font-size: 0;"<?php endif; ?>>
                                <?php if (empty($evento['nome_imagem'])): ?>🎫<?php endif; ?>
                            </div>
                            <div class="event-content">
                                <h3 class="event-title"><?php echo htmlspecialchars($evento['nome_evento']); ?></h3>
                                
                                <div class="event-info">
                                    <span>📅 <?php echo formatarDataBR($evento['data_evento']); ?></span>
                                    <span>🕐 <?php echo formatarHoraBR($evento['hora_evento']); ?></span>
                                    <span>📍 CEP: <?php echo htmlspecialchars($evento['cep']); ?></span>
                                    <?php if ($evento['idade_minima'] > 0): ?>
                                        <span>🔞 Idade mínima: <?php echo $evento['idade_minima']; ?> anos</span>
                                    <?php endif; ?>
                                </div>

                                <p class="event-description">
                                    <?php echo htmlspecialchars($evento['descricao'] ?? 'Sem descrição disponível'); ?>
                                </p>

                                <div class="event-footer">
                                    <div class="vagas-info">
                                        <?php 
                                        $disponiveis = $evento['vagas_disponiveis'];
                                        if ($disponiveis > 0) {
                                            echo "$disponiveis vagas disponíveis";
                                        } else {
                                            echo "Esgotado";
                                        }
                                        ?>
                                    </div>
                                    <button class="btn btn-primary btn-sm">Ver Detalhes</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center">
                    <strong>Nenhum evento disponível no momento</strong><br>
                    Volte mais tarde para conferir novos eventos!
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Detalhes do Evento -->
    <div id="modalEvento" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modalTitulo">Detalhes do Evento</h2>
                <button class="modal-close" onclick="fecharModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalConteudo">
                <div style="text-align: center; padding: 40px;">
                    <div class="loading" style="border-color: var(--primary-color); border-top-color: transparent; width: 40px; height: 40px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2025 EchoPass - Sistema de Gerenciamento de Eventos</p>
    </div>

    <script>
        function abrirModalEvento(idEvento) {
            const modal = document.getElementById('modalEvento');
            modal.classList.add('active');
            
            // Carregar detalhes via AJAX
            fetch('ajax_evento_detalhes.php?id=' + idEvento)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalTitulo').textContent = data.evento.nome_evento;
                        document.getElementById('modalConteudo').innerHTML = gerarHtmlDetalhes(data.evento);
                    } else {
                        document.getElementById('modalConteudo').innerHTML = 
                            '<div class="alert alert-error">Erro ao carregar detalhes do evento</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('modalConteudo').innerHTML = 
                        '<div class="alert alert-error">Erro ao carregar detalhes do evento</div>';
                });
        }

        function gerarHtmlDetalhes(evento) {
            let html = `
                <div class="event-info" style="gap: 15px; margin-bottom: 20px;">
                    <span style="font-size: 16px;"><strong>📅 Data:</strong> ${evento.data_evento_formatada}</span>
                    <span style="font-size: 16px;"><strong>🕐 Horário:</strong> ${evento.hora_evento_formatada}</span>
                    <span style="font-size: 16px;"><strong>📍 CEP:</strong> ${evento.cep}</span>
                    ${evento.endereco_completo ? `<span style="font-size: 16px;"><strong>📍 Endereço:</strong> ${evento.endereco_completo}</span>` : ''}
                    <span style="font-size: 16px;"><strong>👥 Capacidade:</strong> ${evento.num_max} pessoas</span>
                    <span style="font-size: 16px;"><strong>✅ Vagas Disponíveis:</strong> ${evento.vagas_disponiveis}</span>
                    ${evento.idade_minima > 0 ? `<span style="font-size: 16px;"><strong>🔞 Idade Mínima:</strong> ${evento.idade_minima} anos</span>` : ''}
                </div>

                <div style="margin-bottom: 20px;">
                    <strong style="display: block; margin-bottom: 10px;">Descrição:</strong>
                    <p style="color: #555; line-height: 1.6;">${evento.descricao || 'Sem descrição disponível'}</p>
                </div>

                ${evento.faq ? `
                    <div style="margin-bottom: 20px;">
                        <strong style="display: block; margin-bottom: 10px;">FAQ:</strong>
                        <p style="color: #555; line-height: 1.6;">${evento.faq}</p>
                    </div>
                ` : ''}
            `;

            if (evento.vagas_disponiveis > 0) {
                html += `
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="fecharModal()">Fechar</button>
                        <button class="btn btn-primary" onclick="window.location.href='solicitar_participacao.php?evento=${evento.ID_Convite}'">
                            Solicitar Participação
                        </button>
                    </div>
                `;
            } else {
                html += `
                    <div class="alert alert-warning">
                        <strong>Evento Esgotado!</strong><br>
                        Infelizmente todas as vagas já foram preenchidas.
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="fecharModal()">Fechar</button>
                    </div>
                `;
            }

            return html;
        }

        function fecharModal() {
            document.getElementById('modalEvento').classList.remove('active');
        }

        // Fechar modal ao clicar fora
        document.getElementById('modalEvento').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModal();
            }
        });

        // Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                fecharModal();
            }
        });
    </script>
</body>
</html>