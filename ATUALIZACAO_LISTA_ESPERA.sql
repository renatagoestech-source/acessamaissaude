-- Execute no banco conecta_saude já existente. Faça backup antes.
ALTER TABLE ubs ADD COLUMN limite_diario INT UNSIGNED NOT NULL DEFAULT 12 AFTER usuario;
ALTER TABLE profissionais ADD COLUMN limite_diario INT UNSIGNED NOT NULL DEFAULT 12 AFTER mensagem_pos_venda;
ALTER TABLE lista_espera ADD COLUMN status ENUM('pendente','agendado','cancelado') NOT NULL DEFAULT 'pendente' AFTER data_consulta;
ALTER TABLE lista_espera ADD COLUMN consulta_id VARCHAR(60) NULL AFTER status;
ALTER TABLE lista_espera ADD INDEX idx_lista_espera_fila (ubs_id,especialidade,data_consulta,status,id);
ALTER TABLE lista_espera ADD CONSTRAINT fk_lista_consulta FOREIGN KEY (consulta_id) REFERENCES consultas(id) ON DELETE SET NULL;
ALTER TABLE notificacoes_profissionais MODIFY tipo ENUM('confirmacao','lembrete','lista_espera_agendada') NOT NULL;
CREATE TABLE IF NOT EXISTS lista_espera_profissionais (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, paciente_id INT UNSIGNED NOT NULL, profissional_id INT UNSIGNED NOT NULL, data_consulta DATE NOT NULL, status ENUM('pendente','agendado','cancelado') NOT NULL DEFAULT 'pendente', consulta_id VARCHAR(60) NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_lista_espera_prof (paciente_id,profissional_id,data_consulta), INDEX idx_lista_espera_prof_fila (profissional_id,data_consulta,status,id), CONSTRAINT fk_lwp_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE, CONSTRAINT fk_lwp_profissional FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE, CONSTRAINT fk_lwp_consulta FOREIGN KEY (consulta_id) REFERENCES consultas_profissionais(id) ON DELETE SET NULL
) ENGINE=InnoDB;
