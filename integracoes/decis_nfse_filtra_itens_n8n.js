// Para o fluxo n8n: remove retornos vazios da consulta antes de chamar o Decis.
// Quando a consulta nao encontra NFS-e pendente, alguns drivers ainda entregam
// um item vazio. Sem este filtro, o no do Decis recebe campos nulos e falha.
return items.filter((item) => {
  const json = item.json || {};
  const id = json.ID ?? json.id;
  const empresaDecis = json.VDNOTAC_EMPRESA ?? json.DECIS_EMPRESA;

  return (
    id !== undefined &&
    id !== null &&
    String(id).trim() !== '' &&
    empresaDecis !== undefined &&
    empresaDecis !== null &&
    String(empresaDecis).trim() !== ''
  );
});
