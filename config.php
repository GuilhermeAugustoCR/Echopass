<?php
session_start();

//Banco de Dados
$host = '127.0.0.1';
$dbname = 'echopass_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Conexão falhou: " . $e->getMessage());
}

//E-mail
define('SMTP_HOST', 'smtp.gmail.com'); 
define('SMTP_PORT', 587);
define('SMTP_USER', 'echopass25@gmail.com'); 
define('SMTP_PASS', 'thqe zrow hemy mhmu'); 
define('SMTP_FROM', 'echopass25@gmail.com');
define('SMTP_FROM_NAME', 'EchoPass Eventos');

//Sistema
define('BASE_URL', 'http://localhost/echopass/');
define('SITE_NAME', 'EchoPass');

// Função para validar e-mail
function validarEmail($email) {
    // Validação básica de formato
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Extrair domínio
    $domain = substr(strrchr($email, "@"), 1);
    
    // Verificar se o domínio tem MX records (domínio válido)
    if (!checkdnsrr($domain, "MX")) {
        return false;
    }
    
    return true;
}

// Função para enviar e-mail usando PHPMailer
function enviarEmail($destinatario, $assunto, $mensagem, $tipo = 'geral') {
    require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configurações do servidor
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Remetente e destinatário
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($destinatario);
        
        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $mensagem;
        $mail->AltBody = strip_tags($mensagem);
        
        $mail->send();
        
        // Log de sucesso
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO log_email (destinatario, assunto, tipo, status) VALUES (?, ?, ?, 'enviado')");
        $stmt->execute([$destinatario, $assunto, $tipo]);
        
        return true;
    } catch (Exception $e) {
        // Log de erro
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO log_email (destinatario, assunto, tipo, status, erro) VALUES (?, ?, ?, 'falha', ?)");
        $stmt->execute([$destinatario, $assunto, $tipo, $mail->ErrorInfo]);
        
        return false;
    }
}

// Função para enviar e-mail com QR Code anexado
function enviarEmailComQRCode($destinatario, $assunto, $mensagem, $arquivoQR, $tipo = 'aprovacao') {
    require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configurações do servidor
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Remetente e destinatário
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($destinatario);
        
        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $mensagem;
        $mail->AltBody = strip_tags($mensagem);
        
        // ANEXAR IMAGEM QR CODE EMBUTIDA
        if (file_exists($arquivoQR)) {
            $mail->addEmbeddedImage($arquivoQR, 'qrcode_img');
        }
        
        $mail->send();
        
        // Log de sucesso
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO log_email (destinatario, assunto, tipo, status) VALUES (?, ?, ?, 'enviado')");
        $stmt->execute([$destinatario, $assunto, $tipo]);
        
        return true;
    } catch (Exception $e) {
        // Log de erro
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO log_email (destinatario, assunto, tipo, status, erro) VALUES (?, ?, ?, 'falha', ?)");
        $stmt->execute([$destinatario, $assunto, $tipo, $mail->ErrorInfo]);
        
        return false;
    }
}

// Função para gerar código de confirmação
function gerarCodigoConfirmacao() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Função para validar CPF
function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Validação dos dígitos verificadores
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    
    return true;
}

// Função para validar CNPJ
function validarCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    if (strlen($cnpj) != 14) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }
    
    // Validação dos dígitos verificadores
    $b = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    
    for ($i = 0, $n = 0; $i < 12; $i++) {
        $n += $cnpj[$i] * $b[$i + 1];
    }
    
    $n = 11 - ($n % 11);
    $n = $n >= 10 ? 0 : $n;
    
    if ($cnpj[12] != $n) {
        return false;
    }
    
    for ($i = 0, $n = 0; $i <= 12; $i++) {
        $n += $cnpj[$i] * $b[$i];
    }
    
    $n = 11 - ($n % 11);
    $n = $n >= 10 ? 0 : $n;
    
    if ($cnpj[13] != $n) {
        return false;
    }
    
    return true;
}

// Função para calcular idade
function calcularIdade($dataNascimento) {
    $nascimento = new DateTime($dataNascimento);
    $hoje = new DateTime();
    $idade = $hoje->diff($nascimento);
    return $idade->y;
}

// Função para formatar data brasileira
function formatarDataBR($data) {
    return date('d/m/Y', strtotime($data));
}

// Função para formatar hora brasileira
function formatarHoraBR($hora) {
    return date('H:i', strtotime($hora));
}

// Função para redirecionar
function redirecionar($url) {
    header("Location: $url");
    exit();
}

// Verificar se moderador está logado
function verificarLogin() {
    if (!isset($_SESSION['mod_id'])) {
        redirecionar('mod_evento.php');
    }
}

// Verificar se moderador confirmou e-mail
function verificarEmailConfirmado() {
    global $pdo;
    
    if (!isset($_SESSION['mod_id'])) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT email_confirmado FROM mod_evento WHERE ID_Mod = ?");
    $stmt->execute([$_SESSION['mod_id']]);
    $mod = $stmt->fetch();
    
    return $mod && $mod['email_confirmado'] == 1;
}
?>