<script setup>
import {useForm, Head} from '@inertiajs/vue3';
import AdminLayout from '../../Components/AdminLayout.vue';
const props=defineProps({values:Object});
const fields={hero_title:'Título principal',hero_description:'Descrição principal',about:'Sobre a empresa',contact_phone:'Telefone',contact_email:'Email',contact_whatsapp:'WhatsApp',facebook:'Facebook (URL)',instagram:'Instagram (URL)'};
const form=useForm(Object.fromEntries(Object.keys(fields).map(k=>[k,props.values[k]||''])));
</script>
<template><AdminLayout><Head title="Configurações"/><div class="page-heading"><div><span class="eyebrow">Website</span><h1>Configurações</h1><p>Conteúdo da página inicial e canais oficiais de atendimento.</p></div><a href="/" target="_blank" rel="noopener" class="button outline small">Ver website ↗</a></div><form class="panel admin-form" @submit.prevent="form.patch('/admin/configuracoes')"><div class="form-grid"><div v-for="(label,key) in fields" :key="key" class="field" :class="{full:['about','hero_description'].includes(key)}"><label :for="key">{{label}}</label><textarea v-if="['about','hero_description'].includes(key)" :id="key" v-model="form[key]"/><input v-else :id="key" v-model="form[key]" :type="key==='contact_email'?'email':['facebook','instagram'].includes(key)?'url':'text'"><small v-if="form.errors[key]">{{form.errors[key]}}</small></div></div><button class="button" :disabled="form.processing">{{form.processing?'A guardar…':'Guardar configurações'}}</button></form></AdminLayout></template>
