WITH PARAMETROS AS (
    SELECT
        -- Data de entrada parametrizada. Para esta importacao antiga usar 2026-08-28; na rotina normal altere apenas no no de parametros.
        DATE '{{ $("Configura Parametros NFSe Decis").item.json.dataEntradaDecis }}' AS DATA_ENTRADA_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.usuarioDecis || 9980) }}::INTEGER AS USUARIO_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.empresaDecis || 1) }}::INTEGER AS EMPRESA_DECIS,
        '{{ String($("Configura Parametros NFSe Decis").item.json.entradaSaida || "E").replaceAll("'", "''") }}'::TEXT AS ENTRADA_SAIDA_DECIS,
        '{{ String($("Configura Parametros NFSe Decis").item.json.natureza || "Prestacao de Servico").replaceAll("'", "''") }}'::TEXT AS NATUREZA_DECIS,
        '{{ String($("Configura Parametros NFSe Decis").item.json.modeloDocumento || "01").replaceAll("'", "''") }}'::TEXT AS MODELO_DOCUMENTO_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.servico || 0) }}::INTEGER AS SERVICO_DECIS,
        {{ $("Configura Parametros NFSe Decis").item.json.codigoPadrao === null || $("Configura Parametros NFSe Decis").item.json.codigoPadrao === "" ? "NULL" : Number($("Configura Parametros NFSe Decis").item.json.codigoPadrao) }}::INTEGER AS CODIGO_PADRAO_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.contaContabil || 13073) }}::INTEGER AS CONTA_CONTABIL_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.sequenciaServico || 1) }}::INTEGER AS SEQUENCIA_SERVICO_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.tipoFrete || 1) }}::INTEGER AS TIPO_FRETE_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.formaPgto || 1) }}::INTEGER AS FORMA_PGTO_DECIS,
        '{{ String($("Configura Parametros NFSe Decis").item.json.flagFinanceiro || "P").replaceAll("'", "''") }}'::TEXT AS FLAG_FINANCEIRO_DECIS,
        '{{ String($("Configura Parametros NFSe Decis").item.json.flagCancelado || "N").replaceAll("'", "''") }}'::TEXT AS FLAG_CANCELADO_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.flagFreteIncluiValorTotal || 0) }}::INTEGER AS FLAG_FRETE_INCLUI_TOTAL_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.flagPerfilPessoa || 1) }}::INTEGER AS FLAG_PERFIL_PESSOA_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.flagIndicadorPresenca || 9) }}::INTEGER AS FLAG_INDICADOR_PRESENCA_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.tipoInscricaoEstadual || 9) }}::INTEGER AS TIPO_INSCRICAO_ESTADUAL_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.vendedorPadrao || -1) }}::INTEGER AS VENDEDOR_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.naturezaCredito || 3) }}::INTEGER AS NATUREZA_CREDITO_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.cstCofins || 74) }}::INTEGER AS CST_COFINS_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.cstPis || 74) }}::INTEGER AS CST_PIS_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.codigoPadrao2 || 410) }}::INTEGER AS CODIGO_PADRAO2_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.cstCbs || 410) }}::INTEGER AS CST_CBS_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.cstClassificacaoCbs || 410999) }}::INTEGER AS CST_CLASSIFICACAO_CBS_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.aliquotaIbsIntegral || 0.1) }}::NUMERIC AS ALIQUOTA_IBS_INTEGRAL_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.reducaoAliquotaIbs || 100) }}::NUMERIC AS REDUCAO_ALIQUOTA_IBS_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.aliquotaCbsIntegral || 0.9) }}::NUMERIC AS ALIQUOTA_CBS_INTEGRAL_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.reducaoAliquotaCbs || 100) }}::NUMERIC AS REDUCAO_ALIQUOTA_CBS_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.pessoaEstado || 1) }}::INTEGER AS PESSOA_ESTADO_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.pessoaFisicoJuridico || 2) }}::INTEGER AS PESSOA_FISICO_JURIDICO_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.pessoaFilial || 1) }}::INTEGER AS PESSOA_FILIAL_DECIS,
        {{ Number($("Configura Parametros NFSe Decis").item.json.pessoaTipoEmpresa || 1) }}::INTEGER AS PESSOA_TIPO_EMPRESA_DECIS,
        ARRAY[
            '05102155000586',
            '05102155000233',
            '05102155000403',
            '05102155000667',
            '05102155000152'
        ]::TEXT[] AS CNPJS_EMPRESAS
),
DOCS AS (
    SELECT
        D.*,
        REGEXP_REPLACE(COALESCE(D.raw_xml, ''), '\s+', ' ', 'g') AS raw_xml_flat
    FROM documents D
),
FORNECEDORES_PORTAL AS (
    SELECT
        REGEXP_REPLACE(COALESCE(F.documento, ''), '[^0-9]', '', 'g') AS documento_limpo,
        MAX(F.id)::INTEGER AS id_fornecedor
    FROM fornecedores F
    WHERE REGEXP_REPLACE(COALESCE(F.documento, ''), '[^0-9]', '', 'g') <> ''
    GROUP BY REGEXP_REPLACE(COALESCE(F.documento, ''), '[^0-9]', '', 'g')
),
BASE AS (
    SELECT
        D.*,
        REGEXP_REPLACE(COALESCE(D.company_cnpj, ''), '[^0-9]', '', 'g') AS company_cnpj_limpo,
        REGEXP_REPLACE(COALESCE(D.issuer_cnpj, ''), '[^0-9]', '', 'g') AS issuer_cnpj_limpo,
        REGEXP_REPLACE(COALESCE(D.recipient_cnpj, ''), '[^0-9]', '', 'g') AS recipient_cnpj_limpo,
        FP.id_fornecedor,
        CASE REGEXP_REPLACE(COALESCE(D.company_cnpj, ''), '[^0-9]', '', 'g')
            WHEN '05102155000152' THEN 1
            WHEN '05102155000233' THEN 5
            WHEN '05102155000403' THEN 8
            WHEN '05102155000586' THEN 11
            WHEN '05102155000667' THEN 16
            ELSE NULL
        END AS filial_decis,
        COALESCE(
            NULLIF((
                SELECT DI.cfop
                FROM document_items DI
                WHERE DI.document_id = D.id
                  AND COALESCE(DI.cfop, '') <> ''
                ORDER BY DI.item_number ASC, DI.id ASC
                LIMIT 1
            ), ''),
            CASE
                WHEN D.doc_type = 'NFSE'
                 AND UPPER(TRIM(COALESCE(NULLIF(D.issuer_uf, ''), NULLIF(D.service_uf, ''), ''))) <> ''
                THEN
                    CASE
                        WHEN UPPER(TRIM(COALESCE(NULLIF(D.issuer_uf, ''), NULLIF(D.service_uf, ''), ''))) =
                             CASE
                                 WHEN REGEXP_REPLACE(COALESCE(D.company_cnpj, ''), '[^0-9]', '', 'g')
                                      IN ('05102155000152', '05102155000233', '05102155000403', '05102155000667')
                                 THEN 'PR'
                                 WHEN REGEXP_REPLACE(COALESCE(D.company_cnpj, ''), '[^0-9]', '', 'g') = '05102155000586'
                                 THEN 'MS'
                                 ELSE ''
                             END
                        THEN '1933'
                        ELSE '2933'
                    END
                ELSE ''
            END
        ) AS cfop_calculado,
        COALESCE(
            NULLIF(substring(D.raw_xml_flat FROM '<emit>.*?<cMun>([0-9]{7})</cMun>'), ''),
            NULLIF(substring(D.raw_xml_flat FROM '<prest>.*?<cMun>([0-9]{7})</cMun>'), ''),
            NULLIF(substring(D.raw_xml_flat FROM '<EnderecoPrestador>.*?<Cidade>([0-9]{7})</Cidade>'), ''),
            NULLIF(substring(D.raw_xml_flat FROM '<CodigoMunicipioPrestador>([0-9]{7})</CodigoMunicipioPrestador>'), '')
        ) AS codigo_municipio_fornecedor_xml,
        COALESCE(
            CASE
                WHEN COALESCE(D.service_city, '') ~ '^[0-9]{7}$' THEN D.service_city
                ELSE NULL
            END,
            NULLIF(substring(D.raw_xml_flat FROM '<cLocIncid>([0-9]{7})</cLocIncid>'), ''),
            NULLIF(substring(D.raw_xml_flat FROM '<cLocPrestacao>([0-9]{7})</cLocPrestacao>'), ''),
            NULLIF(substring(D.raw_xml_flat FROM '<CodigoMunicipioOcorrencia>([0-9]{7})</CodigoMunicipioOcorrencia>'), '')
        ) AS codigo_municipio_servico_xml
    FROM DOCS D
    CROSS JOIN PARAMETROS P
    LEFT JOIN FORNECEDORES_PORTAL FP
      ON FP.documento_limpo = REGEXP_REPLACE(COALESCE(D.issuer_cnpj, ''), '[^0-9]', '', 'g')
)
SELECT
    B.id AS "ID",
    B.company_name AS "EMPRESA",
    B.company_cnpj AS "CNPJ_EMPRESA",
    B.company_cnpj_limpo AS "CNPJ_EMPRESA_LIMPO",
    B.doc_type AS "TIPO",
    B.model AS "MODELO",
    B.number AS "NUMERO",
    B.access_key AS "CHAVE",
    B.order_number AS "PEDIDO",

    B.id_fornecedor AS "ID_FORNECEDOR",
    B.issuer_name AS "FORNECEDOR",
    B.issuer_cnpj AS "CNPJ_FORNECEDOR",
    B.issuer_cnpj_limpo AS "CNPJ_FORNECEDOR_LIMPO",
    B.issuer_city AS "CIDADE_FORNECEDOR",
    B.issuer_uf AS "UF_FORNECEDOR",

    B.recipient_name AS "TOMADOR",
    B.recipient_cnpj AS "CNPJ_TOMADOR",
    B.recipient_cnpj_limpo AS "CNPJ_TOMADOR_LIMPO",

    B.issue_date AS "DATA_EMISSAO",
    B.total_value AS "VALOR",
    CASE WHEN COALESCE(B.posted_to_erp, FALSE) THEN 'SIM' ELSE 'NAO' END AS "LANCADA_ERP",
    B.entrada_date_erp AS "DATA_ENTRADA_ERP",
    CASE WHEN COALESCE(B.accounting_posted, 'N') = 'S' THEN 'SIM' ELSE 'NAO' END AS "LANCADA_CONTABILIDADE",

    B.status AS "STATUS",
    B.manifestation_status AS "MANIFESTACAO",
    B.source AS "ORIGEM",

    B.service_series AS "SERIE",
    B.service_dps_number AS "DPS",
    B.service_dps_series AS "SERIE_DPS",
    B.service_verification_code AS "CODIGO_VERIFICACAO",
    B.service_code AS "CODIGO_SERVICO",
    B.service_description AS "DESCRICAO",
    B.service_city AS "CIDADE_SERVICO",
    B.service_uf AS "UF_SERVICO",
    B.cfop_calculado AS "CFOP",

    B.iss_rate AS "ALIQUOTA",
    B.iss_amount AS "ISS",
    B.pis_amount AS "PIS",
    B.cofins_amount AS "COFINS",
    B.deductions_amount AS "DEDUCOES",
    B.discount_amount AS "DESCONTO",
    B.net_amount AS "VALOR_LIQUIDO",
    B.fiscal_observation AS "OBSERVACAO",

    B.referenced_nfe_keys AS "CHAVES_REFERENCIADAS",
    B.referenced_document_numbers AS "NUMEROS_REFERENCIADOS",
    B.schema_name AS "SCHEMA",
    B.digest AS "HASH_XML",
    B.xml_path AS "CAMINHO_XML",
    B.storage_dir AS "PASTA",
    B.notes AS "NOTAS",
    B.imported_at AS "IMPORTADO_EM",
    B.updated_at AS "ATUALIZADO_EM",
    B.raw_xml AS "XML",

    -- Campos finais preparados para o De >> Para / gravacao no Decis.
    P.EMPRESA_DECIS AS "DECIS_EMPRESA",
    B.filial_decis AS "DECIS_FILIAL",
    P.ENTRADA_SAIDA_DECIS AS "DECIS_ENTRADA_SAIDA",
    B.id_fornecedor AS "DECIS_PESSOA",
    B.number AS "DECIS_NOTA_FISCAL",
    COALESCE(NULLIF(B.service_series, ''), NULLIF(B.service_dps_series, ''), 'E') AS "DECIS_SERIE",
    NULLIF(REGEXP_REPLACE(COALESCE(B.cfop_calculado, ''), '[^0-9]', '', 'g'), '')::INTEGER AS "DECIS_MOVIMENTACAO_FISCAL",
    P.DATA_ENTRADA_DECIS AS "DECIS_DATA_ENTRADA",
    P.DATA_ENTRADA_DECIS AS "DECIS_DT_INCLUSAO",
    P.USUARIO_DECIS AS "DECIS_USUARIO",
    P.NATUREZA_DECIS AS "DECIS_NATUREZA",
    P.MODELO_DOCUMENTO_DECIS AS "DECIS_MODELO_DOCUMENTO",
    COALESCE(NULLIF(B.service_description, ''), NULLIF(B.fiscal_observation, ''), 'SERVICO NFS-E') AS "DECIS_DESCRICAO_SERVICO",
    P.SERVICO_DECIS AS "DECIS_SERVICO",
    P.CODIGO_PADRAO_DECIS AS "DECIS_CODIGO_PADRAO",
    P.CONTA_CONTABIL_DECIS AS "DECIS_CONTA_CONTABIL",
    NULLIF(B.codigo_municipio_fornecedor_xml, '')::INTEGER AS "DECIS_CODIGO_MUNICIPIO_FORNECEDOR",
    NULLIF(B.codigo_municipio_servico_xml, '')::INTEGER AS "DECIS_CODIGO_MUNICIPIO_SERVICO",
    P.SEQUENCIA_SERVICO_DECIS AS "DECIS_SEQUENCIA_SERVICO",
    B.fiscal_observation AS "DECIS_OBSERVACAO",

    -- VDNOTAC - cabecalho da nota.
    P.EMPRESA_DECIS AS "VDNOTAC_EMPRESA",
    B.filial_decis AS "VDNOTAC_FILIAL",
    P.ENTRADA_SAIDA_DECIS AS "VDNOTAC_ENTRADA_SAIDA",
    B.id_fornecedor AS "VDNOTAC_PESSOA",
    B.number AS "VDNOTAC_NOTA_FISCAL",
    COALESCE(NULLIF(B.service_series, ''), NULLIF(B.service_dps_series, ''), 'E') AS "VDNOTAC_SERIE",
    NULLIF(REGEXP_REPLACE(COALESCE(B.cfop_calculado, ''), '[^0-9]', '', 'g'), '')::INTEGER AS "VDNOTAC_MOVIMENTACAO_FISCAL",
    B.issue_date AS "VDNOTAC_DATA_EMISSAO",
    P.DATA_ENTRADA_DECIS AS "VDNOTAC_DATA_MOVIMENTACAO",
    COALESCE(B.net_amount, B.total_value, 0) AS "VDNOTAC_VALOR_SERVICO",
    0 AS "VDNOTAC_VALOR_PRODUTO",
    COALESCE(B.discount_amount, 0) AS "VDNOTAC_VALOR_DESCONTO",
    0 AS "VDNOTAC_VALOR_DESCONTO_ITEM",
    0 AS "VDNOTAC_VALOR_OUTRAS_DESPESAS",
    0 AS "VDNOTAC_VALOR_SEGURO",
    0 AS "VDNOTAC_QUANTIDADE_PECAS",
    COALESCE(B.total_value, 0) AS "VDNOTAC_VALOR_TOTAL",
    0 AS "VDNOTAC_VALOR_BASE_ICMS",
    0 AS "VDNOTAC_VALOR_ISENTO_ICMS",
    0 AS "VDNOTAC_VALOR_OUTROS_ICMS",
    0 AS "VDNOTAC_ALIQUOTA_ICMS",
    0 AS "VDNOTAC_VALOR_ICMS",
    0 AS "VDNOTAC_VALOR_BASE_SUBS_TRIB",
    0 AS "VDNOTAC_VALOR_SUBS_TRIB",
    0 AS "VDNOTAC_VALOR_BASE_IPI",
    0 AS "VDNOTAC_VALOR_IPI",
    COALESCE(B.net_amount, B.total_value, 0) AS "VDNOTAC_VALOR_BASE_ISS",
    COALESCE(B.iss_amount, 0) AS "VDNOTAC_VALOR_ISS",
    COALESCE(B.iss_rate, 0) AS "VDNOTAC_ALIQUOTA_ISS",
    P.TIPO_FRETE_DECIS AS "VDNOTAC_TIPO_FRETE",
    0 AS "VDNOTAC_VALOR_FRETE",
    P.FORMA_PGTO_DECIS AS "VDNOTAC_FORMA_PGTO",
    0 AS "VDNOTAC_PESO_BRUTO",
    0 AS "VDNOTAC_PESO_LIQUIDO",
    P.DATA_ENTRADA_DECIS AS "VDNOTAC_DATA_INCLUSAO",
    P.USUARIO_DECIS AS "VDNOTAC_USUARIO_INCLUSAO",
    P.DATA_ENTRADA_DECIS AS "VDNOTAC_DATA_ALTERACAO",
    P.USUARIO_DECIS AS "VDNOTAC_USUARIO_ALTERACAO",
    COALESCE(B.issuer_city, B.service_city) AS "VDNOTAC_MUNICIPIO",
    B.issuer_uf AS "VDNOTAC_UF",
    B.issuer_cnpj_limpo AS "VDNOTAC_CNPJ",
    P.NATUREZA_DECIS AS "VDNOTAC_NATUREZA",
    P.FLAG_FINANCEIRO_DECIS AS "VDNOTAC_FLAG_FINANCEIRO",
    P.FLAG_CANCELADO_DECIS AS "VDNOTAC_FLAG_CANCELADO",
    B.order_number AS "VDNOTAC_PEDIDO",
    B.issuer_name AS "VDNOTAC_NOMEPESSOA",
    B.fiscal_observation AS "VDNOTAC_OBSERVACAO",
    CONCAT_WS(
        ' | ',
        'ORIGEM=' || NULLIF(B.source, ''),
        'SCHEMA=' || NULLIF(B.schema_name, ''),
        'CHAVE=' || NULLIF(B.access_key, ''),
        'COD_VERIFICACAO=' || NULLIF(B.service_verification_code, ''),
        'HASH=' || NULLIF(B.digest, ''),
        'XML_PATH=' || NULLIF(B.xml_path, ''),
        'PASTA=' || NULLIF(B.storage_dir, ''),
        'NOTAS=' || NULLIF(B.notes, '')
    ) AS "VDNOTAC_DADOS_ADICIONAIS",
    NULLIF(B.codigo_municipio_servico_xml, '')::INTEGER AS "VDNOTAC_CODIGOMUNICIPIO",
    B.access_key AS "VDNOTAC_CODIGOACESSO",
    P.FLAG_FRETE_INCLUI_TOTAL_DECIS AS "VDNOTAC_FLAGFRETEINCLUIVALORTOTAL",
    P.MODELO_DOCUMENTO_DECIS AS "VDNOTAC_MODELODOCUMENTO",
    0 AS "VDNOTAC_VALORTOTALTRIBUTACAO",
    P.FLAG_PERFIL_PESSOA_DECIS AS "VDNOTAC_FLAGPERFILPESSOA",
    P.FLAG_INDICADOR_PRESENCA_DECIS AS "VDNOTAC_FLAGINDICADORPRESENCA",
    P.TIPO_INSCRICAO_ESTADUAL_DECIS AS "VDNOTAC_TIPOINSCRICAOESTADUAL",
    0 AS "VDNOTAC_MUNICIPIOORIGEM",
    0 AS "VDNOTAC_MUNICIPIODESTINO",

    -- VDNOTAS - item/servico da NFS-e.
    P.EMPRESA_DECIS AS "VDNOTAS_EMPRESA",
    B.filial_decis AS "VDNOTAS_FILIAL",
    P.ENTRADA_SAIDA_DECIS AS "VDNOTAS_ENTRADA_SAIDA",
    B.id_fornecedor AS "VDNOTAS_PESSOA",
    B.number AS "VDNOTAS_NOTA_FISCAL",
    COALESCE(NULLIF(B.service_series, ''), NULLIF(B.service_dps_series, ''), 'E') AS "VDNOTAS_SERIE",
    P.SEQUENCIA_SERVICO_DECIS AS "VDNOTAS_SEQUENCIA",
    P.SERVICO_DECIS AS "VDNOTAS_SERVICO",
    COALESCE(NULLIF(B.service_description, ''), NULLIF(B.fiscal_observation, ''), 'SERVICO NFS-E') AS "VDNOTAS_DESCRICAO",
    1 AS "VDNOTAS_QUANTIDADE",
    COALESCE(B.total_value, 0) AS "VDNOTAS_VALOR_TOTAL",
    COALESCE(B.discount_amount, 0) AS "VDNOTAS_VALOR_DESCONTO",
    0 AS "VDNOTAS_PERC_DESCONTO",
    COALESCE(B.iss_rate, 0) AS "VDNOTAS_ALIQUOTA_ISS",
    COALESCE(B.iss_amount, 0) AS "VDNOTAS_VALOR_ISS",
    P.VENDEDOR_DECIS AS "VDNOTAS_VENDEDOR",
    P.DATA_ENTRADA_DECIS AS "VDNOTAS_DATA_INCLUSAO",
    P.USUARIO_DECIS AS "VDNOTAS_USUARIO_INCLUSAO",
    P.DATA_ENTRADA_DECIS AS "VDNOTAS_DATA_ALTERACAO",
    P.USUARIO_DECIS AS "VDNOTAS_USUARIO_ALTERACAO",
    NULLIF(REGEXP_REPLACE(COALESCE(B.cfop_calculado, ''), '[^0-9]', '', 'g'), '')::INTEGER AS "VDNOTAS_MOVIMENTACAOFISCAL",
    0 AS "VDNOTAS_ALIQUOTAICMS",
    0 AS "VDNOTAS_VALORBASEICMS",
    0 AS "VDNOTAS_VALORICMS",
    CASE
        WHEN LENGTH(NULLIF(REGEXP_REPLACE(COALESCE(B.service_code, ''), '[^0-9]', '', 'g'), '')) <= 9
        THEN NULLIF(REGEXP_REPLACE(COALESCE(B.service_code, ''), '[^0-9]', '', 'g'), '')::INTEGER
        ELSE NULL
    END AS "VDNOTAS_CODIGOFEDERALSERVICO",
    NULLIF(B.codigo_municipio_servico_xml, '')::INTEGER AS "VDNOTAS_CODIGOMUNICIPIOOCORRENCIA",
    P.NATUREZA_CREDITO_DECIS AS "VDNOTAS_NATUREZACREDITO",
    P.CST_COFINS_DECIS AS "VDNOTAS_CSTCOFINS",
    COALESCE(B.cofins_amount, 0) AS "VDNOTAS_VALORCOFINS",
    P.CST_PIS_DECIS AS "VDNOTAS_CSTPIS",
    COALESCE(B.pis_amount, 0) AS "VDNOTAS_VALORPIS",
    0 AS "VDNOTAS_VALORTOTALTRIBUTACAO",
    COALESCE(B.deductions_amount, 0) AS "VDNOTAS_VALORDEDUCAO",
    P.CODIGO_PADRAO_DECIS AS "VDNOTAS_CODIGOPADRAO",
    B.fiscal_observation AS "VDNOTAS_DESCRICAOCOMPLETA",
    P.CONTA_CONTABIL_DECIS AS "VDNOTAS_CONTACONTABIL",
    P.CODIGO_PADRAO2_DECIS AS "VDNOTAS_CODIGOPADRAO2",
    P.CST_CBS_DECIS AS "VDNOTAS_CSTCBS",
    0 AS "VDNOTAS_BASECALCULOCBS",
    0 AS "VDNOTAS_ALIQUOTACBS",
    0 AS "VDNOTAS_VALORCBS",
    0 AS "VDNOTAS_BASECALCULOIBS",
    0 AS "VDNOTAS_ALIQUOTAIBS",
    0 AS "VDNOTAS_VALORIBS",
    COALESCE(B.total_value, 0) AS "VDNOTAS_BASECALCULOIS",
    0 AS "VDNOTAS_ALIQUOTAIS",
    0 AS "VDNOTAS_VALORIS",
    P.CST_CLASSIFICACAO_CBS_DECIS AS "VDNOTAS_CSTCLASSIFICACAOCBS",
    COALESCE(B.total_value, 0) AS "VDNOTAS_BASECALCULOIBSMUNICIPIO",
    0 AS "VDNOTAS_ALIQUOTAIBSMUNICIPIO",
    0 AS "VDNOTAS_VALORIBSMUNICIPIO",
    P.ALIQUOTA_IBS_INTEGRAL_DECIS AS "VDNOTAS_ALIQUOTAIBSINTEGRAL",
    P.REDUCAO_ALIQUOTA_IBS_DECIS AS "VDNOTAS_REDUCAOALIQUOTAIBS",
    P.ALIQUOTA_CBS_INTEGRAL_DECIS AS "VDNOTAS_ALIQUOTACBSINTEGRAL",
    P.REDUCAO_ALIQUOTA_CBS_DECIS AS "VDNOTAS_REDUCAOALIQUOTACBS",

    -- CGPESSOA - fornecedor/prestador, quando ainda nao existir no Decis.
    B.id_fornecedor AS "CGPESSOA_PESSOA",
    B.issuer_name AS "CGPESSOA_NOME",
    P.EMPRESA_DECIS AS "CGPESSOA_EMPRESA",
    P.PESSOA_ESTADO_DECIS AS "CGPESSOA_ESTADO",
    P.PESSOA_FISICO_JURIDICO_DECIS AS "CGPESSOA_FISICO_JURIDICO",
    P.PESSOA_FILIAL_DECIS AS "CGPESSOA_FILIAL",
    P.DATA_ENTRADA_DECIS AS "CGPESSOA_DATA_ALTERACAO",
    P.USUARIO_DECIS AS "CGPESSOA_USUARIO_ALTERACAO",
    P.DATA_ENTRADA_DECIS AS "CGPESSOA_DATAHORAINCLUSAO",
    P.USUARIO_DECIS AS "CGPESSOA_USUARIOINCLUSAO",
    P.PESSOA_TIPO_EMPRESA_DECIS AS "CGPESSOA_TIPOEMPRESA",
    B.issuer_cnpj_limpo AS "CGPESSOA_CNPJ",
    COALESCE(B.issuer_city, B.service_city) AS "CGPESSOA_MUNICIPIO",
    B.issuer_uf AS "CGPESSOA_UF",
    NULLIF(B.codigo_municipio_fornecedor_xml, '')::INTEGER AS "CGPESSOA_CODIGOMUNICIPIO",

    -- Conteudo sem coluna direta nas tabelas anexadas, mantido para auditoria/integracao complementar.
    B.raw_xml AS "DECIS_XML_CONTEUDO",
    B.xml_path AS "DECIS_XML_CAMINHO",
    B.digest AS "DECIS_HASH_XML",
    B.source AS "DECIS_ORIGEM_PORTAL",
    B.schema_name AS "DECIS_SCHEMA_PORTAL",

    CASE WHEN B.id_fornecedor IS NULL THEN 'SIM' ELSE 'NAO' END AS "PRECISA_CADASTRAR_FORNECEDOR",
    B.issuer_name AS "PESSOA_NOME",
    B.issuer_cnpj_limpo AS "PESSOA_CNPJ",
    B.issuer_city AS "PESSOA_CIDADE",
    B.issuer_uf AS "PESSOA_UF",
    NULLIF(B.codigo_municipio_fornecedor_xml, '')::INTEGER AS "PESSOA_CODIGO_MUNICIPIO",
    P.PESSOA_FISICO_JURIDICO_DECIS AS "PESSOA_FISICO_JURIDICO",
    P.PESSOA_ESTADO_DECIS AS "PESSOA_ESTADO",
    P.PESSOA_FILIAL_DECIS AS "PESSOA_FILIAL",

    CONCAT_WS(
        ' | ',
        CASE WHEN B.id_fornecedor IS NULL THEN 'Cadastrar fornecedor no Decis/CGPESSOA e preencher ID_FORNECEDOR/DECIS_PESSOA.' END,
        CASE WHEN B.filial_decis IS NULL THEN 'Preencher o parametro de filial Decis para este CNPJ da empresa antes de integrar.' END,
        CASE WHEN B.id_fornecedor IS NULL THEN 'Depois de cadastrar o fornecedor, retornar o codigo para ID_FORNECEDOR/DECIS_PESSOA.' END,
        CASE WHEN B.codigo_municipio_fornecedor_xml IS NULL THEN 'Codigo municipio fornecedor nao localizado no XML; buscar no cadastro do fornecedor Decis.' END,
        CASE WHEN B.codigo_municipio_servico_xml IS NULL THEN 'Codigo municipio servico nao localizado no XML; conferir se Decis exige este campo.' END,
        'Fixos principais vieram do no Configura Parametros NFSe Decis; filial vem do CASE por CNPJ da empresa.'
    ) AS "OBS_DE_PARA"
FROM BASE B
CROSS JOIN PARAMETROS P
WHERE B.doc_type = 'NFSE'
  AND B.status <> 'evento_informativo'
  AND B.status <> 'cancelado'
  AND COALESCE(B.posted_to_erp, FALSE) = FALSE
  AND COALESCE(B.accounting_posted, 'N') <> 'S'
  AND B.company_cnpj_limpo = ANY (P.CNPJS_EMPRESAS)
  AND B.company_cnpj_limpo = B.recipient_cnpj_limpo
  --AND B.number = '827256'
ORDER BY
    B.id_fornecedor DESC NULLS FIRST,
    B.issuer_name ASC,
    B.issue_date ASC NULLS LAST,
    B.id DESC;
