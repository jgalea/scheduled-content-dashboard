=== Scheduled Content Dashboard ===
Contributors: jeangalea
Tags: scheduled, dashboard, widget, editorial calendar, missed schedule
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Widget do painel + calendário editorial com arrastar e soltar. Corrige agendamentos perdidos. API REST e resumo por e-mail. Sem inchaço.

== Description ==

Scheduled Content Dashboard oferece uma visão clara de tudo que está na fila para ser publicado. Adiciona um widget no painel que agrupa o conteúdo agendado pelo momento da publicação, uma página completa de calendário editorial onde você pode arrastar posts para outros dias, marca posts que o WordPress não conseguiu publicar no horário e os republica em silêncio para você.

A maioria dos plugins de agendamento acrescenta redes sociais, configurações complexas e upsells de marketing. Este não. Ele mostra o que está agendado, permite reorganizar e mantém a publicação acontecendo. É só isso.

= Recursos =

* **Página completa de calendário editorial** — grade mensal em tela cheia com reagendamento por arrastar e soltar (o horário é preservado)
* **Widget do painel** — conteúdo agendado agrupado por Hoje, Amanhã, Esta semana, Próxima semana e Depois
* **Mini calendário** — alterne o widget para uma grade mensal com pontos nos dias que têm posts agendados
* **Detecção de agendamentos perdidos** — posts travados no status `future` após a data são destacados em vermelho
* **Correção automática de agendamentos perdidos** — republica silenciosamente posts travados nas páginas do admin (a maioria dos concorrentes restringe isso ao plano pago)
* **Botão "Publicar agora" de um clique** — publica manualmente qualquer post perdido direto do widget
* **Contador na barra de administração** — veja quantos posts estão agendados (e se algum foi perdido) de qualquer tela do admin
* **Resumo por e-mail opcional** — resumo diário ou semanal de posts perdidos e próximos para qualquer destinatário
* **API REST** — endpoints para scheduled, missed, counts, publish-now e reschedule
* **Filtros de tipo de post + autor** — refine dentro do widget sem sair do painel
* **Alternância "Apenas meus"** — sites multi-autor podem filtrar o widget pelo conteúdo do usuário atual
* **Rascunhos no widget (opcional)** — mostre rascunhos junto com os agendados
* **Página de configurações** — configure o limite de itens, quais tipos de post incluir, visualização padrão, correção automática e resumo
* **Todos os tipos de post públicos** — posts, páginas, produtos, eventos, tipos personalizados
* **Respeita a privacidade** — sem rastreamento, sem requisições externas, sem cookies

= Casos de uso =

* Equipes editoriais gerenciando um calendário de conteúdo
* Blogueiros agendando posts com antecedência
* Agências cuidando de múltiplos sites de clientes
* Qualquer pessoa frustrada com o WordPress perdendo horários de publicação

= Privacidade =

Este plugin não coleta dados, não envia dados para servidores externos, não usa cookies e não rastreia usuários. Tudo que é exibido já está no seu banco de dados do WordPress.

= Hooks para desenvolvedores =

`scheduled_content_dashboard_query_args` — filtra os argumentos WP_Query usados para a lista de conteúdo agendado.

`scheduled_content_dashboard_auto_fix_missed` — retorne `false` para desativar a publicação automática de posts agendados perdidos.

= API REST =

Namespace base: `scheduled-content-dashboard/v1`. Todos os endpoints exigem usuário logado com a capacidade `edit_posts`.

* `GET /scheduled` — lista posts agendados (parâmetros: `post_type`, `author`, `limit`)
* `GET /missed` — lista posts agendados perdidos
* `GET /counts` — retorna `{ total, scheduled, missed }`
* `POST /publish/{id}` — publica um post agendado agora
* `POST /reschedule/{id}` — muda a data de publicação de um post agendado (body: `date` em qualquer formato que `strtotime()` entenda)

== Installation ==

1. Acesse Plugins > Adicionar novo no admin do WordPress
2. Busque por "Scheduled Content Dashboard"
3. Clique em Instalar agora e depois em Ativar
4. Acesse seu Painel para ver o widget

== Frequently Asked Questions ==

= Onde o widget aparece? =

No painel principal de administração do WordPress. Você pode arrastá-lo para reposicionar entre os outros widgets.

= Quais tipos de conteúdo são exibidos? =

Todos os tipos de post públicos: posts, páginas e qualquer tipo de post personalizado registrado como público (produtos, eventos, portfólios, etc.).

= O que é um "agendamento perdido"? =

O WordPress usa o wp-cron para publicar posts agendados no horário previsto. Se o cron não disparar (sites de baixo tráfego, problemas de cron no servidor, erros fatais), os posts ficam travados no status `future` após a data de publicação. Este plugin detecta esses posts, os destaca e, por padrão, os publica automaticamente na próxima vez que você carrega uma página do admin.

= Como desativar a correção automática? =

Adicione ao `functions.php` do seu tema ou a um mu-plugin:

`add_filter( 'scheduled_content_dashboard_auto_fix_missed', '__return_false' );`

Os posts perdidos continuarão aparecendo no widget com um botão manual de "Publicar agora".

= Quantos itens agendados são mostrados? =

Até 50 por grupo, ordenados pela data agendada (os mais próximos primeiro).

= Funciona com Gutenberg / editor de blocos? =

Sim. O plugin exibe o conteúdo agendado e liga para as telas de edição padrão — funciona com qualquer editor.

= Funciona com Multisite? =

Sim. Cada site tem seu próprio widget mostrando o conteúdo agendado daquele site.

= O widget não aparece — e agora? =

1. Confirme que o plugin está ativado
2. Confirme que há conteúdo agendado (posts com data de publicação futura)
3. No Painel, clique em "Opções de tela" e verifique se "Scheduled Content" está marcado

== Screenshots ==

1. O widget Scheduled Content agrupando posts pelo momento da publicação
2. Detecção de agendamentos perdidos com botão "Publicar agora" para cada post travado
3. Contador na barra de administração mostrando o total de agendados

== Changelog ==

= 2.0.0 =
* Adicionado: página completa de calendário editorial com reagendamento por arrastar e soltar (jQuery UI)
* Adicionado: menu superior "Scheduled" com submenus Calendar e Settings
* Adicionado: API REST (`scheduled-content-dashboard/v1`) com endpoints scheduled, missed, counts, publish, reschedule
* Adicionado: resumo por e-mail opcional (diário ou semanal) às 9h locais, destinatários configuráveis
* Adicionado: link "Open full calendar" no cabeçalho do widget
* Alterado: a desativação limpa corretamente o evento cron do resumo

= 1.2.0 =
* Adicionado: página de configurações (Configurações > Scheduled Content) para limite de itens, tipos de post incluídos, visualização padrão, rascunhos e correção automática
* Adicionado: mini calendário mensal com preferência por usuário, pontos nos dias com conteúdo agendado, destaque de dias perdidos e detalhe do dia
* Adicionado: dropdowns de filtro de tipo de post e autor no widget (recolhíveis)
* Adicionado: grupo opcional de rascunhos ao lado do conteúdo agendado
* Adicionado: seletor de visualização "Lista / Calendário" por usuário
* Alterado: a correção automática agora também respeita o seletor na UI de configurações além do filtro

= 1.1.0 =
* Adicionado: detecção de agendamentos perdidos com destaque em vermelho no widget
* Adicionado: correção automática de posts agendados perdidos (cron no carregamento do admin, filtrável)
* Adicionado: botão "Publicar agora" de um clique para posts perdidos
* Adicionado: contador na barra de administração com total e perdidos
* Adicionado: alternância "Apenas meus" para filtrar o widget pelo usuário atual
* Adicionado: filtro `scheduled_content_dashboard_auto_fix_missed`
* Alterado: a consulta de itens agendados ignora posts perdidos (mostrados em grupo próprio)

= 1.0.0 =
* Lançamento inicial
* Widget do painel com conteúdo agendado agrupado
* Suporte a todos os tipos de post públicos

== Upgrade Notice ==

= 2.0.0 =
Versão principal. Adiciona calendário editorial completo com arrastar e soltar, API REST e resumo por e-mail opcional. O plugin cresce além de "apenas widget do painel" — mas o widget e todos os recursos da 1.x continuam funcionando da mesma forma.

= 1.2.0 =
Adiciona página de configurações, mini calendário, filtros de tipo de post e autor e exibição opcional de rascunhos. Usuários existentes não precisam mudar nada — os padrões correspondem ao comportamento anterior.

= 1.1.0 =
Adiciona detecção de agendamentos perdidos com correção automática gratuita, contador na barra de administração e filtragem por usuário. A correção automática vem ativada por padrão — desative com o filtro scheduled_content_dashboard_auto_fix_missed se preferir controle manual.
