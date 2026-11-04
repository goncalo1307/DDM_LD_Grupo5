ALTER TABLE Utilizador
  MODIFY COLUMN Tipo ENUM('cliente', 'admin', 'funcionario') NOT NULL DEFAULT 'cliente';
