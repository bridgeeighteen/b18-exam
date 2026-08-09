-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+08:00";

-- 上面的时区设置为东八区时间，以便查看。请根据实际需求修改。

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- 表的结构 `questions`
--

CREATE TABLE `questions` (
  `id` int NOT NULL,
  `category` enum('IT','ACGN','Virtual_Singer','Broadcasting','Etiquette') NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `answer` varchar(255) NOT NULL,
  `type` enum('single','multiple') NOT NULL,
  `author` varchar(100) NOT NULL DEFAULT '' COMMENT '命题人'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `results`
--

CREATE TABLE `results` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `score` int NOT NULL,
  `end_time` timestamp NOT NULL,
  `invitation_code` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- --------------------------------------------------------

--
-- 表的结构 `matrix_users`
--

CREATE TABLE `matrix_users` (
  `id` int NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `forum_oauth_user_id` int DEFAULT NULL COMMENT '通过论坛 OAuth 验证的 Flarum 用户 ID，非空表示礼仪测试免考',
  `forum_oauth_verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `matrix_results`
--

CREATE TABLE `matrix_results` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `score` int NOT NULL,
  `end_time` timestamp NOT NULL,
  `registration_token` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `exam_papers`（持久化的试卷，供答题页与系统 API 共用）
--

CREATE TABLE `exam_papers` (
  `id` int NOT NULL,
  `channel` enum('forum','matrix') NOT NULL,
  `candidate_id` int NOT NULL,
  `question_ids` text NOT NULL COMMENT '试卷题目 ID 列表（JSON 数组，按出卷顺序）',
  `etiquette_exempt` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否礼仪题免考',
  `etiquette_exempt_ids` text COMMENT '免考礼仪题 ID 列表（JSON 数组）',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `api_keys`（管理面板 / API 的访问密钥）
--

CREATE TABLE `api_keys` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `key_hash` char(64) NOT NULL COMMENT '密钥的 SHA-256 哈希，明文只在创建时显示一次',
  `scopes` varchar(255) NOT NULL COMMENT '逗号分隔的作用域列表',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `audit_log`（管理操作审计日志）
--

CREATE TABLE `audit_log` (
  `id` int NOT NULL,
  `actor` varchar(255) NOT NULL COMMENT '操作者标识（管理员身份或 API 密钥名称）',
  `action` varchar(50) NOT NULL COMMENT '操作类型',
  `target` varchar(255) DEFAULT NULL COMMENT '操作目标',
  `detail` text COMMENT '补充信息（JSON）',
  `ip` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `api_rate_limits`（API 限流计数）
--

CREATE TABLE `api_rate_limits` (
  `bucket` varchar(128) NOT NULL COMMENT '限流桶，如 ip:1.2.3.4 或 key:1',
  `window_start` int NOT NULL COMMENT '窗口起始时间（Unix 秒）',
  `count` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `selected_categories` varchar(255) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `matrix_oauth_mxid` varchar(255) DEFAULT NULL COMMENT '通过 MAS OAuth 验证的 Matrix 账号（MXID），非空表示礼仪测试免考',
  `matrix_oauth_verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转储表的索引
--

--
-- 表的索引 `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- 表的索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `matrix_users`
--
ALTER TABLE `matrix_users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`),
  ADD KEY `email` (`email`);

--
-- 表的索引 `matrix_results`
--
ALTER TABLE `matrix_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- 表的索引 `api_keys`
--
ALTER TABLE `api_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_hash` (`key_hash`);

--
-- 表的索引 `exam_papers`
--
ALTER TABLE `exam_papers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `channel_candidate` (`channel`,`candidate_id`);

--
-- 表的索引 `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `actor` (`actor`),
  ADD KEY `action` (`action`),
  ADD KEY `created_at` (`created_at`);

--
-- 表的索引 `api_rate_limits`
--
ALTER TABLE `api_rate_limits`
  ADD PRIMARY KEY (`bucket`),
  ADD KEY `window_start` (`window_start`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = 1;

--
-- 使用表AUTO_INCREMENT `results`
--
ALTER TABLE `results`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = 1;

--
-- 使用表AUTO_INCREMENT `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = 1;

--
-- 使用表AUTO_INCREMENT `matrix_users`
--
ALTER TABLE `matrix_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = 1;

--
-- 使用表AUTO_INCREMENT `matrix_results`
--
ALTER TABLE `matrix_results`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = 1;

--
-- 使用表AUTO_INCREMENT `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = 1;

--
-- 使用表AUTO_INCREMENT `exam_papers`
--
ALTER TABLE `exam_papers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = 1;

--
-- 使用表AUTO_INCREMENT `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = 1;

--
-- 限制导出的表
--

--
-- 限制表 `results`
--
ALTER TABLE `results`
  ADD CONSTRAINT `results_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- 限制表 `matrix_results`
--
ALTER TABLE `matrix_results`
  ADD CONSTRAINT `matrix_results_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `matrix_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
