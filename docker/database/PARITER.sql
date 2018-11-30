CREATE TABLE IF NOT EXISTS `SESSIONS` (
  `SES_ID` varchar(50) CHARACTER SET ascii NOT NULL,
  `SES_EXPIRES` datetime NOT NULL,
  `SES_DATA` text NOT NULL,
  PRIMARY KEY (`SES_ID`),
  KEY `SES_EXPIRES` (`SES_EXPIRES`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `TRANSLATIONS` (
  `TSL_ID` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
  `TSL_MODULE` varchar(50) CHARACTER SET ascii NOT NULL,
  `TSL_TEMPLATE` varchar(50) CHARACTER SET ascii NOT NULL,
  `TSL_KEYWORD` varchar(100) CHARACTER SET ascii NOT NULL,
  `TSL_FR_TEXT` text NOT NULL,
  `TSL_EN_TEXT` text NOT NULL,
  PRIMARY KEY (`TSL_ID`),
  UNIQUE KEY `MODULE_GROUP_KEYWORD` (`TSL_MODULE`,`TSL_TEMPLATE`,`TSL_KEYWORD`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `TRANSLATIONS` (`TSL_ID`, `TSL_MODULE`, `TSL_TEMPLATE`, `TSL_KEYWORD`, `TSL_FR_TEXT`, `TSL_EN_TEXT`) VALUES
(1, 'frontend', 'default', 'main_site', 'Site officiel', 'Main site'),
(4, 'frontend', 'default', 'space_before_column', ' ', ''),
(7, 'frontend', 'index', 'register_for_updates', 'Inscrivez-vous pour recevoir des nouvelles du projet', 'Register for future updates on the project'),
(10, 'frontend', 'index', 'register_with', 'Inscription avec', 'Register with'),
(13, 'frontend', 'default', 'pariter_platform', 'Plateforme Pariter', 'Pariter platform'),
(16, 'frontend', 'auth', 'thanks_for_registering', 'Merci de votre inscription, à très bientôt !', 'Thanks for registering, we\'ll be in contact soon!'),
(19, 'frontend', 'auth', 'update_your_settings', 'Merci de mettre à jour vos informations', 'Please update your profile settings'),
(22, 'frontend', 'default', 'email', 'Adresse email', 'Email address'),
(25, 'frontend', 'default', 'display_name', 'Nom public', 'Display name'),
(28, 'frontend', 'default', 'submit', 'Envoyer', 'Submit');

CREATE TABLE IF NOT EXISTS `USERS` (
  `USR_ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `USR_EMAIL` varchar(500) CHARACTER SET ascii NOT NULL,
  `USR_DISPLAY_NAME` varchar(500) NOT NULL,
  `USR_LINKEDIN_IDENTIFIER` varchar(50) CHARACTER SET ascii DEFAULT NULL,
  `USR_GITHUB_IDENTIFIER` varchar(50) CHARACTER SET ascii DEFAULT NULL,
  PRIMARY KEY (`USR_ID`),
  UNIQUE KEY `USR_LINKEDIN_IDENTIFIER` (`USR_LINKEDIN_IDENTIFIER`),
  UNIQUE KEY `USR_GITHUB_IDENTIFIER` (`USR_GITHUB_IDENTIFIER`),
  KEY `USR_EMAIL` (`USR_EMAIL`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
