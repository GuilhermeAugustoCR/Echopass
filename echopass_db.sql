-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 10/09/2025 às 15:15
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
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_lembretes_de_agenda` ()   BEGIN
    -- Lembrete 7 dias antes do evento
    INSERT INTO Lembrete (ID_Solicitacao, email, titulo, descricao, propriedade)
    SELECT 
        s.ID_Solicitacao,
        s.email_cliente,
        'Lembrete Evento',
        CONCAT('Faltam 7 dias para o evento "', c.nome_evento, '" que ocorrerá em ', DATE_FORMAT(c.data_evento, '%d/%m/%Y'), ' às ', TIME_FORMAT(c.hora_evento, '%H:%i')),
        'Lembrete 7 dias antes'
    FROM Solicitacao s
    JOIN Convite c ON s.ID_Convite = c.ID_Convite
    WHERE c.data_evento = DATE_ADD(CURDATE(), INTERVAL 7 DAY);
    
    -- Lembrete 3 dias antes do evento
    INSERT INTO Lembrete (ID_Solicitacao, email, titulo, descricao, propriedade)
    SELECT 
        s.ID_Solicitacao,
        s.email_cliente,
        'Lembrete Evento',
        CONCAT('Faltam 3 dias para o evento "', c.nome_evento, '" que ocorrerá em ', DATE_FORMAT(c.data_evento, '%d/%m/%Y'), ' às ', TIME_FORMAT(c.hora_evento, '%H:%i')),
        'Lembrete 3 dias antes'
    FROM Solicitacao s
    JOIN Convite c ON s.ID_Convite = c.ID_Convite
    WHERE c.data_evento = DATE_ADD(CURDATE(), INTERVAL 3 DAY);
    
    -- Lembrete 1 dia antes do evento
    INSERT INTO Lembrete (ID_Solicitacao, email, titulo, descricao, propriedade)
    SELECT 
        s.ID_Solicitacao,
        s.email_cliente,
        'Lembrete Evento',
        CONCAT('Falta 1 dia para o evento "', c.nome_evento, '" que ocorrerá amanhã (', DATE_FORMAT(c.data_evento, '%d/%m/%Y'), ') às ', TIME_FORMAT(c.hora_evento, '%H:%i'), '!'),
        'Lembrete 1 dia antes'
    FROM Solicitacao s
    JOIN Convite c ON s.ID_Convite = c.ID_Convite
    WHERE c.data_evento = DATE_ADD(CURDATE(), INTERVAL 1 DAY);
    
    -- Lembrete no dia do evento
    INSERT INTO Lembrete (ID_Solicitacao, email, titulo, descricao, propriedade)
    SELECT 
        s.ID_Solicitacao,
        s.email_cliente,
        'Evento Hoje!',
        CONCAT('O evento "', c.nome_evento, '" ocorre hoje às ', TIME_FORMAT(c.hora_evento, '%H:%i'), '! Não se atrase!'),
        'Lembrete dia do evento'
    FROM Solicitacao s
    JOIN Convite c ON s.ID_Convite = c.ID_Convite
    WHERE c.data_evento = CURDATE();
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `admin_convite`
--

CREATE TABLE `admin_convite` (
  `ID_convite` int(11) DEFAULT NULL,
  `ID_solicitacoes` int(11) DEFAULT NULL,
  `ID_questionario` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `convite`
--

CREATE TABLE `convite` (
  `ID_Convite` int(11) NOT NULL,
  `ID_Mod` int(11) DEFAULT NULL,
  `nome_evento` varchar(50) NOT NULL,
  `cep` varchar(8) NOT NULL,
  `conv_data_cria` date NOT NULL,
  `descricao` text DEFAULT NULL,
  `data_evento` date NOT NULL,
  `hora_evento` time NOT NULL,
  `num_max` int(11) DEFAULT NULL,
  `faq` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Acionadores `convite`
--
DELIMITER $$
CREATE TRIGGER `trg_autogerar_token` AFTER INSERT ON `convite` FOR EACH ROW BEGIN
    INSERT INTO Token (ID_Convite, token_link, token_desc)
    VALUES (NEW.ID_Convite, 
            CONCAT('https://echopass.com/invite/', UUID_SHORT()),
            CONCAT('Token do evento: ', NEW.nome_evento));
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_validar_data_evento` BEFORE INSERT ON `convite` FOR EACH ROW BEGIN
    IF NEW.data_evento < CURDATE() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A Data do Evento não pode estar no passado.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `lembrete`
--

CREATE TABLE `lembrete` (
  `ID_Lembrete` int(11) NOT NULL,
  `ID_Solicitacao` int(11) DEFAULT NULL,
  `email` char(50) DEFAULT NULL,
  `titulo` char(10) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `propriedade` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `mod_evento`
--

CREATE TABLE `mod_evento` (
  `ID_Mod` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `cnpj` varchar(14) NOT NULL,
  `telefone` varchar(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `questionario`
--

CREATE TABLE `questionario` (
  `ID_Questionario` int(11) NOT NULL,
  `ID_Convite` int(11) DEFAULT NULL,
  `titulo` char(50) DEFAULT NULL,
  `descricao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `solicitacao`
--

CREATE TABLE `solicitacao` (
  `ID_Solicitacao` int(11) NOT NULL,
  `ID_Convite` int(11) DEFAULT NULL,
  `ID_Token` int(11) DEFAULT NULL,
  `cpf_cliente` char(11) NOT NULL,
  `email_cliente` char(50) NOT NULL,
  `data_nasc` date NOT NULL,
  `num_polt` int(11) DEFAULT NULL,
  `data_desejada` date DEFAULT NULL,
  `hora_desejada` time DEFAULT NULL,
  `data_de_envio_soli` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Acionadores `solicitacao`
--
DELIMITER $$
CREATE TRIGGER `trg_previnir_overbooking` BEFORE INSERT ON `solicitacao` FOR EACH ROW BEGIN
    DECLARE current_count INT;
    DECLARE max_capacity INT;
    
    -- Conta apenas solicitações ativas (se houver campo de status)
    SELECT COUNT(*) INTO current_count 
    FROM Solicitacao 
    WHERE ID_Convite = NEW.ID_Convite;
    
    -- Obtém a capacidade máxima do evento
    SELECT num_max INTO max_capacity 
    FROM Convite 
    WHERE ID_Convite = NEW.ID_Convite;
    
    -- Verifica se já atingiu ou ultrapassou a capacidade
    IF current_count >= max_capacity THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Evento já atingiu a capacidade máxima!';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `token`
--

CREATE TABLE `token` (
  `ID_Token` int(11) NOT NULL,
  `ID_Convite` int(11) DEFAULT NULL,
  `token_link` char(50) DEFAULT NULL,
  `token_desc` char(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `admin_convite`
--
ALTER TABLE `admin_convite`
  ADD KEY `ID_convite` (`ID_convite`),
  ADD KEY `ID_solicitacoes` (`ID_solicitacoes`),
  ADD KEY `ID_questionario` (`ID_questionario`);

--
-- Índices de tabela `convite`
--
ALTER TABLE `convite`
  ADD PRIMARY KEY (`ID_Convite`),
  ADD UNIQUE KEY `ID_Convite` (`ID_Convite`),
  ADD KEY `ID_Mod` (`ID_Mod`);

--
-- Índices de tabela `lembrete`
--
ALTER TABLE `lembrete`
  ADD PRIMARY KEY (`ID_Lembrete`),
  ADD UNIQUE KEY `ID_Lembrete` (`ID_Lembrete`),
  ADD KEY `ID_Solicitacao` (`ID_Solicitacao`);

--
-- Índices de tabela `mod_evento`
--
ALTER TABLE `mod_evento`
  ADD PRIMARY KEY (`ID_Mod`),
  ADD UNIQUE KEY `ID_Mod` (`ID_Mod`);

--
-- Índices de tabela `questionario`
--
ALTER TABLE `questionario`
  ADD PRIMARY KEY (`ID_Questionario`),
  ADD UNIQUE KEY `ID_Questionario` (`ID_Questionario`),
  ADD KEY `ID_Convite` (`ID_Convite`);

--
-- Índices de tabela `solicitacao`
--
ALTER TABLE `solicitacao`
  ADD PRIMARY KEY (`ID_Solicitacao`),
  ADD UNIQUE KEY `ID_Solicitacao` (`ID_Solicitacao`),
  ADD KEY `ID_Convite` (`ID_Convite`),
  ADD KEY `ID_Token` (`ID_Token`);

--
-- Índices de tabela `token`
--
ALTER TABLE `token`
  ADD PRIMARY KEY (`ID_Token`),
  ADD UNIQUE KEY `ID_Token` (`ID_Token`),
  ADD KEY `ID_Convite` (`ID_Convite`);

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `admin_convite`
--
ALTER TABLE `admin_convite`
  ADD CONSTRAINT `admin_convite_ibfk_1` FOREIGN KEY (`ID_convite`) REFERENCES `convite` (`ID_Convite`),
  ADD CONSTRAINT `admin_convite_ibfk_2` FOREIGN KEY (`ID_solicitacoes`) REFERENCES `solicitacao` (`ID_Solicitacao`),
  ADD CONSTRAINT `admin_convite_ibfk_3` FOREIGN KEY (`ID_questionario`) REFERENCES `questionario` (`ID_Questionario`);

--
-- Restrições para tabelas `convite`
--
ALTER TABLE `convite`
  ADD CONSTRAINT `convite_ibfk_1` FOREIGN KEY (`ID_Mod`) REFERENCES `mod_evento` (`ID_Mod`);

--
-- Restrições para tabelas `lembrete`
--
ALTER TABLE `lembrete`
  ADD CONSTRAINT `lembrete_ibfk_1` FOREIGN KEY (`ID_Solicitacao`) REFERENCES `solicitacao` (`ID_Solicitacao`);

--
-- Restrições para tabelas `questionario`
--
ALTER TABLE `questionario`
  ADD CONSTRAINT `questionario_ibfk_1` FOREIGN KEY (`ID_Convite`) REFERENCES `convite` (`ID_Convite`);

--
-- Restrições para tabelas `solicitacao`
--
ALTER TABLE `solicitacao`
  ADD CONSTRAINT `solicitacao_ibfk_1` FOREIGN KEY (`ID_Convite`) REFERENCES `convite` (`ID_Convite`),
  ADD CONSTRAINT `solicitacao_ibfk_2` FOREIGN KEY (`ID_Token`) REFERENCES `token` (`ID_Token`);

--
-- Restrições para tabelas `token`
--
ALTER TABLE `token`
  ADD CONSTRAINT `token_ibfk_1` FOREIGN KEY (`ID_Convite`) REFERENCES `convite` (`ID_Convite`);

DELIMITER $$
--
-- Eventos
--
CREATE DEFINER=`root`@`localhost` EVENT `ev_envio_lembretes_diario` ON SCHEDULE EVERY 1 DAY STARTS '2025-09-10 08:00:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL sp_lembretes_de_agenda()$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
