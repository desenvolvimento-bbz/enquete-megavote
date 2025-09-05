MegaVote Enquetes 

MegaVote Enquetes é um app leve para criação e condução de enquetes condominiais, com acesso público por link seguro para participantes e painel administrativo completo para síndicos/gestores. O projeto também foi pensado como hub, permitindo plugar outros módulos — como o Sorteio de Vagas — reaproveitando a mesma autenticação de administradores.

✨ Principais recursos
Módulo Enquetes (admin)

Criação de “salas” de enquete com título e data.

Link público com token aleatório (ativar/desativar/regenerar).

Pautas para ordenação e edição.

Enquetes por pautas, com:

Múltipla ou única escolha (max_choices).

Ativar/pausar votação (toggle).

“Liberar resultado ao público” (apresenta gráfico em tempo real).

Ordenação de enquetes por item (↑/↓).

Resultado por enquete e por item (gráficos + exportação em PDF).

Contador de participantes da sala (modelo “participante público”).

Acesso Público (participantes)

Entrada por link público (/public/join.php?token=...).

“Cadastro leve” para a sala específica: nome, e-mail, condomínio, bloco e unidade.

Sessão de participante escopada por sala: o mesmo convidado entra direto caso já tenha se identificado.

Votações:

Só aparecem enquetes ativas ou com resultado liberado.

Respeita limite de escolhas, evita voto duplicado, registra IP e anulações.

Segurança

Cookie de sessão com flags seguras (HttpOnly, SameSite, Secure quando HTTPS).

Regeneração de ID ao login e metadados de sessão (criada em, last activity).

Timeout de inatividade e TTL absoluto (configuráveis) + “fingerprint” leve (UA+IP parcial).

Guardas de rota:

enforceSessionGuard('admin') para admin.

Sessão “basic” escopada por enquete para participantes.

CSRF preparado (em várias telas) e fácil de ativar globalmente.

Após login de admin, exibir um Hub com cards:

Enquetes

Sorteio de Vagas

🧭 Fluxo resumido

Admin

Faz login ou herda login CondoHuby (usuário/senha).

Cria uma sala e suas pautas/itens.

Cria enquetes dentro das pautas e ativa quando quiser.

Ativa o link público e compartilha com os moradores.

Acompanha resultados em tempo real; pode encerrar votação e exportar PDF.

Participante (morador)

Acessa o link público.

Preenche identificação rápida (nome, e-mail, condomínio, bloco, unidade).

Entra direto na lista de pautas e vota nas enquetes ativas.

Pode visualizar resultados se liberados.

🧱 Modelo de dados (resumo)

A estrutura completa (DDL) está no schema.sql. Abaixo, uma visão por alto:

users: admins do sistema (master/admin).

assembleias: salas com token público e flags de link.

itens: pautas numeradas de uma assembleia.

polls: enquetes ligadas a um item, com flags is_active e show_results.

options: alternativas de cada enquete.

votes: votos (com is_annulled, IP, timestamps).

participants: novo cadastro leve por sala (nome, e-mail, bloco, etc.). Substitui o antigo inscritos no papel de identificar participantes no link público.

🗂️ Estrutura de pastas (sugerida)
poll-app/
├─ admin/               # telas do admin (painéis, CRUDs)
├─ auth/                # login, logout, session_timeout
├─ vote/                # telas do participante logado (pautas, enquetes)
├─ public/              # join.php + fluxo público de entrada
├─ raffle/              # (opcional) módulo Sorteio de Vagas integrado ao hub
├─ layout/              # header.php / footer.php / assets comuns
├─ config/
│  └─ db.php            # conexão PDO
├─ schema/
│  └─ schema.sql        # DDL completa
├─ docker/              # Dockerfile(s) e compose
└─ index.php            # redireciona pro login/hub conforme sessão

🔗 Link público (participantes)

Criar sala ➜ admin habilita link em painel_admin/manage_itens.

O link é do tipo:
https://dominio/public/join.php?token=<invite_token>

O participante não cria conta no sistema; ele se identifica na sala e recebe sessão “basic” escopada à sala.