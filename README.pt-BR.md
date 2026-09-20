<p align="center">
  <img src="assets/banner.png" alt="Banner do WordPress Custom Popup Alert" width="100%">
</p>

<h1 align="center">WordPress Custom Popup Alert</h1>

<p align="center">
  Alertas contextuais para WordPress, com regras opcionais para WooCommerce.
</p>

<p align="center">
  <a href="README.md">English</a>
</p>

## Sobre o plugin

O **WordPress Custom Popup Alert (WCCPA)** permite criar avisos, mensagens e alertas em modal que aparecem somente quando o contexto atual do WordPress ou WooCommerce atende às regras configuradas.

A proposta é trabalhar com **comunicação contextual**: avisos de compatibilidade, restrições regionais de entrega, informações no carrinho ou checkout, mensagens promocionais e outros conteúdos que só devem aparecer em situações específicas.

Exemplo simples:

```text
Categoria do produto = Baterias de Moto
```

Exemplo com regras combinadas:

```text
TODAS as condições:
├── Categoria do produto = Baterias
├── Classe de entrega = Local
└── QUALQUER condição:
    ├── Tag do produto = Motocicleta
    └── SKU contém "HONDA"
```

## Principais recursos

- Custom Post Type privado para os alertas.
- Editor tradicional Visual/Texto do WordPress com Biblioteca de Mídia.
- Grupos de regras **E / OU** aninhados.
- Operadores negativos e exclusões.
- Condições do WordPress e WooCommerce.
- Fila sequencial por prioridade.
- Alertas exclusivos capazes de suprimir mensagens de prioridade inferior.
- Controle de frequência no navegador.
- Agendamento opcional por período de validade.
- Modal responsivo e acessível.
- Overlay, dimensões, borda, sombra, barra superior e animações configuráveis.
- Fechamento automático com pausa opcional ao passar o mouse.
- Cinco formas de apresentar a contagem regressiva.
- Botões de ação opcionais.
- Previews em tempo real no painel.
- Pesquisa AJAX para conteúdos, produtos, termos e usuários.
- Duplicação de alertas como rascunho.
- Assets do frontend carregados somente quando necessários.
- Internacionalização pronta e tradução pt-BR incluída.
- WooCommerce opcional: o plugin continua funcionando sem ele.

## Condições do WordPress

Atualmente é possível criar regras por:

- tipo de conteúdo;
- conteúdo específico;
- categoria de post;
- tag de post;
- autor;
- palavra-chave no título, resumo, conteúdo ou nos três campos.

## Condições do WooCommerce

Com o WooCommerce ativo, também ficam disponíveis:

- páginas de Carrinho e Checkout;
- produto específico, pesquisável por nome, ID ou SKU;
- categoria de produto, com opção de incluir subcategorias;
- tag de produto;
- classe de entrega;
- produto em promoção;
- status de estoque;
- tipo de produto;
- SKU com comparação exata, início, final ou conteúdo parcial.

Com o WooCommerce desativado, as regras específicas são preservadas e ignoradas sem gerar erros PHP.

## Frequência de exibição

Cada alerta pode aparecer:

- sempre que as regras forem atendidas;
- uma vez por sessão;
- a cada **X** dias;
- uma única vez, enquanto o registro continuar armazenado no navegador.

A sessão usa `sessionStorage`. Intervalos maiores usam `localStorage`, sem necessidade de sessão PHP ou cadastro de visitantes no banco de dados.

## Prioridade e fila

Se vários alertas forem elegíveis para a mesma página, eles são ordenados por prioridade e exibidos sequencialmente. O plugin nunca sobrepõe vários modais ao mesmo tempo.

Um alerta também pode ser marcado como **exclusivo**, removendo da fila os alertas elegíveis de prioridade inferior.

## Contagem regressiva

O fechamento automático pode funcionar sem indicador visual ou utilizar cinco modos:

1. sem contagem visível;
2. texto discreto no rodapé;
3. barra de progresso inferior;
4. progresso circular no botão fechar;
5. segundos restantes dentro dos botões de fechamento.

Também é possível pausar o fechamento automático enquanto o ponteiro estiver sobre o popup e retomar exatamente do tempo restante.

## Instalação

1. No WordPress, acesse **Plugins → Adicionar novo plugin → Enviar plugin**.
2. Selecione o ZIP do WordPress Custom Popup Alert.
3. Instale e ative o plugin.
4. Acesse **Popup Alerts → Adicionar novo**.
5. Use o título do WordPress como nome interno do alerta.
6. Escreva a mensagem no editor Visual/Texto.
7. Configure pelo menos uma regra de exibição.
8. Publique para ativar o alerta.

> Uma árvore de regras vazia não exibe o alerta por segurança.

## Exemplo: entrega local

Uma loja WooCommerce pode configurar:

```text
Categoria do produto = Baterias
E
Classe de entrega = Local
```

Assim, o aviso de entrega regional aparece apenas nos produtos em que essa informação realmente é necessária.

## Privacidade

O plugin não envia dados de visitantes para serviços externos e não cria uma base de visitantes.

As configurações usam posts e post meta nativos do WordPress. O controle de frequência armazena somente o identificador do alerta e o horário de exibição no navegador do visitante.

## Performance e cache

O contexto é avaliado antes da renderização do frontend. Quando nenhum alerta é elegível, os assets específicos do popup não são carregados.

Como a frequência individual é controlada no navegador, o servidor não precisa gerar uma página diferente para cada visitante, o que favorece compatibilidade com cache de página e CDN.

## Produtos variáveis

Na versão atual, as regras WooCommerce consideram o **produto pai** da página. Regras que mudam dinamicamente após a seleção de uma variação específica ainda não fazem parte da implementação atual.

## Extensibilidade

O motor de regras aceita condições que implementem:

```php
WCCPA\Conditions\Condition
```

Principais hooks:

```text
wccpa_registered_conditions
wccpa_condition_result
wccpa_alert_matches
wccpa_alert_content
wccpa_alerts_before_render
wccpa_alerts_after_render
```

## Desinstalação

Por padrão, excluir o plugin preserva os alertas e configurações.

Para remover os dados durante a desinstalação, defina antes em `wp-config.php`:

```php
define( 'WCCPA_REMOVE_DATA', true );
```

## Requisitos

| Requisito | Mínimo |
|---|---:|
| WordPress | 6.2 |
| PHP | 7.4 |
| WooCommerce | Opcional |

## Contribuições

Issues, correções e pull requests são bem-vindos. Consulte [CONTRIBUTING.md](CONTRIBUTING.md) antes de alterações maiores.

## Changelog

Consulte [CHANGELOG.md](CHANGELOG.md).

## Licença

Copyright © Eduardo Henrique Teixeira.

Distribuído sob a **GNU General Public License v3.0 ou posterior**. Consulte [LICENSE](LICENSE).
