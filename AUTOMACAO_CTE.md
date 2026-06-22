# Automação de download de CT-e

## Objetivo

A automação CT-e executa o mesmo fluxo seguro do **Robô CT-e até último NSU**, mas em segundo plano, em intervalos configuráveis. Os XMLs completos recebidos são salvos automaticamente na estrutura de pastas configurada no portal.

## Como configurar

1. Acesse **Configurações**.
2. Em **Automação CT-e**, marque **Ativar robô automático de CT-e**.
3. Escolha a empresa da automação.
   - Para testes, prefira uma empresa específica.
   - Use “Todos os CNPJs ativos” somente quando todos tiverem certificado correto.
4. Defina o intervalo em minutos.
   - O sistema aplica mínimo operacional de 5 minutos.
5. Ajuste os limites do robô:
   - **Ciclos máximos por execução**
   - **Tempo máximo por execução**
6. Salve.

## Como iniciar o worker no Docker

O `docker-compose.yml` possui o serviço:

```bash
docker compose up -d cte_worker
```

Para reiniciar após alterações:

```bash
docker compose up -d --build cte_worker
```

Para ver logs:

```bash
docker compose logs -f cte_worker
```

Além do log do Docker, o portal grava detalhes em:

```text
app/storage/logs/auto_cte_worker.log
```

## Como funciona

- O worker lê as configurações salvas no portal.
- Se a automação estiver desativada, ele aguarda e não executa coletas.
- Se estiver ativa, ele executa `cte_until_max` para a empresa configurada.
- O robô avança o `ultNSU` em ciclos.
- Eventos retornados na distribuição, como `procEventoCTe`, são ignorados e não entram na base de documentos do ERP.
- Resumos `resCTe`, quando retornados, são salvos na base como **apenas resumo**, mas não são tratados como XML final de ERP.
- Ele para quando:
  - alcança `maxNSU`;
  - não há avanço de NSU;
  - ocorre erro;
  - atinge o limite de tempo seguro;
  - atinge o limite de ciclos por execução.
- Um lock local evita execução concorrente.

## Pasta dos XMLs

Os XMLs completos são salvos pela rotina atual de armazenamento do portal. A pasta é definida em **Configurações > Armazenamento global**:

- pasta base;
- modo segmentado;
- template personalizado.

Exemplo padrão:

```text
{base}/{cnpj}/{doc_type}/{year}/{month}
```

## Cuidados importantes

- Comece testando somente uma empresa.
- Não use empresa cujo certificado digital seja de outro CNPJ-base.
- NF-e e NFS-e não foram automatizados nesta etapa.
- A distribuição DF-e continua sendo por NSU, não por data.
- Para conferir um período, use **Documentos** e **Radar por Período** após a automação avançar a fila.
