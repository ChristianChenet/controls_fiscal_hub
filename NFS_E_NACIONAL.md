# NFS-e Nacional no portal

## O que foi ajustado

- O conector de NFS-e Nacional passou a usar o formato atual da API de contribuintes do ADN: `GET /contribuintes/DFe/{nsu}`.
- A URL antiga `/contribuintes/api/v1/distribuicao` foi mantida apenas como compatibilidade: se ela estiver salva nas configuracoes, o portal redireciona internamente para o endpoint atual.
- A chamada HTTPS agora usa o certificado da empresa selecionada, nao um certificado ativo generico.
- A leitura da resposta aceita XML direto, JSON com XML, ou XML compactado em GZIP/Base64 quando retornado pelo servico.
- Os documentos importados pela API passam a ser vinculados corretamente a empresa no banco.
- A coleta passou a consultar NSU a NSU, com limite configurado por execucao, porque a API do ADN retorna o DF-e correspondente ao NSU informado, e nao uma pagina por periodo como NF-e/CT-e.
- Se o ADN retornar HTTP 429, o portal grava um bloqueio local de 1 hora para evitar novas requisicoes em excesso.

## Como configurar

Na tela **Configuracoes**, em **NFS-e Nacional**:

- **Base URL ADN**: `https://adn.nfse.gov.br`
- **Path de distribuicao**: `/contribuintes/DFe/{nsu}`
- **Autenticacao**: normalmente `Certificado`

O portal acrescenta o parametro `cnpj` na consulta para permitir consulta por CNPJ com a mesma raiz do certificado, conforme comportamento atual do ADN.

O controle local `nfse_{empresa}_ult_nsu` guarda o ultimo NSU consultado. Em cada execucao, o portal consulta os proximos NSUs ate o limite configurado e registra no log quais NSUs foram verificados.

## Como testar

1. Confirme se a empresa tem certificado A1 ativo na tela **Empresas**.
2. Acesse **Radar de XML**.
3. Selecione a empresa desejada.
4. Em **Rotina**, escolha **Coletar NFS-e Nacional**.
5. Execute a rotina.

Se o servico retornar 401/403, o problema tende a ser autorizacao/certificado. Se retornar 404, confira se a base URL e o path estao exatamente como acima.

Quando a rotina retornar zero itens, confira o log `storage/logs/collector_nfse.log`. Ele mostra o intervalo de NSUs consultado e um resumo das primeiras respostas sem XML reconhecido.

Se aparecer bloqueio por excesso de requisicoes, aguarde o horario informado pelo portal antes de executar novamente.

## Automacao

A automacao de NFS-e Nacional fica em **Configuracoes > Automacao NFS-e Nacional**.

Campos:

- **Ativar robo automatico de NFS-e Nacional**: liga ou desliga o worker.
- **Empresa da automacao NFS-e**: permite escolher uma empresa especifica ou todos os CNPJs ativos.
- **Intervalo entre execucoes automaticas NFS-e**: o worker aplica minimo operacional de 60 minutos.
- **NSUs por execucao NFS-e**: limita quantos NSUs serao consultados em cada ciclo. O padrao conservador e 10.

O worker Docker e `nfse_worker` e registra as execucoes como jobs do tipo `nfse`. O historico aparece em **Configuracoes > Historico das automacoes**.

Por causa do comportamento do ADN, se houver HTTP 429 o portal grava cooldown local e interrompe novas tentativas ate o horario informado.

## Limitacoes reais

A NFS-e Nacional nao funciona como NF-e/CT-e da SEFAZ. A disponibilidade depende do Ambiente de Dados Nacional, do municipio, do tipo de participacao da empresa no documento e da autorizacao do certificado usado.

O portal consulta o ADN por NSU, mas isso nao garante que toda NFS-e municipal existente esteja disponivel no padrao nacional. Para fechamento operacional, use a tela **Documentos** e o **Radar por Periodo** para conferir o que entrou na base antes de exportar ao ERP.
