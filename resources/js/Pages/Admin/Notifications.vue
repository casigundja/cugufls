<script setup>
import {Head,Link,router} from '@inertiajs/vue3';
import AdminLayout from '../../Components/AdminLayout.vue';
import Pagination from '../../Components/Pagination.vue';
defineProps({notifications:Object});
</script>
<template><AdminLayout><Head title="Notificações"/><div class="page-heading"><h1>Notificações</h1></div><section class="panel admin-form"><article v-for="n in notifications.data" :key="n.id" class="access-card"><h3>{{n.data.title}}</h3><p>{{n.data.message}}</p><div class="actions"><Link v-if="n.data.url" :href="n.data.url">Consultar ↗</Link><button v-if="!n.read_at" @click="router.patch('/admin/notificacoes/'+n.id+'/ler')">Marcar como lida</button><small v-else class="muted">Lida</small></div></article><p v-if="!notifications.data.length" class="empty-state">Não tem notificações.</p><Pagination :links="notifications.links"/></section></AdminLayout></template>
