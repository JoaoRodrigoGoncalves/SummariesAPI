-- phpMyAdmin SQL Dump
-- version 4.9.5deb2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 17-Jan-2021 às 13:27
-- Versão do servidor: 8.0.22-0ubuntu0.20.04.3
-- versão do PHP: 7.4.3

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
-- Estrutura da tabela `AccessTokens`
--

CREATE TABLE `AccessTokens` (
  `id` int NOT NULL,
  `userid` int NOT NULL,
  `token` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `eventName` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiredate` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `attachmentMapping`
--

CREATE TABLE `attachmentMapping` (
  `id` int NOT NULL,
  `summaryID` int DEFAULT NULL,
  `filename` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `classesList`
--

CREATE TABLE `classesList` (
  `id` int NOT NULL,
  `name` varchar(127) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `classesList`
--

INSERT INTO `classesList` (`id`, `name`) VALUES
(0, 'No Class');

-- --------------------------------------------------------

--
-- Estrutura da tabela `summaries`
--

CREATE TABLE `summaries` (
  `id` int NOT NULL,
  `userid` int NOT NULL,
  `date` date NOT NULL,
  `summaryNumber` int NOT NULL,
  `workspace` int NOT NULL,
  `contents` longtext COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `user` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classID` int NOT NULL DEFAULT '0',
  `password` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `displayName` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adminControl` tinyint(1) NOT NULL DEFAULT '0',
  `isDeletionProtected` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `users`
--

INSERT INTO `users` (`id`, `user`, `classID`, `password`, `displayName`, `adminControl`, `isDeletionProtected`) VALUES
(1, 'admin', 0, '$2y$10$iWBNPjIrNjIJw2yXmNd61uHFxzOlttMbYrYMqvW4j4DLN30JbS0ay', 'Administrator', 1, 1);

-- --------------------------------------------------------

--
-- Estrutura da tabela `workspaces`
--

CREATE TABLE `workspaces` (
  `id` int NOT NULL,
  `name` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `read` tinyint(1) NOT NULL DEFAULT '1',
  `write` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `workspaces`
--

INSERT INTO `workspaces` (`id`, `name`, `read`, `write`) VALUES
(1, 'Default', 1, 1);

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `AccessTokens`
--
ALTER TABLE `AccessTokens`
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
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user` (`user`);

--
-- Índices para tabela `workspaces`
--
ALTER TABLE `workspaces`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `AccessTokens`
--
ALTER TABLE `AccessTokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de tabela `attachmentMapping`
--
ALTER TABLE `attachmentMapping`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de tabela `classesList`
--
ALTER TABLE `classesList`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de tabela `summaries`
--
ALTER TABLE `summaries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `workspaces`
--
ALTER TABLE `workspaces`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
