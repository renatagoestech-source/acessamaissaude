# Acessa+ Saúde — correções da agenda e do portal da clínica

## Correções aplicadas nesta versão

- **Salvamento sem bloqueio CSRF no portal profissional:** as ações da clínica não pedem token CSRF; continuam exigindo uma sessão autenticada da própria clínica. As ações administrativas e as gravações dos fluxos públicos/UBS mantêm CSRF.
- **Sessão da clínica durante o expediente:** removida a expiração automática profissional após 30 minutos/8 horas. O botão **Sair** encerra o login; redefinir a senha continua invalidando sessões anteriores.
- **Sessões e prontuários fora da interface:** removidos o bloco de gestão de sessões, a aba/atalhos de prontuário e as respectivas rotas de API. **Nenhum registro existente foi apagado do banco.**
- **Grade semanal compacta:** os sete dias aparecem como botões curtos; ao escolher um, só o painel daquele dia fica visível. É possível ativar/fechar o dia e editar expediente, duração e pausa sem alongar a página; os dados dos outros dias permanecem carregados e são preservados.
- **Salvamento separado:** o botão de expediente salva apenas expediente/pausas; o botão de perfil salva os dados da clínica sem recarregar nem sobrescrever a grade que ainda esteja sendo editada.
- **Validação dos horários:** em dias ativos, início deve anteceder o fim, duração deve ficar entre 5 e 240 minutos e a pausa precisa estar completa e dentro do expediente. O paciente vê somente os horários livres, sem a duração do atendimento; o servidor continua aplicando a duração no cálculo dos slots.
- **Cache do navegador:** JavaScript e CSS usam a data de modificação dos arquivos nas URLs, para o navegador buscar a versão nova após a cópia.

> A sessão de login ainda é necessária para o servidor identificar qual clínica está salvando. A desativação de CSRF nas rotas profissionais foi feita a pedido; isso reduz a proteção contra requisições cruzadas nessas ações. Antes de oferecer o serviço publicamente, considere reativá-la com uma integração estável de token e teste no ambiente final.

## Agendamento particular já implementado

- A clínica configura expediente, duração e pausa por dia da semana.
- O servidor calcula e revalida horários disponíveis e protege as reservas concorrentes para evitar dupla marcação.
- A clínica pode aprovar automaticamente ou confirmar manualmente; pedidos pendentes aparecem na agenda profissional.
- O paciente pode cancelar/remarcar conforme os prazos definidos pela clínica, pelo link de gerenciamento recebido por e-mail.
- Recuperação de senha permanece disponível por link de uso único com validade de uma hora. Cadastro e login não exigem verificação prévia de e-mail.

## Aplicar no XAMPP sem reinstalar

1. Faça cópia de segurança da pasta atual `C:\xampp\htdocs\acessa+saude\` e do banco.
2. Extraia este ZIP **sobre a pasta existente**, substituindo os arquivos. Não misture arquivos de uma versão antiga.
3. **Não execute `install.php` e não reimporte `database.sql` sobre sua base existente.** Esta correção não exige reinstalação nem apagar dados.
4. Reinicie o Apache no XAMPP e recarregue com `Ctrl+F5` (o JavaScript tem cache-busting automático).
5. Entre no portal da clínica, abra **Minha marca**, altere dias/horários/pausas e use **Salvar expediente e pausas semanais**. Para os dados da clínica, use **Salvar minha marca**.

## Configurações importantes para produção

- A recuperação de senha, confirmações e links de gestão dependem de e-mail transacional configurado (`RESEND_API_KEY`, `MAIL_FROM`, `APP_URL`) e domínio verificado. Sem isso, o e-mail não é enviado.
- Antes de usar dados reais de saúde, configure HTTPS, credenciais de banco com privilégio mínimo, backups e restauração testada, monitoração, retenção/auditoria, política de privacidade, termos e os processos aplicáveis à LGPD.
- O código não instala um worker/cron de lembretes automáticos. Não prometa lembretes por SMS/WhatsApp até integrar e monitorar um provedor.
