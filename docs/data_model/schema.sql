-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema pokebase
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema pokebase
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `pokebase` DEFAULT CHARACTER SET utf8 ;
USE `pokebase` ;

-- -----------------------------------------------------
-- Table `pokebase`.`cards`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pokebase`.`cards` (
  `card_id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NOT NULL,
  `set` VARCHAR(45) NOT NULL,
  `set_num` VARCHAR(45) NOT NULL,
  `lang` VARCHAR(45) NOT NULL,
  `price` FLOAT NOT NULL,
  `price_date` DATE NOT NULL,
  `condition` VARCHAR(45) NULL,
  `printing` VARCHAR(45) NULL,
  `grade` VARCHAR(45) NULL,
  PRIMARY KEY (`card_id`),
  UNIQUE INDEX `card_id_UNIQUE` (`card_id` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `pokebase`.`users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pokebase`.`users` (
  `user_id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(45) NOT NULL,
  `password` VARCHAR(45) NOT NULL,
  `role` VARCHAR(45) NOT NULL,
  `first_name` VARCHAR(45) NOT NULL,
  `last_name` VARCHAR(45) NOT NULL,
  `date_registered` DATE NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE INDEX `user_id_UNIQUE` (`user_id` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `pokebase`.`transactions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pokebase`.`transactions` (
  `trans_id` INT NOT NULL AUTO_INCREMENT,
  `date` DATE NOT NULL,
  `user_id` INT NOT NULL,
  `sold_for` FLOAT NULL,
  PRIMARY KEY (`trans_id`),
  UNIQUE INDEX `trans_id_UNIQUE` (`trans_id` ASC),
  INDEX `user_id_idx` (`user_id` ASC),
  CONSTRAINT `transactions_users_user_id`
    FOREIGN KEY (`user_id`)
    REFERENCES `pokebase`.`users` (`user_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `pokebase`.`exchanged_cards`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pokebase`.`exchanged_cards` (
  `exchange_id` INT NOT NULL AUTO_INCREMENT,
  `trans_id` INT NOT NULL,
  `card_id` INT NOT NULL,
  `recieved` TINYINT NOT NULL,
  PRIMARY KEY (`exchange_id`),
  UNIQUE INDEX `card_id_UNIQUE` (`exchange_id` ASC),
  INDEX `card_id_idx` (`card_id` ASC),
  INDEX `trans_id_idx` (`trans_id` ASC),
  CONSTRAINT `exchanged_cards_card_id`
    FOREIGN KEY (`card_id`)
    REFERENCES `pokebase`.`cards` (`card_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `exchanged_transactions_trans_id`
    FOREIGN KEY (`trans_id`)
    REFERENCES `pokebase`.`transactions` (`trans_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `pokebase`.`api_sessions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pokebase`.`api_sessions` (
  `session_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `ended_at` DATETIME NOT NULL,
  PRIMARY KEY (`session_id`),
  UNIQUE INDEX `session_id_UNIQUE` (`session_id` ASC),
  INDEX `user_id_idx` (`user_id` ASC),
  CONSTRAINT `api_sessions_users_user_id`
    FOREIGN KEY (`user_id`)
    REFERENCES `pokebase`.`users` (`user_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `pokebase`.`inventory_tracker`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pokebase`.`inventory_tracker` (
  `inv_id` INT NOT NULL AUTO_INCREMENT,
  `date` DATE NOT NULL,
  `total_value` FLOAT NOT NULL,
  PRIMARY KEY (`inv_id`),
  UNIQUE INDEX `inv_id_UNIQUE` (`inv_id` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `pokebase`.`audits`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pokebase`.`audits` (
  `audit_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `notes` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`audit_id`),
  UNIQUE INDEX `audit_id_UNIQUE` (`audit_id` ASC),
  INDEX `user_id_idx` (`user_id` ASC),
  CONSTRAINT `audits_users_user_id`
    FOREIGN KEY (`user_id`)
    REFERENCES `pokebase`.`users` (`user_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
