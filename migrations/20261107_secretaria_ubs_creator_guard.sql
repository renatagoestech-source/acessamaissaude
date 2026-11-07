-- Corrige owners preexistentes: uma conta Secretaria só pode receber UBS cuja criação
-- está comprovada por um único evento de auditoria e cuja cidade/UF ainda coincidem.
-- PostgreSQL/Supabase. Reexecutável sem alterar vínculos válidos.
WITH unique_creator AS (
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
SET secretaria_id = NULL
WHERE u.secretaria_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM unique_creator AS c
      JOIN public.administradores AS a
        ON a.id = c.admin_id
       AND a.tipo = 'secretaria'
      WHERE c.entidade_id = u.id
        AND c.admin_id = u.secretaria_id
        AND TRIM(u.cidade) <> ''
        AND TRIM(a.cidade) <> ''
        AND UPPER(TRIM(u.estado)) ~ '^[A-Z]{2}$'
        AND UPPER(TRIM(a.estado)) ~ '^[A-Z]{2}$'
        AND LOWER(TRIM(u.cidade)) = LOWER(TRIM(a.cidade))
        AND UPPER(TRIM(u.estado)) = UPPER(TRIM(a.estado))
  );

-- Também preenche registros ainda sem owner, mas que tenham prova inequívoca.
WITH unique_creator AS (
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
FROM unique_creator AS c
JOIN public.administradores AS a
  ON a.id = c.admin_id
 AND a.tipo = 'secretaria'
WHERE u.id = c.entidade_id
  AND u.secretaria_id IS NULL
  AND TRIM(u.cidade) <> ''
  AND TRIM(a.cidade) <> ''
  AND UPPER(TRIM(u.estado)) ~ '^[A-Z]{2}$'
  AND UPPER(TRIM(a.estado)) ~ '^[A-Z]{2}$'
  AND LOWER(TRIM(u.cidade)) = LOWER(TRIM(a.cidade))
  AND UPPER(TRIM(u.estado)) = UPPER(TRIM(a.estado));
