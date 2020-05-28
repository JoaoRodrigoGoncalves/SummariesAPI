-- phpMyAdmin SQL Dump
-- version 4.9.4
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 28-Maio-2020 às 16:33
-- Versão do servidor: 5.7.30-0ubuntu0.18.04.1
-- versão do PHP: 7.2.24-0ubuntu0.18.04.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `summariesDB`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `APIkeys`
--

CREATE TABLE `APIkeys` (
  `id` int(11) NOT NULL,
  `owner` varchar(257) NOT NULL,
  `apikey` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Extraindo dados da tabela `APIkeys`
--

INSERT INTO `APIkeys` (`id`, `owner`, `apikey`) VALUES
(1, 'SOFTWARE', '1f984e2ed1545f287fe473c890266fea901efcd63d07967ae6d2f09f4566ddde930923ee9212ea815186b0c11a620a5cc85e');

-- --------------------------------------------------------

--
-- Estrutura da tabela `attachmentMapping`
--

CREATE TABLE `attachmentMapping` (
  `id` int(11) NOT NULL,
  `summaryID` int(11) DEFAULT NULL,
  `filename` varchar(200) NOT NULL,
  `path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura da tabela `classesList`
--

CREATE TABLE `classesList` (
  `id` int(11) NOT NULL,
  `name` varchar(127) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Extraindo dados da tabela `classesList`
--

INSERT INTO `classesList` (`id`, `name`) VALUES
(0, 'No Class'),
(1, '67 - GEI 2018 a 2021'),
(2, '68 - Turismo 2018 a 2021'),
(3, 'Turma');

-- --------------------------------------------------------

--
-- Estrutura da tabela `summaries`
--

CREATE TABLE `summaries` (
  `id` int(11) NOT NULL,
  `userid` int(11) NOT NULL,
  `date` date NOT NULL,
  `summaryNumber` int(11) NOT NULL,
  `workspace` int(11) NOT NULL,
  `contents` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura da tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user` varchar(256) NOT NULL,
  `classID` int(11) NOT NULL DEFAULT '0',
  `password` varchar(256) NOT NULL,
  `displayName` varchar(256) NOT NULL,
  `adminControl` tinyint(1) NOT NULL DEFAULT '0',
  `isDeletionProtected` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Extraindo dados da tabela `users`
--

INSERT INTO `users` (`id`, `user`, `classID`, `password`, `displayName`, `adminControl`, `isDeletionProtected`) VALUES
(1, 'admin', 1, '$2y$10$p3xtZBuXdg5jkjOevHpxCOYIGw7kVfGfkqlJoK2hTi7rphbR5jyja', 'admin', 1, 1);

-- --------------------------------------------------------

--
-- Estrutura da tabela `workspaces`
--

CREATE TABLE `workspaces` (
  `id` int(11) NOT NULL,
  `name` varchar(256) NOT NULL,
  `read` tinyint(1) NOT NULL DEFAULT '1',
  `write` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Extraindo dados da tabela `workspaces`
--

INSERT INTO `workspaces` (`id`, `name`, `read`, `write`) VALUES
(1, '2020', 1, 1);

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `APIkeys`
--
ALTER TABLE `APIkeys`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `attachmentMapping`
--
ALTER TABLE `attachmentMapping`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `classesList`
--
ALTER TABLE `classesList`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `summaries`
--
ALTER TABLE `summaries`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `workspaces`
--
ALTER TABLE `workspaces`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `APIkeys`
--
ALTER TABLE `APIkeys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `attachmentMapping`
--
ALTER TABLE `attachmentMapping`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de tabela `classesList`
--
ALTER TABLE `classesList`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `summaries`
--
ALTER TABLE `summaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `workspaces`
--
ALTER TABLE `workspaces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
