CREATE TABLE IF NOT EXISTS public.php_sessions (
    id_hash CHAR(64) PRIMARY KEY,
    payload TEXT NOT NULL,
    expires_at BIGINT NOT NULL
);

CREATE INDEX IF NOT EXISTS php_sessions_expires_at_idx
    ON public.php_sessions (expires_at);

ALTER TABLE public.php_sessions ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON TABLE public.php_sessions FROM PUBLIC, anon, authenticated;
GRANT ALL ON TABLE public.php_sessions TO service_role;
