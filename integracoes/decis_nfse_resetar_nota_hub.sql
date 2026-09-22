WITH PARAMETROS AS (
    SELECT
        31424::INTEGER AS ID_DOCUMENTO,
        '378'::TEXT AS NUMERO_NOTA,
        '23964352000162'::TEXT AS CNPJ_FORNECEDOR
)
UPDATE public.documents D
SET
    posted_to_erp = FALSE,
    entrada_date_erp = NULL,
    updated_at = NOW()
FROM PARAMETROS P
WHERE D.doc_type = 'NFSE'
  AND (
      D.id = P.ID_DOCUMENTO
      OR (
          LTRIM(D.number::TEXT, '0') = LTRIM(P.NUMERO_NOTA, '0')
          AND REGEXP_REPLACE(COALESCE(D.issuer_cnpj, ''), '[^0-9]', '', 'g') =
              REGEXP_REPLACE(P.CNPJ_FORNECEDOR, '[^0-9]', '', 'g')
      )
  )
  AND (
      P.CNPJ_FORNECEDOR IS NULL
      OR P.CNPJ_FORNECEDOR = ''
      OR REGEXP_REPLACE(COALESCE(D.issuer_cnpj, ''), '[^0-9]', '', 'g') =
         REGEXP_REPLACE(P.CNPJ_FORNECEDOR, '[^0-9]', '', 'g')
  )
RETURNING
    D.id,
    D.number,
    D.issue_date,
    D.issuer_name,
    D.issuer_cnpj,
    D.posted_to_erp,
    D.entrada_date_erp;
