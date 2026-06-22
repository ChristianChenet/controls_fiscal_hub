# Automação de download de NF-e / NFC-e

## Objetivo

A automação NF-e / NFC-e executa a distribuição por NSU em segundo plano e salva os XMLs completos na estrutura de pastas configurada no portal.

## Rotinas manuais no Radar de XML

Na tela **Radar de XML**, foram adicionadas:

- **Robô NF-e / NFC-e até último NSU**
- **Robô NF-e + ciência da operação**

A segunda rotina, além de avançar o NSU, envia **Ciência da Operação** para NF-e pendente que veio apenas como resumo. Após a ciência, a NF-e fica como **aguardando novo download** e deve ser baixada em uma execução posterior.

## Resumos e XML completo

- `resNFe` é salvo na base como **apenas resumo** para permitir manifestação.
- O resumo não é salvo como XML final de ERP.
- Quando o XML completo chega depois, ele substitui o estado pendente e é salvo na pasta configurada.
- Se um resumo chegar depois de um XML completo já existente, ele é ignorado para não rebaixar o documento.
- Eventos continuam sendo ignorados.

## Automação em segundo plano

Em **Configurações > Automação NF-e / NFC-e**:

1. Ative o robô automático.
2. Escolha uma empresa específica para testes.
3. Marque ou não a opção de enviar ciência da operação.
4. Configure o intervalo.
5. Configure ciclos, tempo máximo e limite de ciência por execução.

O intervalo mínimo operacional do worker é de **60 minutos**, para reduzir risco de consumo indevido.

## Worker Docker

Serviço adicionado:

```bash
docker compose up -d nfe_worker
```

Logs:

```bash
docker compose logs -f nfe_worker
```

Log interno:

```text
app/storage/logs/auto_nfe_worker.log
```

## Cuidados fiscais

- A distribuição NF-e não possui filtro por data; a consulta avança por NSU.
- A SEFAZ pode retornar consumo indevido se houver repetição inadequada.
- A ciência da operação é um evento fiscal real.
- NF-e de destinatário pode vir primeiro como resumo.
- Após ciência, o XML completo pode ficar disponível apenas em consulta posterior.
- Comece com uma empresa específica, nunca em todos os CNPJs no primeiro teste.
- Eventos retornados pela distribuição, como `resEvento` e `procEventoNFe`, são ignorados e não entram na base de documentos do ERP.
