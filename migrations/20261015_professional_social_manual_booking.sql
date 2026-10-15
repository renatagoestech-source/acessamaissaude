-- Instagram do perfil público e cadastro manual de clientes sem Cartão SUS.
ALTER TABLE profissionais ADD COLUMN IF NOT EXISTS instagram_url VARCHAR(255);
ALTER TABLE pacientes ALTER COLUMN sus DROP NOT NULL;
