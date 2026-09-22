WITH PARAMETROS AS (
    SELECT
        DATE '2021-08-01' AS DATA_INICIAL,
        DATE '2026-07-31' AS DATA_FINAL,
        DATE '2026-08-28' AS DATA_ENTRADA_DECIS,
        9980::INTEGER AS USUARIO_DECIS,
        ARRAY[
            '05102155000586',
            '05102155000233',
            '05102155000403',
            '05102155000667',
            '05102155000152'
        ]::TEXT[] AS CNPJS_EMPRESAS
),
BASE AS (
    SELECT
        D.*,
        REGEXP_REPLACE(COALESCE(D.company_cnpj, ''), '[^0-9]', '', 'g') AS company_cnpj_limpo,
        REGEXP_REPLACE(COALESCE(D.issuer_cnpj, ''), '[^0-9]', '', 'g') AS issuer_cnpj_limpo,
        REGEXP_REPLACE(COALESCE(D.recipient_cnpj, ''), '[^0-9]', '', 'g') AS recipient_cnpj_limpo,
        (
            SELECT MAX(F.ID)
            FROM fornecedores F
            WHERE REGEXP_REPLACE(COALESCE(F.documento, ''), '[^0-9]', '', 'g') =
                  REGEXP_REPLACE(COALESCE(D.issuer_cnpj, ''), '[^0-9]', '', 'g')
        ) AS id_fornecedor,
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
            NULLIF(substring(COALESCE(D.raw_xml, '') FROM '(?s)<emit>.*?<cMun>([0-9]{7})</cMun>'), ''),
            NULLIF(substring(COALESCE(D.raw_xml, '') FROM '(?s)<EnderecoPrestador>.*?<Cidade>([0-9]{7})</Cidade>'), '')
        ) AS codigo_municipio_fornecedor_xml,
        COALESCE(
            CASE
                WHEN COALESCE(D.service_city, '') ~ '^[0-9]{7}$' THEN D.service_city
                ELSE NULL
            END,
            NULLIF(substring(COALESCE(D.raw_xml, '') FROM '(?s)<cLocIncid>([0-9]{7})</cLocIncid>'), ''),
            NULLIF(substring(COALESCE(D.raw_xml, '') FROM '(?s)<CodigoMunicipioOcorrencia>([0-9]{7})</CodigoMunicipioOcorrencia>'), '')
        ) AS codigo_municipio_servico_xml
    FROM documents D
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

    -- Campos preparados para o De >> Para / gravação no Decis.
    1 AS "DECIS_EMPRESA",
    NULL::INTEGER AS "DECIS_FILIAL",
    'E' AS "DECIS_ENTRADA_SAIDA",
    B.id_fornecedor AS "DECIS_PESSOA",
    B.number AS "DECIS_NOTA_FISCAL",
    COALESCE(NULLIF(B.service_series, ''), NULLIF(B.service_dps_series, ''), 'E') AS "DECIS_SERIE",
    NULLIF(B.cfop_calculado, '')::INTEGER AS "DECIS_MOVIMENTACAO_FISCAL",
    P.DATA_ENTRADA_DECIS AS "DECIS_DATA_ENTRADA",
    P.DATA_ENTRADA_DECIS AS "DECIS_DT_INCLUSAO",
    P.USUARIO_DECIS AS "DECIS_USUARIO",
    'Prestacao de Servico' AS "DECIS_NATUREZA",
    '01' AS "DECIS_MODELO_DOCUMENTO",
    COALESCE(NULLIF(B.service_description, ''), NULLIF(B.fiscal_observation, ''), 'SERVICO NFS-E') AS "DECIS_DESCRICAO_SERVICO",
    0 AS "DECIS_SERVICO",
    NULL::INTEGER AS "DECIS_CODIGO_PADRAO",
    13073 AS "DECIS_CONTA_CONTABIL",
    NULLIF(B.codigo_municipio_fornecedor_xml, '')::INTEGER AS "DECIS_CODIGO_MUNICIPIO_FORNECEDOR",
    NULLIF(B.codigo_municipio_servico_xml, '')::INTEGER AS "DECIS_CODIGO_MUNICIPIO_SERVICO",
    1 AS "DECIS_SEQUENCIA_SERVICO",
    B.fiscal_observation AS "DECIS_OBSERVACAO",

    CASE WHEN B.id_fornecedor IS NULL THEN 'SIM' ELSE 'NAO' END AS "PRECISA_CADASTRAR_FORNECEDOR",
    B.issuer_name AS "PESSOA_NOME",
    B.issuer_cnpj_limpo AS "PESSOA_CNPJ",
    B.issuer_city AS "PESSOA_CIDADE",
    B.issuer_uf AS "PESSOA_UF",
    NULLIF(B.codigo_municipio_fornecedor_xml, '')::INTEGER AS "PESSOA_CODIGO_MUNICIPIO",
    2 AS "PESSOA_FISICO_JURIDICO",
    1 AS "PESSOA_ESTADO",
    1 AS "PESSOA_FILIAL",

    CONCAT_WS(
        ' | ',
        CASE WHEN B.id_fornecedor IS NULL THEN 'Cadastrar fornecedor no Decis/CGPESSOA e preencher ID_FORNECEDOR/DECIS_PESSOA.' END,
        CASE WHEN B.company_cnpj_limpo = '05102155000152' THEN 'Informar DECIS_FILIAL para MATRIZ/MARINGA.' END,
        CASE WHEN B.company_cnpj_limpo = '05102155000233' THEN 'Informar DECIS_FILIAL para CURITIBA.' END,
        CASE WHEN B.company_cnpj_limpo = '05102155000403' THEN 'Informar DECIS_FILIAL para FOZ.' END,
        CASE WHEN B.company_cnpj_limpo = '05102155000586' THEN 'Informar DECIS_FILIAL para BATAGUASSU.' END,
        CASE WHEN B.company_cnpj_limpo = '05102155000667' THEN 'Informar DECIS_FILIAL para CASCAVEL.' END,
        CASE WHEN B.id_fornecedor IS NULL THEN 'Depois de cadastrar o fornecedor, retornar o codigo para ID_FORNECEDOR/DECIS_PESSOA.' END,
        CASE WHEN B.codigo_municipio_fornecedor_xml IS NULL THEN 'Codigo municipio fornecedor nao localizado no XML; buscar no cadastro do fornecedor Decis.' END,
        CASE WHEN B.codigo_municipio_servico_xml IS NULL THEN 'Codigo municipio servico nao localizado no XML; conferir se Decis exige este campo.' END,
        'DECIS_SERVICO=0, DECIS_CONTA_CONTABIL=13073 e DECIS_CODIGO_PADRAO=NULL conforme modelo recebido; ajustar se houver regra especifica por fornecedor.'
    ) AS "OBS_DE_PARA"
FROM BASE B
CROSS JOIN PARAMETROS P
WHERE B.doc_type = 'NFSE'
  AND B.status <> 'evento_informativo'
  AND B.status <> 'cancelado'
  AND COALESCE(B.posted_to_erp, FALSE) = FALSE
  AND COALESCE(B.accounting_posted, 'N') <> 'S'
  AND B.issue_date >= P.DATA_INICIAL
  AND B.issue_date < (P.DATA_FINAL + INTERVAL '1 day')
  AND B.company_cnpj_limpo = ANY (P.CNPJS_EMPRESAS)
  AND B.company_cnpj_limpo = B.recipient_cnpj_limpo
  --AND B.number = '827256'
ORDER BY
    B.id_fornecedor DESC NULLS FIRST,
    B.issuer_name ASC,
    B.issue_date ASC NULLS LAST,
    B.id DESC;
