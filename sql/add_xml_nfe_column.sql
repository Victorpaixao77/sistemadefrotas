-- Garante que a tabela fiscal_nfe_clientes tenha a coluna xml_nfe (conteúdo do XML).
-- Pode ser executado várias vezes: só adiciona a coluna se ela ainda não existir.

DROP PROCEDURE IF EXISTS add_xml_nfe_column_proc;

DELIMITER //
CREATE PROCEDURE add_xml_nfe_column_proc()
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS 
      WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'fiscal_nfe_clientes' 
        AND COLUMN_NAME = 'xml_nfe') = 0 THEN
    ALTER TABLE fiscal_nfe_clientes 
    ADD COLUMN xml_nfe LONGTEXT NULL COMMENT 'Conteúdo XML da NF-e para download' 
    AFTER protocolo_autorizacao;
  END IF;
END //
DELIMITER ;

CALL add_xml_nfe_column_proc();
DROP PROCEDURE add_xml_nfe_column_proc;
