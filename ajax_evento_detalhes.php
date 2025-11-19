<?php
// ajax_evento_detalhes.php
include 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$idEvento = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            m.nome AS moderador_nome,
            (c.num_max - COUNT(CASE WHEN s.status = 'aprovada' THEN 1 END)) AS vagas_disponiveis,
            COUNT(CASE WHEN s.status = 'aprovada' THEN 1 END) AS vagas_ocupadas
        FROM convite c
        JOIN mod_evento m ON c.ID_Mod = m.ID_Mod
        LEFT JOIN solicitacao s ON c.ID_Convite = s.ID_Convite
        WHERE c.ID_Convite = ? AND c.status = 'ativo'
        GROUP BY c.ID_Convite
    ");
    $stmt->execute([$idEvento]);
    $evento = $stmt->fetch();
    
    if ($evento) {
        $evento['data_evento_formatada'] = formatarDataBR($evento['data_evento']);
        $evento['hora_evento_formatada'] = formatarHoraBR($evento['hora_evento']);
        
        echo json_encode([
            'success' => true,
            'evento' => $evento
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Evento não encontrado'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar evento'
    ]);
}
?>