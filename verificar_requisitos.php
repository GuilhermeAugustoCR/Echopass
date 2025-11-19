<?php
/**
 * verificar_requisitos.php
 * Script para verificar se todos os requisitos do sistema estão instalados
 */

$requisitos = [];
$erros = [];
$avisos = [];

// Verificar versão do PHP
$phpVersion = phpversion();
$requisitos['PHP Version'] = $phpVersion;
if (version_compare($phpVersion, '7.4.0', '<')) {
    $erros[] = "PHP 7.4 ou superior é necessário. Versão atual: $phpVersion";
} else {
    $requisitos['PHP Version'] = "✓ $phpVersion";
}

// Verificar extensões PHP
$extensoesNecessarias = ['pdo', 'pdo_mysql', 'gd', 'openssl', 'mbstring'];
foreach ($extensoesNecessarias as $ext) {
    if (extension_loaded($ext)) {
        $requisitos["Extensão $ext"] = "✓ Instalada";
    } else {
        $erros[] = "Extensão PHP '$ext' não está instalada";
        $requisitos["Extensão $ext"] = "✗ Não instalada";
    }
}

// Verificar PHPMailer
if (file_exists('PHPMailer/PHPMailer.php')) {
    $requisitos['PHPMailer'] = "✓ Instalado";
} else {
    $erros[] = "PHPMailer não encontrado. Instale em PHPMailer/";
    $requisitos['PHPMailer'] = "✗ Não encontrado";
}

// Verificar PHP QR Code
if (file_exists('phpqrcode/qrlib.php')) {
    $requisitos['PHP QR Code'] = "✓ Instalado";
} else {
    $erros[] = "PHP QR Code não encontrado. Instale em phpqrcode/";
    $requisitos['PHP QR Code'] = "✗ Não encontrado";
}

// Verificar pasta de QR Codes
if (!file_exists('qrcodes')) {
    mkdir('qrcodes', 0755, true);
    $requisitos['Pasta qrcodes/'] = "✓ Criada automaticamente";
} else {
    if (is_writable('qrcodes')) {
        $requisitos['Pasta qrcodes/'] = "✓ Existe e tem permissão de escrita";
    } else {
        $avisos[] = "Pasta qrcodes/ existe mas não tem permissão de escrita";
        $requisitos['Pasta qrcodes/'] = "⚠ Sem permissão de escrita";
    }
}

// Verificar conexão com banco de dados
try {
    include 'config.php';
    $requisitos['Conexão MySQL'] = "✓ Conectado com sucesso";
} catch (Exception $e) {
    $erros[] = "Erro ao conectar ao banco de dados: " . $e->getMessage();
    $requisitos['Conexão MySQL'] = "✗ Erro de conexão";
}

// Verificar configuração de e-mail
if (defined('SMTP_USER') && SMTP_USER != 'seuemail@gmail.com') {
    $requisitos['Configuração SMTP'] = "✓ Configurado";
} else {
    $avisos[] = "Configure suas credenciais SMTP no arquivo config.php";
    $requisitos['Configuração SMTP'] = "⚠ Não configurado";
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Requisitos - EchoPass</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #4a90e2;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #777;
            margin-bottom: 30px;
        }
        .status {
            margin-bottom: 30px;
        }
        .status-item {
            padding: 12px 15px;
            background: #f8f9fa;
            border-left: 4px solid #ddd;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            border-radius: 4px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #f39c12;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #50c878;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #4a90e2;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #357abd;
        }
        .instructions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .instructions h3 {
            margin-bottom: 10px;
            color: #4a90e2;
        }
        .instructions code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .instructions pre {
            background: #2d3748;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificação de Requisitos</h1>
        <p class="subtitle">Sistema EchoPass - Gerenciamento de Eventos</p>

        <?php if (count($erros) > 0): ?>
            <div class="alert alert-error">
                <strong>❌ Erros encontrados:</strong>
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <?php foreach ($erros as $erro): ?>
                        <li><?php echo $erro; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (count($avisos) > 0): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Avisos:</strong>
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <?php foreach ($avisos as $aviso): ?>
                        <li><?php echo $aviso; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (count($erros) == 0 && count($avisos) == 0): ?>
            <div class="alert alert-success">
                <strong>✅ Todos os requisitos estão satisfeitos!</strong><br>
                O sistema está pronto para uso.
            </div>
        <?php endif; ?>

        <h2 style="margin: 30px 0 15px 0; color: #333;">Status dos Requisitos</h2>
        <div class="status">
            <?php foreach ($requisitos as $nome => $status): ?>
                <div class="status-item">
                    <strong><?php echo $nome; ?></strong>
                    <span><?php echo $status; ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($erros) > 0): ?>
            <div class="instructions">
                <h3>📦 Como Instalar as Dependências</h3>
                
                <h4 style="margin-top: 20px;">PHPMailer</h4>
                <p>Via Composer (recomendado):</p>
                <pre>composer require phpmailer/phpmailer</pre>
                
                <p>Ou faça o download manual:</p>
                <pre>https://github.com/PHPMailer/PHPMailer/releases</pre>
                <p>Extraia para a pasta <code>PHPMailer/</code> na raiz do projeto.</p>

                <h4 style="margin-top: 20px;">PHP QR Code</h4>
                <p>Faça o download:</p>
                <pre>https://sourceforge.net/projects/phpqrcode/
ou
https://github.com/t0k4rt/phpqrcode</pre>
                <p>Extraia para a pasta <code>phpqrcode/</code> na raiz do projeto.</p>

                <h4 style="margin-top: 20px;">Estrutura de Pastas</h4>
                <pre>/seu-projeto/
├── PHPMailer/
│   ├── PHPMailer.php
│   ├── SMTP.php
│   └── Exception.php
├── phpqrcode/
│   └── qrlib.php
└── qrcodes/ (será criada automaticamente)</pre>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <?php if (count($erros) == 0): ?>
                <a href="index.php" class="btn">Ir para o Sistema</a>
            <?php else: ?>
                <a href="verificar_requisitos.php" class="btn">Verificar Novamente</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>