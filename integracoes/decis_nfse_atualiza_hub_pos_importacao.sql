UPDATE public.documents
SET
  posted_to_erp = TRUE,
  entrada_date_erp = COALESCE(
    {{ $json.DECIS_DATA_ENTRADA ? "'" + $json.DECIS_DATA_ENTRADA + "'::date" : "NULL" }},
    {{ $json.DATA_ENTRADA_ERP ? "'" + $json.DATA_ENTRADA_ERP + "'::date" : "NULL" }},
    {{ $json.ENTRADA_DATE_ERP ? "'" + $json.ENTRADA_DATE_ERP + "'::date" : "NULL" }}
  ),
  updated_at = NOW()
WHERE
(
  id = {{ Number($json.ID || 0) }}

  OR

  (
    (
      LTRIM(number::TEXT, '0') =
      LTRIM('{{ String($json.DECIS_NOTA_FISCAL || $json.ID_NOTA_FISCAL || $json.NUMERO || "").replaceAll("'", "''") }}', '0')

      OR

      (
        LEFT(number::TEXT, 2) =
          RIGHT(EXTRACT(YEAR FROM issue_date)::TEXT, 2)

        AND

        LTRIM(SUBSTRING(number::TEXT FROM 3), '0') =
          LTRIM('{{ String($json.DECIS_NOTA_FISCAL || $json.ID_NOTA_FISCAL || $json.NUMERO || "").replaceAll("'", "''") }}', '0')
      )
    )

    AND REGEXP_REPLACE(COALESCE(issuer_cnpj, ''), '[^0-9]', '', 'g') =
        REGEXP_REPLACE('{{ String($json.CNPJ_FORNECEDOR || $json.CNPJ || "").replaceAll("'", "''") }}', '[^0-9]', '', 'g')
  )
)
AND doc_type = 'NFSE'
RETURNING
  id,
  number,
  issue_date,
  issuer_cnpj,
  posted_to_erp,
  entrada_date_erp;
