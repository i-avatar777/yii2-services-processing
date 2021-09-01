
DROP TABLE IF EXISTS `currency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currency` (
                            `id` int unsigned NOT NULL AUTO_INCREMENT,
                            `decimals` int DEFAULT NULL,
                            `amount` bigint unsigned DEFAULT '0',
                            `name` varchar(100) DEFAULT NULL,
                            `code` varchar(10) DEFAULT NULL,
                            `address` varchar(70) DEFAULT NULL,
                            `decimals_view` tinyint DEFAULT '2',
                            `decimals_view_shop` int DEFAULT NULL,
                            PRIMARY KEY (`id`),
                            KEY `currency_code_index` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;


DROP TABLE IF EXISTS `login`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login` (
                         `id` int NOT NULL AUTO_INCREMENT,
                         `login` varchar(64) DEFAULT NULL,
                         `secret` varchar(64) DEFAULT NULL,
                         `created_at` int DEFAULT NULL,
                         `name` varchar(100) DEFAULT NULL,
                         PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;


DROP TABLE IF EXISTS `operations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operations` (
                              `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                              `wallet_id` int DEFAULT NULL,
                              `transaction_id` int DEFAULT NULL,
                              `type` tinyint(1) DEFAULT NULL,
                              `datetime` bigint DEFAULT NULL,
                              `before` bigint unsigned DEFAULT '0',
                              `after` bigint unsigned DEFAULT '0',
                              `amount` bigint unsigned DEFAULT '0',
                              `comment` text CHARACTER SET utf8 COLLATE utf8_general_ci,
                              `address` varchar(70) DEFAULT NULL,
                              `hash` varchar(64) DEFAULT NULL,
                              PRIMARY KEY (`id`),
                              KEY `datetime_index` (`datetime`),
                              KEY `wallet_id_index` (`wallet_id`),
                              KEY `operations_transaction_id_index` (`transaction_id`)
) ENGINE=InnoDB AUTO_INCREMENT=49973 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;


DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
                                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                                `from` int DEFAULT NULL,
                                `to` int DEFAULT NULL,
                                `amount` bigint unsigned DEFAULT '0',
                                `comment` text CHARACTER SET utf8 COLLATE utf8_general_ci,
                                `datetime` bigint DEFAULT NULL,
                                `type` tinyint DEFAULT NULL,
                                `address` varchar(70) DEFAULT NULL,
                                `hash` varchar(64) DEFAULT NULL,
                                PRIMARY KEY (`id`),
                                KEY `datetime_index` (`datetime`),
                                KEY `from_index` (`from`),
                                KEY `to_index` (`to`)
) ENGINE=InnoDB AUTO_INCREMENT=81884 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;


DROP TABLE IF EXISTS `wallet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet` (
                          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                          `currency_id` int DEFAULT NULL,
                          `amount` bigint unsigned DEFAULT '0',
                          `comment` varchar(255) DEFAULT NULL,
                          `address` varchar(70) DEFAULT NULL,
                          `is_deleted` tinyint DEFAULT '0',
                          `password` varchar(100) DEFAULT NULL,
                          `master_key` varchar(255) DEFAULT NULL,
                          PRIMARY KEY (`id`),
                          KEY `wallet_currency_id_index` (`currency_id`),
                          KEY `wallet_amount_index` (`amount`),
                          KEY `wallet_is_deleted_index` (`is_deleted`)
) ENGINE=InnoDB AUTO_INCREMENT=28539 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
