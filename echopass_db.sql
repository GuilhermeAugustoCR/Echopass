-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 11, 2025 at 08:00 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `echopass_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_aprovar_solicitacao` (IN `p_id_solicitacao` INT)   BEGIN
    UPDATE Solicitacao 
    SET status = 'aprovada' 
    WHERE ID_Solicitacao = p_id_solicitacao AND status = 'pendente';
    
    IF ROW_COUNT() > 0 THEN
        SELECT 'Solicitação aprovada com sucesso' as mensagem;
    ELSE
        SELECT 'Solicitação não encontrada ou já processada' as mensagem;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_criar_convite` (IN `p_id_mod` INT, IN `p_nome_evento` VARCHAR(100), IN `p_cep` CHAR(8), IN `p_descricao` VARCHAR(1000), IN `p_data_evento` DATE, IN `p_hora_evento` TIME, IN `p_num_max` INT, IN `p_faq` TEXT)   BEGIN
    INSERT INTO Convite (ID_Mod, nome_evento, cep, descricao, data_evento, hora_evento, num_max, faq)
    VALUES (p_id_mod, p_nome_evento, p_cep, p_descricao, p_data_evento, p_hora_evento, p_num_max, p_faq);
    
    SELECT LAST_INSERT_ID() as novo_convite_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_convite`
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
-- Table structure for table `convite`
--

CREATE TABLE `convite` (
  `ID_Convite` int(11) NOT NULL,
  `ID_Mod` int(11) NOT NULL,
  `nome_evento` varchar(100) NOT NULL,
  `cep` char(8) NOT NULL,
  `conv_data_cria` datetime DEFAULT current_timestamp(),
  `descricao` varchar(1000) DEFAULT NULL,
  `data_evento` date NOT NULL,
  `hora_evento` time NOT NULL,
  `num_max` int(11) NOT NULL,
  `faq` text DEFAULT NULL,
  `status` enum('ativo','inativo','cancelado','finalizado') DEFAULT 'ativo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lembrete`
--

CREATE TABLE `lembrete` (
  `ID_Lembrete` int(11) NOT NULL,
  `ID_Solicitacao` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `propriedade` text DEFAULT NULL,
  `data_envio` datetime DEFAULT current_timestamp(),
  `status` enum('pendente','enviado','falha') DEFAULT 'pendente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mod_evento`
--

CREATE TABLE `mod_evento` (
  `ID_Mod` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `cnpj` varchar(14) NOT NULL,
  `telefone` varchar(15) DEFAULT NULL,
  `data_cadastro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questionario`
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
-- Table structure for table `solicitacao`
--

CREATE TABLE `solicitacao` (
  `ID_Solicitacao` int(11) NOT NULL,
  `ID_Convite` int(11) NOT NULL,
  `ID_Token` int(11) DEFAULT NULL,
  `nome_cliente` varchar(100) NOT NULL,
  `cpf_cliente` char(11) NOT NULL,
  `email_cliente` varchar(100) NOT NULL,
  `data_nasc` date DEFAULT NULL,
  `num_polt` int(11) DEFAULT NULL,
  `data_desejada` date DEFAULT NULL,
  `hora_desejada` time DEFAULT NULL,
  `data_envio_solicitacao` datetime DEFAULT current_timestamp(),
  `status` enum('pendente','aprovada','rejeitada','cancelada') DEFAULT 'pendente',
  `observacoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `token`
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
-- Stand-in structure for view `vw_convites_ativos`
-- (See below for the actual view)
--
CREATE TABLE `vw_convites_ativos` (
`ID_Convite` int(11)
,`nome_evento` varchar(100)
,`moderador` varchar(100)
,`data_evento` date
,`hora_evento` time
,`status` enum('ativo','inativo','cancelado','finalizado')
,`total_solicitacoes` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_solicitacoes_pendentes`
-- (See below for the actual view)
--
CREATE TABLE `vw_solicitacoes_pendentes` (
`ID_Solicitacao` int(11)
,`nome_cliente` varchar(100)
,`nome_evento` varchar(100)
,`cpf_cliente` char(11)
,`email_cliente` varchar(100)
,`data_envio_solicitacao` datetime
,`status` enum('pendente','aprovada','rejeitada','cancelada')
);

-- --------------------------------------------------------

--
-- Structure for view `vw_convites_ativos`
--
DROP TABLE IF EXISTS `vw_convites_ativos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_convites_ativos`  AS SELECT `c`.`ID_Convite` AS `ID_Convite`, `c`.`nome_evento` AS `nome_evento`, `m`.`nome` AS `moderador`, `c`.`data_evento` AS `data_evento`, `c`.`hora_evento` AS `hora_evento`, `c`.`status` AS `status`, count(`s`.`ID_Solicitacao`) AS `total_solicitacoes` FROM ((`convite` `c` join `mod_evento` `m` on(`c`.`ID_Mod` = `m`.`ID_Mod`)) left join `solicitacao` `s` on(`c`.`ID_Convite` = `s`.`ID_Convite`)) WHERE `c`.`status` = 'ativo' GROUP BY `c`.`ID_Convite` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_solicitacoes_pendentes`
--
DROP TABLE IF EXISTS `vw_solicitacoes_pendentes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_solicitacoes_pendentes`  AS SELECT `s`.`ID_Solicitacao` AS `ID_Solicitacao`, `s`.`nome_cliente` AS `nome_cliente`, `c`.`nome_evento` AS `nome_evento`, `s`.`cpf_cliente` AS `cpf_cliente`, `s`.`email_cliente` AS `email_cliente`, `s`.`data_envio_solicitacao` AS `data_envio_solicitacao`, `s`.`status` AS `status` FROM (`solicitacao` `s` join `convite` `c` on(`s`.`ID_Convite` = `c`.`ID_Convite`)) WHERE `s`.`status` = 'pendente' ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_convite`
--
ALTER TABLE `admin_convite`
  ADD PRIMARY KEY (`ID_admin_convite`),
  ADD KEY `ID_convite` (`ID_convite`),
  ADD KEY `ID_questionario` (`ID_questionario`);

--
-- Indexes for table `convite`
--
ALTER TABLE `convite`
  ADD PRIMARY KEY (`ID_Convite`),
  ADD KEY `idx_convite_mod` (`ID_Mod`),
  ADD KEY `idx_convite_status` (`status`);

--
-- Indexes for table `lembrete`
--
ALTER TABLE `lembrete`
  ADD PRIMARY KEY (`ID_Lembrete`),
  ADD KEY `idx_lembrete_solicitacao` (`ID_Solicitacao`);

--
-- Indexes for table `mod_evento`
--
ALTER TABLE `mod_evento`
  ADD PRIMARY KEY (`ID_Mod`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `cnpj` (`cnpj`);

--
-- Indexes for table `questionario`
--
ALTER TABLE `questionario`
  ADD PRIMARY KEY (`ID_Questionario`),
  ADD KEY `idx_questionario_convite` (`ID_Convite`);

--
-- Indexes for table `solicitacao`
--
ALTER TABLE `solicitacao`
  ADD PRIMARY KEY (`ID_Solicitacao`),
  ADD KEY `idx_solicitacao_convite` (`ID_Convite`),
  ADD KEY `idx_solicitacao_token` (`ID_Token`),
  ADD KEY `idx_solicitacao_cpf` (`cpf_cliente`),
  ADD KEY `idx_solicitacao_status` (`status`),
  ADD KEY `idx_solicitacao_nome` (`nome_cliente`);

--
-- Indexes for table `token`
--
ALTER TABLE `token`
  ADD PRIMARY KEY (`ID_Token`),
  ADD UNIQUE KEY `token_link` (`token_link`),
  ADD KEY `idx_token_convite` (`ID_Convite`),
  ADD KEY `idx_token_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_convite`
--
ALTER TABLE `admin_convite`
  MODIFY `ID_admin_convite` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `convite`
--
ALTER TABLE `convite`
  MODIFY `ID_Convite` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `lembrete`
--
ALTER TABLE `lembrete`
  MODIFY `ID_Lembrete` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mod_evento`
--
ALTER TABLE `mod_evento`
  MODIFY `ID_Mod` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `questionario`
--
ALTER TABLE `questionario`
  MODIFY `ID_Questionario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `solicitacao`
--
ALTER TABLE `solicitacao`
  MODIFY `ID_Solicitacao` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `token`
--
ALTER TABLE `token`
  MODIFY `ID_Token` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_convite`
--
ALTER TABLE `admin_convite`
  ADD CONSTRAINT `admin_convite_ibfk_1` FOREIGN KEY (`ID_convite`) REFERENCES `convite` (`ID_Convite`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_convite_ibfk_2` FOREIGN KEY (`ID_questionario`) REFERENCES `questionario` (`ID_Questionario`) ON DELETE SET NULL;

--
-- Constraints for table `convite`
--
ALTER TABLE `convite`
  ADD CONSTRAINT `convite_ibfk_1` FOREIGN KEY (`ID_Mod`) REFERENCES `mod_evento` (`ID_Mod`) ON DELETE CASCADE;

--
-- Constraints for table `lembrete`
--
ALTER TABLE `lembrete`
  ADD CONSTRAINT `lembrete_ibfk_1` FOREIGN KEY (`ID_Solicitacao`) REFERENCES `solicitacao` (`ID_Solicitacao`) ON DELETE CASCADE;

--
-- Constraints for table `questionario`
--
ALTER TABLE `questionario`
  ADD CONSTRAINT `questionario_ibfk_1` FOREIGN KEY (`ID_Convite`) REFERENCES `convite` (`ID_Convite`) ON DELETE CASCADE;

--
-- Constraints for table `solicitacao`
--
ALTER TABLE `solicitacao`
  ADD CONSTRAINT `solicitacao_ibfk_1` FOREIGN KEY (`ID_Convite`) REFERENCES `convite` (`ID_Convite`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitacao_ibfk_2` FOREIGN KEY (`ID_Token`) REFERENCES `token` (`ID_Token`) ON DELETE SET NULL;

--
-- Constraints for table `token`
--
ALTER TABLE `token`
  ADD CONSTRAINT `token_ibfk_1` FOREIGN KEY (`ID_Convite`) REFERENCES `convite` (`ID_Convite`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
