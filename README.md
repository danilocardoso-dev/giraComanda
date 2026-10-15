# GiraComanda

Sistema multiempresa para restaurantes e lanchonetes, com filiais isoladas, comandas por cartão e QR Code, caixa, estoque, contas a receber, relatórios e auditoria.

## Escopo da versão piloto

- empresas e filiais isoladas, com compartilhamento opcional de clientes;
- administrador da plataforma, administrador da empresa, caixa e atendente;
- comandas, produtos por unidade ou peso, divisão de itens e pagamentos mistos;
- cartão reutilizável com QR Code e comprovante não fiscal;
- caixa com abertura, suprimento, sangria, conferência e fechamento;
- estoque, contas a receber, pagamentos parciais e resumo mensal;
- cancelamento auditado de comandas enquanto o caixa relacionado estiver aberto;
- recuperação de senha por e-mail;
- backup diário, cópia externa opcional e restauração controlada.

## Requisitos

- PHP 8.3 ou superior com extensões exigidas pelo Laravel;
- MySQL 8 ou MariaDB compatível;
- Composer 2;
- Node.js e npm somente para gerar os arquivos de interface;
- Apache ou Nginx com o diretório público apontado para `public`;
- HTTPS obrigatório quando houver acesso fora da rede local.

## Instalação

1. Copie `.env.example` para `.env` e configure banco, URL, e-mail e caminhos do MySQL.
2. Execute `composer install --no-dev --optimize-autoloader`.
3. Execute `php artisan key:generate` apenas em uma instalação nova.
4. Execute `php artisan migrate --force`.
5. Execute `npm ci` e `npm run build` na preparação da versão.
6. Execute `php artisan storage:link` se a instalação passar a usar arquivos públicos.
7. Execute `php artisan optimize` depois de conferir o ambiente.

O servidor web deve publicar somente a pasta `public`. Nunca publique a raiz do projeto.

## Configuração mínima de produção

```dotenv
APP_ENV=production
APP_VERSION=1.0.1-piloto
APP_DEBUG=false
APP_URL=https://comandas.seudominio.com.br
LOG_CHANNEL=daily
LOG_LEVEL=warning

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

MAIL_MAILER=smtp
MAIL_HOST=servidor-smtp
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS=nao-responda@seudominio.com.br
MAIL_FROM_NAME="GiraComanda"
```

Mantenha o arquivo `.env` fora do controle de versão. Use uma chave exclusiva por instalação e não reutilize senhas das contas de demonstração.

Antes de liberar o acesso, execute `php artisan app:production-check`. Todos os itens precisam aparecer como `APROVADO`.

## Agendador e disponibilidade

O backup é programado diariamente às 02:00. No Windows, crie uma tarefa no Agendador de Tarefas que execute a cada minuto:

```text
C:\caminho\php.exe C:\caminho\giracomanda\artisan schedule:run
```

Configure Apache e MySQL para iniciarem como serviços do Windows. Depois de reiniciar o servidor, confirme `/up`, a tela de acesso e a conexão com o banco sem abrir manualmente o XAMPP.

## Backup externo e restauração

O backup principal fica no disco privado da aplicação. Para manter uma segunda cópia em HD externo, NAS ou pasta sincronizada, configure:

```dotenv
BACKUP_EXTERNAL_DISK=backup_external
BACKUP_EXTERNAL_PATH="D:\Backups\GiraComanda"
```

Gere uma cópia manual com `php artisan backup:database`. Teste a restauração inicialmente em uma instalação separada:

```text
php artisan backup:restore giracomanda-AAAA-MM-DD_HH-MM-SS.sql --force
```

A restauração substitui os dados atuais e coloca a aplicação temporariamente em manutenção. Nunca a execute sem preservar uma cópia do estado corrente.

## Publicação e atualização

Antes de cada versão:

1. execute `php artisan test`;
2. execute `composer audit --locked` e `npm audit`;
3. execute `npm run build`;
4. gere e confira um backup;
5. ative manutenção com `php artisan down`;
6. atualize os arquivos e execute `php artisan migrate --force`;
7. execute `php artisan optimize` e `php artisan up`;
8. valide login, comanda, caixa, recebimento e relatório.

Reverta a publicação se o login falhar, ocorrer erro na abertura ou fechamento de comandas, houver divergência de caixa ou isolamento incorreto entre empresas. Preserve o backup anterior e a versão Git usada no servidor.

## Aceite do restaurante piloto

- [ ] dois usuários operam simultaneamente sem misturar filial ou comanda;
- [ ] atendente não acessa caixa, clientes, relatórios ou administração;
- [ ] caixa abre, recebe em todas as formas, registra sangria e fecha corretamente;
- [ ] cartão é lido por QR Code, liberado no fechamento e pode ser reutilizado;
- [ ] impressão do cartão e do comprovante funciona na impressora real;
- [ ] venda por peso confere com a balança do estabelecimento;
- [ ] fiado aparece no cliente, no contas a receber e no resumo mensal;
- [ ] cancelamento estorna estoque e valores e aparece na auditoria;
- [ ] backup externo é criado e restaurado em ambiente separado;
- [ ] servidor reinicia e volta a operar sem intervenção manual;
- [ ] responsável pelo estabelecimento aprovou privacidade, termos e caráter não fiscal.

## Limites conhecidos do piloto

- cancelamentos com caixa já fechado ou anotação parcialmente paga exigem correção orientada pelo administrador;
- integração automática com balança e WhatsApp oficial não faz parte desta versão;
- comprovantes emitidos são não fiscais;
- melhorias de usabilidade nas comandas serão tratadas após a observação do piloto.
