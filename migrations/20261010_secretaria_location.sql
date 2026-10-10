ALTER TABLE public.administradores
    ADD COLUMN IF NOT EXISTS cidade VARCHAR(120) NOT NULL DEFAULT '';

ALTER TABLE public.administradores
    ADD COLUMN IF NOT EXISTS estado CHAR(2) NOT NULL DEFAULT '';
