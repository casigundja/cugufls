export const labels = {name:'Nome',title:'Título',sku:'SKU',segment_id:'Segmento',category_id:'Categoria',branch_id:'Filial',price:'Preço',sale_price:'Promoção',quantity:'Quantidade',minimum_quantity:'Mínimo',maximum_quantity:'Máximo',status:'Estado',active:'Ativo',featured:'Destaque',public_id:'Referência',reference:'Referência',total:'Valor cotado',email:'Email',phone:'Telefone',city:'Cidade',province:'Província',type:'Tipo',created_at:'Data',action:'Ação',model_type:'Registo',model_id:'ID',question:'Pergunta',position:'Cargo',description:'Descrição',product_id:'Produto',previous_quantity:'Saldo anterior',new_quantity:'Novo saldo',reason:'Motivo',source_branch_id:'Origem',destination_branch_id:'Destino',placement:'Local',consented_at:'Autorização',message:'Mensagem'};
export const states = {new:'Novo',contacted:'Contactado',confirmed:'Confirmado',preparing:'Em preparação',ready:'Pronto',delivered:'Entregue',cancelled:'Cancelado',draft:'Rascunho',published:'Publicado',dispatched:'Em trânsito',received:'Recebido',return:'Devolução',entry:'Entrada',exit:'Saída',adjustment:'Ajuste',transfer_in:'Receção',transfer_out:'Expedição',pending:'Pendente',succeeded:'Importado',failed:'Erro',processing:'A processar',validated:'Pré-visualização',completed:'Concluído',general:'Geral',demo:'Demonstração',quote:'Orçamento',in_progress:'Em tratamento',closed:'Encerrado'};
export const money = value => value==null ? 'Sob consulta' : new Intl.NumberFormat('pt-AO',{style:'currency',currency:'AOA'}).format(Number(value));
export function value(row,key) {
 const rel = {product_id:'product',branch_id:'branch',segment_id:'segment',category_id:'category',source_branch_id:'source_branch',destination_branch_id:'destination_branch'}[key];
 if(rel && row[rel]) return row[rel].name;
 if(key==='status'||key==='type') return states[row[key]] || row[key];
 if(key==='active'||key==='featured') return row[key] ? 'Sim' : 'Não';
 if(key.includes('price')||key==='total') return money(row[key]);
 if(key.endsWith('_at')) return row[key] ? new Date(row[key]).toLocaleString('pt-AO') : '—';
 return row[key] ?? '—';
}
