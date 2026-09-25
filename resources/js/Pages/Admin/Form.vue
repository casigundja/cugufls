<script setup>
import {ref,watch} from 'vue';
import {Link,useForm,router} from '@inertiajs/vue3';
import AdminLayout from '../../Components/AdminLayout.vue';
const props=defineProps({resource:String,definition:Object,record:Object,options:Object});
const values={};
for(const field of props.definition.fields){
 let value=props.record[field.name];
 if(value==null) value=field.type==='checkbox'?false:field.type==='multiselect'?[]:field.type==='json'?{}:field.name==='sort_order'?0:'';
 if(field.type==='datetime-local'&&value)value=value.slice(0,16);
 values[field.name]=value;
}
const form=useForm(values);
watch(()=>form.segment_id,()=>{if('category_id' in form)form.category_id='';if('parent_id' in form)form.parent_id='';});
function optionsFor(field){return (props.options[field.name]||[]).filter(o=>!o.segment_id||!form.segment_id||o.segment_id===form.segment_id).filter(o=>field.name!=='parent_id'||o.value!==props.record.id);}
const uploading=ref(false),uploadError=ref(''),gallery=ref('');
const days=['Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado','Domingo'];
function submit(){form.transform(data=>Object.fromEntries(Object.entries(data).map(([k,v])=>[k,v===''?null:v])))[props.record.id?'patch':'post']('/admin/'+props.resource+(props.record.id?'/'+props.record.id:''));}
async function upload(event,field){
 const file=event.target.files[0]; if(!file)return;
 uploading.value=true;uploadError.value='';
 const body=new FormData();body.append('image',file);
 try{const res=await fetch('/admin/imagens',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json'},body});const data=await res.json();if(!res.ok)throw new Error(data.message||'Falha no envio');if(field)form[field]=data.path;else gallery.value=data.path;}catch(e){uploadError.value=e.message;}finally{uploading.value=false;}
}
function attach(){router.post('/admin/produtos/'+props.record.id+'/imagens',{path:gallery.value,alt:props.record.name,sort_order:0},{onSuccess:()=>gallery.value=''});}
</script>
<template><AdminLayout><div class="page-heading"><div><span class="eyebrow">{{definition.group}}</span><h1>{{record.id?'Editar':'Novo registo'}} · {{definition.title}}</h1><p>Preencha os dados e guarde as alterações.</p></div><Link :href="'/admin/'+resource" class="button outline small">← Voltar</Link></div><form class="panel admin-form" @submit.prevent="submit"><div class="form-grid"><template v-for="field in definition.fields" :key="field.name">
<div v-if="field.type==='checkbox'" class="checkbox-row"><input type="checkbox" :id="field.name" v-model="form[field.name]"><label :for="field.name">{{field.label}}</label></div>
<div v-else class="field" :class="{full:['textarea','json','image'].includes(field.type)}"><label :for="field.name">{{field.label}} {{field.required?'*':''}}</label>
<textarea v-if="field.type==='textarea'" :id="field.name" v-model="form[field.name]" :required="field.required"/>
<select v-else-if="field.type==='select'||field.type==='multiselect'||field.options" :id="field.name" v-model="form[field.name]" :multiple="field.type==='multiselect'" :required="field.required"><option v-if="field.type!=='multiselect'" value="">Selecionar…</option><template v-if="field.options"><option v-for="(label,key) in field.options" :key="key" :value="key">{{label}}</option></template><template v-else><option v-for="option in optionsFor(field)" :key="option.value" :value="option.value">{{option.label}}</option></template></select>
<template v-else-if="field.type==='image'"><input type="file" :id="field.name" accept="image/jpeg,image/png,image/webp" @change="upload($event,field.name)"><img v-if="form[field.name]" :src="'/storage/'+form[field.name]" class="image-preview" alt="Pré-visualização"></template>
<div v-else-if="field.type==='json'" class="form-grid"><div v-for="day in days" :key="day" class="field"><label>{{day}}</label><input v-model="form[field.name][day]" placeholder="08:00–18:00 ou Encerrado"></div></div>
<input v-else :type="field.type" :id="field.name" v-model="form[field.name]" :required="field.required" :step="field.type==='number'?'any':undefined" :autocomplete="field.type==='password'?'new-password':undefined">
<small v-if="form.errors[field.name]">{{form.errors[field.name]}}</small></div>
</template></div><p v-if="uploadError" class="error-box">{{uploadError}}</p><div class="button-row"><button class="button" :disabled="form.processing||uploading">{{form.processing?'A guardar…':'Guardar alterações'}}</button><Link :href="'/admin/'+resource" class="text-link">Cancelar</Link></div></form>
<section v-if="resource==='produtos'&&record.id" class="panel admin-form"><h2>Galeria de imagens</h2><p class="muted">Adicione imagens JPEG, PNG ou WebP, até 5 MB.</p><input type="file" accept="image/jpeg,image/png,image/webp" @change="upload($event,null)"><img v-if="gallery" :src="'/storage/'+gallery" class="image-preview" alt=""><button v-if="gallery" class="button small" @click="attach">Adicionar ao produto</button><div v-for="image in record.product_images||[]" :key="image.id" class="access-card"><img :src="'/storage/'+image.path" class="image-preview" :alt="image.alt"><div class="form-grid"><div class="field"><label :for="'alt-'+image.id">Texto alternativo</label><input :id="'alt-'+image.id" v-model="image.alt" maxlength="255"></div><div class="field"><label :for="'sort-'+image.id">Ordem</label><input :id="'sort-'+image.id" v-model="image.sort_order" type="number" min="0"></div></div><div class="actions"><button @click="router.patch('/admin/imagens/'+image.id,{alt:image.alt,sort_order:image.sort_order},{preserveScroll:true})">Guardar imagem</button><button @click="router.delete('/admin/imagens/'+image.id,{preserveScroll:true})">Remover da galeria</button></div></div></section>
</AdminLayout></template>
