CREATE SCHEMA IF NOT EXISTS `agendaagil` DEFAULT CHARACTER SET utf8mb4 ;
USE `agendaagil` ;

-- -----------------------------------------------------
-- Table `agendaagil`.`roles`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `agendaagil`.`roles` (
  `idRol` INT(11) NOT NULL AUTO_INCREMENT,
  `nombreRol` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`idRol`))
ENGINE = InnoDB
AUTO_INCREMENT = 5
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `agendaagil`.`usuarios`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `agendaagil`.`usuarios` (
  `idUsuario` INT(11) NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NULL DEFAULT NULL,
  `correo` VARCHAR(100) NULL DEFAULT NULL,
  `contraseña` VARCHAR(255) NULL DEFAULT NULL,
  `idRol` INT(11) NULL DEFAULT NULL,
  `reset_token` VARCHAR(64) NULL DEFAULT NULL,
  `token_expira` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`idUsuario`),
  UNIQUE INDEX `correo` (`correo` ASC) VISIBLE,
  INDEX `idRol` (`idRol` ASC) VISIBLE,
  CONSTRAINT `usuarios_ibfk_1`
    FOREIGN KEY (`idRol`)
    REFERENCES `agendaagil`.`roles` (`idRol`))
ENGINE = InnoDB
AUTO_INCREMENT = 20
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `agendaagil`.`pacientes`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `agendaagil`.`pacientes` (
  `idPaciente` INT(11) NOT NULL AUTO_INCREMENT,
  `idUsuario` INT(11) NULL DEFAULT NULL,
  `idDentistaAsignado` INT(11) NULL DEFAULT NULL,
  `telefono` VARCHAR(15) NULL DEFAULT NULL,
  `fechaNacimiento` DATE NULL DEFAULT NULL,
  PRIMARY KEY (`idPaciente`),
  INDEX `idUsuario` (`idUsuario` ASC) VISIBLE,
  INDEX `idDentistaAsignado` (`idDentistaAsignado` ASC) VISIBLE,
  CONSTRAINT `pacientes_ibfk_1`
    FOREIGN KEY (`idUsuario`)
    REFERENCES `agendaagil`.`usuarios` (`idUsuario`),
  CONSTRAINT `pacientes_ibfk_2`
    FOREIGN KEY (`idDentistaAsignado`)
    REFERENCES `agendaagil`.`dentistas` (`idDentista`))
ENGINE = InnoDB
AUTO_INCREMENT = 20
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `agendaagil`.`dentistas`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `agendaagil`.`dentistas` (
  `idDentista` INT(11) NOT NULL AUTO_INCREMENT,
  `idUsuario` INT(11) NULL DEFAULT NULL,
  `especialidad` VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`idDentista`),
  INDEX `idUsuario` (`idUsuario` ASC) VISIBLE,
  CONSTRAINT `dentistas_ibfk_1`
    FOREIGN KEY (`idUsuario`)
    REFERENCES `agendaagil`.`usuarios` (`idUsuario`))
ENGINE = InnoDB
AUTO_INCREMENT = 3
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `agendaagil`.`tratamientos`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `agendaagil`.`tratamientos` (
  `idTratamiento` INT(11) NOT NULL AUTO_INCREMENT,
  `nombreTratamiento` VARCHAR(100) NULL DEFAULT NULL,
  `descripcion` TEXT NULL DEFAULT NULL,
  `precio` DECIMAL(10,2) NULL DEFAULT NULL,
  PRIMARY KEY (`idTratamiento`))
ENGINE = InnoDB
AUTO_INCREMENT = 4
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `agendaagil`.`citas`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `agendaagil`.`citas` (
  `idCita` INT(11) NOT NULL AUTO_INCREMENT,
  `idPaciente` INT(11) NULL DEFAULT NULL,
  `idDentista` INT(11) NULL DEFAULT NULL,
  `idTratamiento` INT(11) NULL DEFAULT NULL,
  `descripcion` TEXT NULL DEFAULT NULL,
  `fecha` DATE NULL DEFAULT NULL,
  `diaSemana` VARCHAR(20) NULL DEFAULT NULL,
  `horaInicio` TIME NULL DEFAULT NULL,
  `horaFin` TIME NULL DEFAULT NULL,
  `estado` VARCHAR(50) NULL DEFAULT NULL,
  `recordatorio24hEnviado` TINYINT(1) NULL DEFAULT 0,
  `fechaRecordatorio24h` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`idCita`),
  INDEX `idPaciente` (`idPaciente` ASC) VISIBLE,
  INDEX `idDentista` (`idDentista` ASC) VISIBLE,
  INDEX `idTratamiento` (`idTratamiento` ASC) VISIBLE,
  CONSTRAINT `citas_ibfk_1`
    FOREIGN KEY (`idPaciente`)
    REFERENCES `agendaagil`.`pacientes` (`idPaciente`),
  CONSTRAINT `citas_ibfk_2`
    FOREIGN KEY (`idDentista`)
    REFERENCES `agendaagil`.`dentistas` (`idDentista`),
  CONSTRAINT `citas_ibfk_3`
    FOREIGN KEY (`idTratamiento`)
    REFERENCES `agendaagil`.`tratamientos` (`idTratamiento`))
ENGINE = InnoDB
AUTO_INCREMENT = 4
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `agendaagil`.`historialclinico`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `agendaagil`.`historialclinico` (
  `idHistorial` INT(11) NOT NULL AUTO_INCREMENT,
  `idPaciente` INT(11) NULL DEFAULT NULL,
  `idCita` INT(11) NULL DEFAULT NULL,
  `motivoConsulta` TEXT NULL DEFAULT NULL,
  `diagnostico` TEXT NULL DEFAULT NULL,
  `tratamientoAplicado` TEXT NULL DEFAULT NULL,
  `observaciones` TEXT NULL DEFAULT NULL,
  `fechaRegistro` DATETIME NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`idHistorial`),
  INDEX `idPaciente` (`idPaciente` ASC) VISIBLE,
  INDEX `idCita` (`idCita` ASC) VISIBLE,
  CONSTRAINT `historialclinico_ibfk_1`
    FOREIGN KEY (`idPaciente`)
    REFERENCES `agendaagil`.`pacientes` (`idPaciente`),
  CONSTRAINT `historialclinico_ibfk_2`
    FOREIGN KEY (`idCita`)
    REFERENCES `agendaagil`.`citas` (`idCita`))
ENGINE = InnoDB
AUTO_INCREMENT = 2
DEFAULT CHARACTER SET = utf8mb4;


-- -----------------------------------------------------
-- Table `agendaagil`.`horarios`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `agendaagil`.`horarios` (
  `idHorario` INT(11) NOT NULL AUTO_INCREMENT,
  `idDentista` INT(11) NULL DEFAULT NULL,
  `diaSemana` VARCHAR(20) NULL DEFAULT NULL,
  `fecha` DATE NULL DEFAULT NULL,
  `horaInicio` TIME NULL DEFAULT NULL,
  `horaFin` TIME NULL DEFAULT NULL,
  PRIMARY KEY (`idHorario`),
  INDEX `idDentista` (`idDentista` ASC) VISIBLE,
  CONSTRAINT `horarios_ibfk_1`
    FOREIGN KEY (`idDentista`)
    REFERENCES `agendaagil`.`dentistas` (`idDentista`))
ENGINE = InnoDB
AUTO_INCREMENT = 5
DEFAULT CHARACTER SET = utf8mb4;

USE `agendaagil`;

DELIMITER $$
USE `agendaagil`$$
CREATE
DEFINER=`root`@`localhost`
TRIGGER `agendaagil`.`after_citas_insert`
AFTER INSERT ON `agendaagil`.`citas`
FOR EACH ROW
BEGIN
    INSERT INTO horarios (idDentista, diaSemana, fecha, horaInicio, horaFin)
    VALUES (NEW.idDentista, NEW.diaSemana, NEW.fecha, NEW.horaInicio, NEW.horaFin);
END$$


DELIMITER ;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
