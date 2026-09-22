const onlyDigits = (value) => String(value ?? '').replace(/\D/g, '');
const clean = (value) => {
  const text = String(value ?? '').trim();
  return text === '' || text.toLowerCase() === 'null' ? '' : text;
};
const first = (...values) => {
  for (const value of values) {
    const text = clean(value);
    if (text !== '') return text;
  }
  return '';
};
const firstStateRegistration = (data) => {
  if (Array.isArray(data?.inscricoes_estaduais)) {
    const active = data.inscricoes_estaduais.find((item) => clean(item?.inscricao_estadual) !== '');
    return active?.inscricao_estadual;
  }
  return data?.inscricao_estadual;
};

async function fetchCnpjData(cnpj) {
  const response = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`, {
    method: 'GET',
    headers: {Accept: 'application/json'},
  });
  if (!response.ok) {
    return {ok: false, status: response.status, data: null};
  }
  return {ok: true, status: response.status, data: await response.json()};
}

const output = [];

for (const item of items) {
  const json = {...(item.json || {})};
  const cnpj = onlyDigits(json.CNPJ_FORNECEDOR_LIMPO || json.VDNOTAC_CNPJ || json.CGPESSOA_CNPJ || json.CNPJ_FORNECEDOR);

  json.CNPJ_ENRIQUECIDO_CONSULTADO = 'NAO';
  json.CNPJ_ENRIQUECIDO_STATUS = '';

  const needsData = [
    json.CGPESSOA_NOME,
    json.CGPESSOA_ENDERECO,
    json.CGPESSOA_BAIRRO,
    json.CGPESSOA_CEP,
    json.CGPESSOA_FONE,
    json.CGPESSOA_MUNICIPIO,
    json.CGPESSOA_UF,
    json.CGPESSOA_CODIGOMUNICIPIO,
    json.CGENDERECO_LOGRADOURO,
    json.CGENDERECO_CODIGOMUNICIPIO,
  ].some((value) => clean(value) === '');

  if (cnpj.length === 14 && needsData) {
    try {
      const result = await fetchCnpjData(cnpj);
      json.CNPJ_ENRIQUECIDO_CONSULTADO = 'SIM';
      json.CNPJ_ENRIQUECIDO_STATUS = String(result.status || '');

      if (result.ok && result.data) {
        const data = result.data;
        const tipoLogradouro = clean(data.descricao_tipo_de_logradouro);
        const logradouro = clean(data.logradouro);
        const endereco = first(
          tipoLogradouro && logradouro ? `${tipoLogradouro} ${logradouro}` : '',
          logradouro,
        );
        const telefone = first(data.ddd_telefone_1, data.ddd_telefone_2, data.ddd_fax);
        const inscricaoEstadual = firstStateRegistration(data);

        json.CGPESSOA_NOME = first(json.CGPESSOA_NOME, data.razao_social, data.nome_fantasia);
        json.FORNECEDOR = first(json.FORNECEDOR, data.razao_social, data.nome_fantasia);
        json.VDNOTAC_NOMEPESSOA = first(json.VDNOTAC_NOMEPESSOA, data.razao_social, data.nome_fantasia);
        json.CGPESSOA_ENDERECO = first(json.CGPESSOA_ENDERECO, endereco);
        json.CGPESSOA_NUMEROENDERECO = first(json.CGPESSOA_NUMEROENDERECO, data.numero);
        json.CGPESSOA_BAIRRO = first(json.CGPESSOA_BAIRRO, data.bairro);
        json.CGPESSOA_CEP = first(json.CGPESSOA_CEP, onlyDigits(data.cep));
        json.CGPESSOA_FONE = first(json.CGPESSOA_FONE, telefone);
        json.CGPESSOA_MUNICIPIO = first(json.CGPESSOA_MUNICIPIO, data.municipio);
        json.CGPESSOA_UF = first(json.CGPESSOA_UF, data.uf);
        json.CGPESSOA_CODIGOMUNICIPIO = first(json.CGPESSOA_CODIGOMUNICIPIO, data.codigo_municipio_ibge, data.codigo_municipio);
        json.CGPESSOA_INSCR_ESTADUAL = first(json.CGPESSOA_INSCR_ESTADUAL, inscricaoEstadual);

        json.CGJURIDICA_CNPJ = first(json.CGJURIDICA_CNPJ, cnpj);
        json.CGJURIDICA_INSCRICAO_ESTADUAL = first(json.CGJURIDICA_INSCRICAO_ESTADUAL, inscricaoEstadual);
        json.CGJURIDICA_INSCRICAO_MUNICIPAL = first(json.CGJURIDICA_INSCRICAO_MUNICIPAL, data.inscricao_municipal);
        json.CGJURIDICA_FANTASIA = first(json.CGJURIDICA_FANTASIA, data.nome_fantasia, data.razao_social);
        json.CGJURIDICA_DATA_ABERTURA = first(json.CGJURIDICA_DATA_ABERTURA, data.data_inicio_atividade);
        json.CGJURIDICA_CNAE = first(json.CGJURIDICA_CNAE, data.cnae_fiscal);

        json.CGENDERECO_CEP = first(json.CGENDERECO_CEP, onlyDigits(data.cep));
        json.CGENDERECO_TIPO_LOGRADOURO = first(json.CGENDERECO_TIPO_LOGRADOURO, tipoLogradouro);
        json.CGENDERECO_LOGRADOURO = first(json.CGENDERECO_LOGRADOURO, logradouro);
        json.CGENDERECO_COMPLEMENTO = first(json.CGENDERECO_COMPLEMENTO, data.complemento);
        json.CGENDERECO_CIDADE = first(json.CGENDERECO_CIDADE, data.municipio);
        json.CGENDERECO_UF = first(json.CGENDERECO_UF, data.uf);
        json.CGENDERECO_EMAIL = first(json.CGENDERECO_EMAIL, data.email);
        json.CGENDERECO_BAIRRO = first(json.CGENDERECO_BAIRRO, data.bairro);
        json.CGENDERECO_CODIGOMUNICIPIO = first(json.CGENDERECO_CODIGOMUNICIPIO, data.codigo_municipio_ibge, data.codigo_municipio);
        json.CGENDERECO_NUMERO = first(json.CGENDERECO_NUMERO, data.numero);
        json.CNPJ_ENRIQUECIDO_ORIGEM = 'BRASILAPI';
      }
    } catch (error) {
      json.CNPJ_ENRIQUECIDO_CONSULTADO = 'SIM';
      json.CNPJ_ENRIQUECIDO_STATUS = `ERRO: ${error.message || error}`;
    }
  }

  output.push({json});
}

return output;
