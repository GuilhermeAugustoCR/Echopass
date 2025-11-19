-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 19/11/2025 às 14:56
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `echopass_db`
--

DELIMITER $$
--
-- Procedimentos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_aprovar_solicitacao` (IN `p_id_solicitacao` INT)   BEGIN
    DECLARE v_qr_code VARCHAR(255);
    DECLARE v_evento_nome VARCHAR(100);
    DECLARE v_cliente_nome VARCHAR(100);
    
    -- Gerar código único para QR Code
    SET v_qr_code = CONCAT('ECHO-', LPAD(p_id_solicitacao, 8, '0'), '-', UNIX_TIMESTAMP());
    
    -- Atualizar solicitação
    UPDATE solicitacao 
    SET status = 'aprovada',
        qr_code = v_qr_code
    WHERE ID_Solicitacao = p_id_solicitacao 
      AND status = 'pendente';
    
    IF ROW_COUNT() > 0 THEN
        -- Buscar informações para o e-mail
        SELECT c.nome_evento, s.nome_cliente
        INTO v_evento_nome, v_cliente_nome
        FROM solicitacao s
        JOIN convite c ON s.ID_Convite = c.ID_Convite
        WHERE s.ID_Solicitacao = p_id_solicitacao;
        
        SELECT 'Solicitação aprovada com sucesso' as mensagem, v_qr_code as qr_code;
    ELSE
        SELECT 'Solicitação não encontrada ou já processada' as mensagem, NULL as qr_code;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cancelar_evento` (IN `p_id_convite` INT)   BEGIN
    -- Atualizar status do evento
    UPDATE convite 
    SET status = 'cancelado'
    WHERE ID_Convite = p_id_convite;
    
    IF ROW_COUNT() > 0 THEN
        SELECT 'Evento cancelado com sucesso' as mensagem;
    ELSE
        SELECT 'Evento não encontrado' as mensagem;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `admin_convite`
--

CREATE TABLE `admin_convite` (
  `ID_admin_convite` int(11) NOT NULL,
  `ID_convite` int(11) NOT NULL,
  `ID_solicitacoes` int(11) DEFAULT NULL,
  `ID_questionario` int(11) DEFAULT NULL,
  `data_associacao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `convite`
--

CREATE TABLE `convite` (
  `ID_Convite` int(11) NOT NULL,
  `ID_Mod` int(11) NOT NULL,
  `nome_evento` varchar(100) NOT NULL,
  `cep` char(8) NOT NULL,
  `endereco_completo` varchar(255) DEFAULT NULL,
  `conv_data_cria` datetime DEFAULT current_timestamp(),
  `descricao` varchar(1000) DEFAULT NULL,
  `data_evento` date NOT NULL,
  `hora_evento` time NOT NULL,
  `num_max` int(11) NOT NULL,
  `idade_minima` int(11) DEFAULT 0,
  `faq` text DEFAULT NULL,
  `nome_imagem` varchar(255) DEFAULT NULL,
  `status` enum('ativo','inativo','cancelado','finalizado') DEFAULT 'ativo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `convite`
--

INSERT INTO `convite` (`ID_Convite`, `ID_Mod`, `nome_evento`, `cep`, `endereco_completo`, `conv_data_cria`, `descricao`, `data_evento`, `hora_evento`, `num_max`, `idade_minima`, `faq`, `nome_imagem`, `status`) VALUES
(1, 1, 'evento 1', '13082015', 'Avenida Cônego Antônio Rocato, Chácaras Campos dos Amarais, Campinas - SP', '2025-11-12 13:33:30', 'descrição do evento', '2025-12-12', '15:00:00', 3, 18, '', NULL, 'cancelado'),
(2, 1, 'EVENTO1', '13010041', 'rua teste', '2025-11-12 19:04:43', 'descrição aqui blablabla', '2025-12-12', '22:00:00', 150, 18, '', 'evento1.jpg', 'ativo'),
(3, 3, 'Evento2', '81570240', 'Rua Paulo Jacomel, Uberaba, Curitiba - PR', '2025-11-19 10:46:02', 'Evento Relacionado <>\"\"\'\'\'\'\r\nEvento Legal', '2025-11-30', '16:25:00', 50, 14, 'Evento Ótimo?\r\nEvento Ok', 'evento_gui.jpeg', 'ativo');

-- --------------------------------------------------------

--
-- Estrutura para tabela `lembrete`
--

CREATE TABLE `lembrete` (
  `ID_Lembrete` int(11) NOT NULL,
  `ID_Solicitacao` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `tipo` enum('aprovacao','lembrete','cancelamento','confirmacao') DEFAULT 'lembrete',
  `data_envio` datetime DEFAULT current_timestamp(),
  `status` enum('pendente','enviado','falha') DEFAULT 'pendente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `log_email`
--

CREATE TABLE `log_email` (
  `ID_Log` int(11) NOT NULL,
  `destinatario` varchar(100) NOT NULL,
  `assunto` varchar(255) NOT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `status` enum('enviado','falha') DEFAULT 'enviado',
  `data_envio` datetime DEFAULT current_timestamp(),
  `erro` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `log_email`
--

INSERT INTO `log_email` (`ID_Log`, `destinatario`, `assunto`, `tipo`, `status`, `data_envio`, `erro`) VALUES
(1, 'gabriela.scapovilla@gmail.com', 'Confirme seu cadastro - EchoPass', 'confirmacao_cadastro', 'enviado', '2025-11-12 13:26:17', NULL),
(2, 'gabriela.simaoc@gmail.com', 'Solicitação Aprovada - evento 1', 'aprovacao', 'enviado', '2025-11-12 17:54:13', NULL),
(3, 'gabriela.simaoc@gmail.com', 'Solicitação Aprovada - evento 1', 'aprovacao', 'enviado', '2025-11-12 18:03:40', NULL),
(4, 'gabriela.simaoc@gmail.com', 'Solicitação Aprovada - evento 1', 'aprovacao', 'enviado', '2025-11-12 18:10:14', NULL),
(5, 'gabriela.simaoc@gmail.com', 'Evento Cancelado - evento 1', 'cancelamento', 'enviado', '2025-11-12 18:11:21', NULL),
(6, 'gabriela.simaoc@gmail.com', 'Evento Cancelado - evento 1', 'cancelamento', 'enviado', '2025-11-12 18:11:25', NULL),
(7, 'gabriela.simaoc@gmail.com', 'Evento Cancelado - evento 1', 'cancelamento', 'enviado', '2025-11-12 18:11:28', NULL),
(8, 'gabriela.simaoc@gmail.com', 'Solicitação Aprovada - EVENTO1', 'aprovacao', 'enviado', '2025-11-12 19:08:14', NULL),
(9, 'gabriela.simaoc@gmail.com', 'Solicitação Aprovada - EVENTO1', 'aprovacao', 'enviado', '2025-11-12 19:12:53', NULL),
(10, 'gabriela.simaoc@gmail.com', 'Alteração no Evento - EVENTO1', 'atualizacao_evento', 'enviado', '2025-11-13 14:38:34', NULL),
(11, 'gabriela.simaoc@gmail.com', 'Solicitação Aprovada - EVENTO1', 'aprovacao', 'enviado', '2025-11-13 17:15:38', NULL),
(12, 'correacorreio111@gmail.com', 'Solicitação Aprovada - EVENTO1', 'aprovacao', 'enviado', '2025-11-19 10:03:30', NULL),
(13, 'guilherme.ribeiro40@fatec.sp.gov.br', 'Confirme seu cadastro - EchoPass', 'confirmacao_cadastro', 'enviado', '2025-11-19 10:34:25', NULL),
(14, 'guilherme.ribeiro40@fatec.sp.gov.br', 'Novo código de confirmação - EchoPass', 'reenvio_codigo', 'enviado', '2025-11-19 10:36:01', NULL),
(15, 'correacorreio111@gmail.com', 'Confirme seu cadastro - EchoPass', 'confirmacao_cadastro', 'enviado', '2025-11-19 10:37:59', NULL),
(16, 'gabriela.scapovilla@gmail.com', 'Solicitação Aprovada - Evento2', 'aprovacao', 'enviado', '2025-11-19 10:47:30', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `mod_evento`
--

CREATE TABLE `mod_evento` (
  `ID_Mod` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `cnpj` varchar(14) NOT NULL,
  `telefone` varchar(15) DEFAULT NULL,
  `codigo_confirmacao` varchar(6) DEFAULT NULL,
  `email_confirmado` tinyint(1) DEFAULT 0,
  `data_cadastro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `mod_evento`
--

INSERT INTO `mod_evento` (`ID_Mod`, `nome`, `email`, `senha`, `cnpj`, `telefone`, `codigo_confirmacao`, `email_confirmado`, `data_cadastro`) VALUES
(1, 'Gabriela Simão', 'gabriela.scapovilla@gmail.com', '$2y$10$FdRePHId0kRPA4Lpu4M3KOV4bCy6u0zNTtM.ARztAPNtDCUupe4SO', '11222333000181', '', NULL, 1, '2025-11-12 13:26:13'),
(2, 'Guilherme', 'guilherme.ribeiro40@fatec.sp.gov.br', '$2y$10$mlJP9T/fSxnPCbpP9KgfWu.oMcEiiHIvDj2Sp5Z/MKm07lk3EDIxm', '11376330000100', '', NULL, 0, '2025-11-19 10:34:20'),
(3, 'Guilherme Augusto', 'correacorreio111@gmail.com', '$2y$10$QX7SAqVM787JuWYXgW5uYOLORrqwHqoiQrud08jnmUo0vo1DiTRRi', '15773042000132', '19979666576', NULL, 1, '2025-11-19 10:37:42');

-- --------------------------------------------------------

--
-- Estrutura para tabela `questionario`
--

CREATE TABLE `questionario` (
  `ID_Questionario` int(11) NOT NULL,
  `ID_Convite` int(11) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `data_criacao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `solicitacao`
--

CREATE TABLE `solicitacao` (
  `ID_Solicitacao` int(11) NOT NULL,
  `ID_Convite` int(11) NOT NULL,
  `ID_Token` int(11) DEFAULT NULL,
  `nome_cliente` varchar(100) NOT NULL,
  `cpf_cliente` char(11) NOT NULL,
  `email_cliente` varchar(100) NOT NULL,
  `data_nasc` date NOT NULL,
  `num_polt` int(11) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `data_envio_solicitacao` datetime DEFAULT current_timestamp(),
  `status` enum('pendente','aprovada','rejeitada','cancelada') DEFAULT 'pendente',
  `observacoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `solicitacao`
--

INSERT INTO `solicitacao` (`ID_Solicitacao`, `ID_Convite`, `ID_Token`, `nome_cliente`, `cpf_cliente`, `email_cliente`, `data_nasc`, `num_polt`, `qr_code`, `data_envio_solicitacao`, `status`, `observacoes`) VALUES
(1, 1, NULL, 'nome e sobrenome', '43403461874', 'gabriela.simaoc@gmail.com', '1995-07-31', 1, 'ECHO-00000001-1762980849', '2025-11-12 13:34:30', 'aprovada', ''),
(2, 1, NULL, 'nome teste', '58576922029', 'gabriela.simaoc@gmail.com', '1990-10-10', 3, 'ECHO-00000002-1762981416', '2025-11-12 18:03:09', 'aprovada', ''),
(3, 1, NULL, 'teste dois', '19878458032', 'gabriela.simaoc@gmail.com', '2000-10-10', 2, 'ECHO-00000003-1762981810', '2025-11-12 18:09:54', 'aprovada', ''),
(4, 2, NULL, 'nome teste', '45639369019', 'gabriela.simaoc@gmail.com', '2000-04-14', 70, 'ECHO-00000004-1762985290', '2025-11-12 19:07:34', 'aprovada', ''),
(7, 2, NULL, 'Guilherme', '52413019847', 'correacorreio111@gmail.com', '2003-06-21', 7, 'ECHO-00000007-1763557397', '2025-11-19 10:02:59', 'aprovada', 'Tenho 22 Anos'),
(8, 3, NULL, 'gabriela', '43403461874', 'gabriela.scapovilla@gmail.com', '1995-07-31', 8, 'ECHO-00000008-1763560042', '2025-11-19 10:47:10', 'aprovada', '');

-- --------------------------------------------------------

--
-- Estrutura para tabela `token`
--

CREATE TABLE `token` (
  `ID_Token` int(11) NOT NULL,
  `ID_Convite` int(11) NOT NULL,
  `token_link` varchar(100) NOT NULL,
  `token_desc` varchar(100) DEFAULT NULL,
  `status` enum('ativo','inativo','utilizado','expirado') DEFAULT 'ativo',
  `data_criacao` datetime DEFAULT current_timestamp(),
  `data_expiracao` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_convites_ativos`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_convites_ativos` (
`ID_Convite` int(11)
,`nome_evento` varchar(100)
,`moderador` varchar(100)
,`data_evento` date
,`hora_evento` time
,`num_max` int(11)
,`idade_minima` int(11)
,`status` enum('ativo','inativo','cancelado','finalizado')
,`vagas_ocupadas` bigint(21)
,`vagas_disponiveis` bigint(22)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_solicitacoes_pendentes`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_solicitacoes_pendentes` (
`ID_Solicitacao` int(11)
,`nome_cliente` varchar(100)
,`nome_evento` varchar(100)
,`cpf_cliente` char(11)
,`email_cliente` varchar(100)
,`data_nasc` date
,`num_polt` int(11)
,`data_envio_solicitacao` datetime
,`status` enum('pendente','aprovada','rejeitada','cancelada')
,`ID_Mod` int(11)
);

-- --------------------------------------------------------

--
-- Estrutura para view `vw_convites_ativos`
--
DROP TABLE IF EXISTS `vw_convites_ativos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_convites_ativos`  AS SELECT `c`.`ID_Convite` AS `ID_Convite`, `c`.`nome_evento` AS `nome_evento`, `m`.`nome` AS `moderador`, `c`.`data_evento` AS `data_evento`, `c`.`hora_evento` AS `hora_evento`, `c`.`num_max` AS `num_max`, `c`.`idade_minima` AS `idade_minima`, `c`.`status` AS `status`, count(case when `s`.`status` = 'aprovada' then 1 end) AS `vagas_ocupadas`, `c`.`num_max`- count(case when `s`.`status` = 'aprovada' then 1 end) AS `vagas_disponiveis` FROM ((`convite` `c` join `mod_evento` `m` on(`c`.`ID_Mod` = `m`.`ID_Mod`)) left join `solicitacao` `s` on(`c`.`ID_Convite` = `s`.`ID_Convite`)) WHERE `c`.`status` = 'ativo' AND `c`.`data_evento` >= curdate() GROUP BY `c`.`ID_Convite` ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_solicitacoes_pendentes`
--
DROP TABLE IF EXISTS `vw_solicitacoes_pendentes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_solicitacoes_pendentes`  AS SELECT `s`.`ID_Solicitacao` AS `ID_Solicitacao`, `s`.`nome_cliente` AS `nome_cliente`, `c`.`nome_evento` AS `nome_evento`, `s`.`cpf_cliente` AS `cpf_cliente`, `s`.`email_cliente` AS `email_cliente`, `s`.`data_nasc` AS `data_nasc`, `s`.`num_polt` AS `num_polt`, `s`.`data_envio_solicitacao` AS `data_envio_solicitacao`, `s`.`status` AS `status`, `c`.`ID_Mod` AS `ID_Mod` FROM (`solicitacao` `s` join `convite` `c` on(`s`.`ID_Convite` = `c`.`ID_Convite`)) WHERE `s`.`status` = 'pendente' ;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `admin_convite`
--
ALTER TABLE `admin_convite`
  ADD PRIMARY KEY (`ID_admin_convite`),
  ADD KEY `ID_convite` (`ID_convite`),
  ADD KEY `ID_questionario` (`ID_questionario`);

--
-- Índices de tabela `convite`
--
ALTER TABLE `convite`
  ADD PRIMARY KEY (`ID_Convite`),
  ADD KEY `idx_convite_mod` (`ID_Mod`),
  ADD KEY `idx_convite_status` (`status`);

--
-- Índices de tabela `lembrete`
--
ALTER TABLE `lembrete`
  ADD PRIMARY KEY (`ID_Lembrete`),
  ADD KEY `idx_lembrete_solicitacao` (`ID_Solicitacao`);

--
-- Índices de tabela `log_email`
--
ALTER TABLE `log_email`
  ADD PRIMARY KEY (`ID_Log`);

--
-- Índices de tabela `mod_evento`
--
ALTER TABLE `mod_evento`
  ADD PRIMARY KEY (`ID_Mod`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `cnpj` (`cnpj`);

--
-- Índices de tabela `questionario`
--
ALTER TABLE `questionario`
  ADD PRIMARY KEY (`ID_Questionario`),
  ADD KEY `idx_questionario_convite` (`ID_Convite`);

--
-- Índices de tabela `solicitacao`
--
ALTER TABLE `solicitacao`
  ADD PRIMARY KEY (`ID_Solicitacao`),
  ADD UNIQUE KEY `unique_convite_assento` (`ID_Convite`,`num_polt`),
  ADD KEY `idx_solicitacao_convite` (`ID_Convite`),
  ADD KEY `idx_solicitacao_token` (`ID_Token`),
  ADD KEY `idx_solicitacao_cpf` (`cpf_cliente`),
  ADD KEY `idx_solicitacao_status` (`status`),
  ADD KEY `idx_solicitacao_nome` (`nome_cliente`);

--
-- Índices de tabela `token`
--
ALTER TABLE `token`
  ADD PRIMARY KEY (`ID_Token`),
  ADD UNIQUE KEY `token_link` (`token_link`),
  ADD KEY `idx_token_convite` (`ID_Convite`),
  ADD KEY `idx_token_status` (`status`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `admin_convite`
--
ALTER TABLE `admin_convite`
  MODIFY `ID_admin_convite` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `convite`
--
ALTER TABLE `convite`
  MODIFY `ID_Convite` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `lembrete`
--
ALTER TABLE `lembrete`
  MODIFY `ID_Lembrete` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `log_email`
--
ALTER TABLE `log_email`
  MODIFY `ID_Log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `mod_evento`
--
ALTER TABLE `mod_evento`
  MODIFY `ID_Mod` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `questionario`
--
ALTER TABLE `questionario`
  MODIFY `ID_Questionario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `solicitacao`
--
ALTER TABLE `solicitacao`
  MODIFY `ID_Solicitacao` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `token`
--
ALTER TABLE `token`
  MODIFY `ID_Token` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `admin_convite`
--
ALTER TABLE `admin_convite`
  ADD CONSTRAINT `admin_convite_ibfk_1` FOREIGN KEY (`ID_convite`) REFERENCES `convite` (`ID_Convite`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_convite_ibfk_2` FOREIGN KEY (`ID_questionario`) REFERENCES `questionario` (`ID_Questionario`) ON DELETE SET NULL;

--
-- Restrições para tabelas `convite`
--
ALTER TABLE `convite`
  ADD CONSTRAINT `convite_ibfk_1` FOREIGN KEY (`ID_Mod`) REFERENCES `mod_evento` (`ID_Mod`) ON DELETE CASCADE;

--
-- Restrições para tabelas `lembrete`
--
ALTER TABLE `lembrete`
  ADD CONSTRAINT `lembrete_ibfk_1` FOREIGN KEY (`ID_Solicitacao`) REFERENCES `solicitacao` (`ID_Solicitacao`) ON DELETE CASCADE;

--
-- Restrições para tabelas `questionario`
--
ALTER TABLE `questionario`
  ADD CONSTRAINT `questionario_ibfk_1` FOREIGN KEY (`ID_Convite`) REFERENCES `convite` (`ID_Convite`) ON DELETE CASCADE;

--
-- Restrições para tabelas `solicitacao`
--
ALTER TABLE `solicitacao`
  ADD CONSTRAINT `solicitacao_ibfk_1` FOREIGN KEY (`ID_Convite`) REFERENCES `convite` (`ID_Convite`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitacao_ibfk_2` FOREIGN KEY (`ID_Token`) REFERENCES `token` (`ID_Token`) ON DELETE SET NULL;

--
-- Restrições para tabelas `token`
--
ALTER TABLE `token`
  ADD CONSTRAINT `token_ibfk_1` FOREIGN KEY (`ID_Convite`) REFERENCES `convite` (`ID_Convite`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
