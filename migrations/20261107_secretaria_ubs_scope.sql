-- Acessa+ Saúde — escopo administrativo de UBS por conta Secretaria
-- PostgreSQL/Supabase. Migração aditiva; aplicar primeiro em staging, nunca via request.
-- O vínculo identifica a conta Secretaria proprietária/criadora; cidade e UF continuam
-- sendo validadas contra os dados canônicos da própria conta em cada requisição.

ALTER TABLE public.ubs
    ADD COLUMN IF NOT EXISTS secretaria_id BIGINT NULL;

CREATE INDEX IF NOT EXISTS idx_ubs_secretaria_municipio
    ON public.ubs (secretaria_id, cidade, estado);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'fk_ubs_secretaria_owner'
          AND conrelid = 'public.ubs'::regclass
    ) THEN
        ALTER TABLE public.ubs
            ADD CONSTRAINT fk_ubs_secretaria_owner
            FOREIGN KEY (secretaria_id)
            REFERENCES public.administradores(id)
            ON DELETE SET NULL;
    END IF;
END
$$;

-- Recupera somente UBS cuja autoria está comprovada por um único criador Secretaria
-- na auditoria e cuja cidade/UF ainda correspondem à conta. Ambiguidades ficam sem dono.
WITH logged_owner AS (
    SELECT entidade_id, MIN(admin_id) AS admin_id
    FROM public.auditoria
    WHERE acao = 'ubs_criada'
      AND entidade = 'ubs'
      AND tipo_usuario = 'secretaria'
      AND admin_id IS NOT NULL
      AND entidade_id IS NOT NULL
    GROUP BY entidade_id
    HAVING COUNT(DISTINCT admin_id) = 1
)
UPDATE public.ubs AS u
SET secretaria_id = a.id
FROM logged_owner AS logged
JOIN public.administradores AS a
  ON a.id = logged.admin_id
 AND a.tipo = 'secretaria'
WHERE u.id = logged.entidade_id
  AND u.secretaria_id IS NULL
  AND TRIM(u.cidade) <> ''
  AND TRIM(a.cidade) <> ''
  AND UPPER(TRIM(u.estado)) ~ '^[A-Z]{2}$'
  AND UPPER(TRIM(a.estado)) ~ '^[A-Z]{2}$'
  AND LOWER(TRIM(u.cidade)) = LOWER(TRIM(a.cidade))
  AND UPPER(TRIM(u.estado)) = UPPER(TRIM(a.estado));
